<?php

namespace App\Services;

use App\Models\ArchivedWorldBook;
use App\Models\ArchivedWorldBookEntry;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoomRetentionService
{
    public const TIER_NEW = 'new';
    public const TIER_MATURE = 'mature';
    public const TIER_PREMIUM = 'premium';

    public function __construct(
        private readonly RoomAccessService $roomAccess,
    ) {
    }

    public function tierForUser(User $user): string
    {
        if ($this->isPremiumUser($user)) {
            return self::TIER_PREMIUM;
        }

        $matureAfterDays = (int) config('retention.rooms.tiers.mature.starts_after_days', 30);

        return $user->created_at !== null && $user->created_at->lte(now()->subDays($matureAfterDays))
            ? self::TIER_MATURE
            : self::TIER_NEW;
    }

    public function activePublicRoomLimitForUser(User $user): int
    {
        return (int) $this->tierConfigForUser($user)['active_room_limit'];
    }

    public function activePublicRoomCountForUser(User $user): int
    {
        return Room::query()
            ->where('created_by', $user->id)
            ->where('type', Room::TYPE_PUBLIC)
            ->count();
    }

    public function ensureCanCreatePublicRoom(User $user): void
    {
        $limit = $this->activePublicRoomLimitForUser($user);
        $count = $this->activePublicRoomCountForUser($user);

        if ($count < $limit) {
            return;
        }

        $tier = $this->tierForUser($user);
        $label = $tier === self::TIER_MATURE ? 'mature' : 'new';
        $suffix = $limit === 1 ? '' : 's';

        throw ValidationException::withMessages([
            'room_limit' => [
                "Your {$label} account can have up to {$limit} active public room{$suffix}. Wait for an inactive room to expire or delete an existing room before creating another.",
            ],
        ]);
    }

    public function recordPublicRoomPost(Room $room, ?Carbon $postedAt = null): void
    {
        if (! $room->isPublicRoom()) {
            return;
        }

        $room->forceFill([
            'last_posted_at' => ($postedAt ?? now())->copy(),
        ])->save();
    }

    public function expireInactiveRooms(bool $dryRun, int $limit): array
    {
        $now = now();
        $matched = 0;
        $affected = 0;
        $scanned = 0;
        $lastId = 0;
        $batchSize = max(1, min((int) config('retention.rooms.command_batch_size', 100), $limit));

        while ($matched < $limit) {
            $candidateIds = $this->inactiveCandidateQuery($now)
                ->where('id', '>', $lastId)
                ->limit(min($batchSize, $limit - $matched))
                ->pluck('id');

            if ($candidateIds->isEmpty()) {
                break;
            }

            foreach ($candidateIds as $roomId) {
                $lastId = max($lastId, (int) $roomId);
                $scanned++;

                $room = Room::query()->with('creator')->find($roomId);

                if (! $room || ! $this->isInactiveRoomEligible($room, $now)) {
                    continue;
                }

                $matched++;

                if ($dryRun) {
                    continue;
                }

                $this->softDeleteRoom($room);
                $affected++;
            }
        }

        $result = [
            'dry_run' => $dryRun,
            'limit' => $limit,
            'scanned' => $scanned,
            'matched' => $matched,
            'soft_deleted' => $affected,
        ];

        Log::info('Room retention expire run completed.', $result);

        return $result;
    }

    public function hardDeleteExpiredRooms(bool $dryRun, int $limit): array
    {
        $now = now();
        $matched = 0;
        $affected = 0;
        $scanned = 0;
        $lastId = 0;
        $batchSize = max(1, min((int) config('retention.rooms.command_batch_size', 100), $limit));

        while ($matched < $limit) {
            $candidateIds = $this->hardDeleteCandidateQuery($now)
                ->where('id', '>', $lastId)
                ->limit(min($batchSize, $limit - $matched))
                ->pluck('id');

            if ($candidateIds->isEmpty()) {
                break;
            }

            foreach ($candidateIds as $roomId) {
                $lastId = max($lastId, (int) $roomId);
                $scanned++;

                $room = Room::withTrashed()->with('creator')->find($roomId);

                if (! $room || ! $this->isHardDeleteEligible($room, $now)) {
                    continue;
                }

                $matched++;

                if ($dryRun) {
                    continue;
                }

                DB::transaction(function () use ($room) {
                    $this->archiveRoomWorldBook($room);
                    $room->forceDelete();
                });
                $affected++;
            }
        }

        $result = [
            'dry_run' => $dryRun,
            'limit' => $limit,
            'scanned' => $scanned,
            'matched' => $matched,
            'hard_deleted' => $affected,
        ];

        Log::info('Room retention hard-delete run completed.', $result);

        return $result;
    }

    public function isInactiveRoomEligible(Room $room, ?Carbon $now = null): bool
    {
        if (! $room->isPublicRoom() || $room->trashed() || ! $room->creator) {
            return false;
        }

        $now ??= now();
        $inactiveAfterHours = (int) $this->tierConfigForUser($room->creator)['inactive_after_hours'];
        $cutoff = $now->copy()->subHours($inactiveAfterHours);

        return $this->lastActivityAt($room)->lte($cutoff);
    }

    public function isHardDeleteEligible(Room $room, ?Carbon $now = null): bool
    {
        if (! $room->isPublicRoom() || ! $room->trashed() || ! $room->deleted_at) {
            return false;
        }

        $now ??= now();
        $cutoff = $now->copy()->subDays((int) config('retention.rooms.recovery_window_days', 90));

        return $room->deleted_at->lte($cutoff);
    }

    public function lastActivityAt(Room $room): Carbon
    {
        return ($room->last_posted_at ?? $room->created_at ?? now())->copy();
    }

    private function inactiveCandidateQuery(Carbon $now): Builder
    {
        $newAccountHours = (int) config('retention.rooms.tiers.new.inactive_after_hours', 24);
        $oldestAllowedActivity = $now->copy()->subHours($newAccountHours);

        return Room::query()
            ->with('creator')
            ->where('type', Room::TYPE_PUBLIC)
            ->where(function (Builder $query) use ($oldestAllowedActivity) {
                $query->where('last_posted_at', '<=', $oldestAllowedActivity)
                    ->orWhere(function (Builder $subquery) use ($oldestAllowedActivity) {
                        $subquery->whereNull('last_posted_at')
                            ->where('created_at', '<=', $oldestAllowedActivity);
                    });
            })
            ->orderBy('id');
    }

    private function hardDeleteCandidateQuery(Carbon $now): Builder
    {
        $cutoff = $now->copy()->subDays((int) config('retention.rooms.recovery_window_days', 90));

        return Room::onlyTrashed()
            ->where('type', Room::TYPE_PUBLIC)
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id');
    }

    private function softDeleteRoom(Room $room): void
    {
        DB::transaction(function () use ($room) {
            $room->characterPresences()->delete();
            DB::table('room_user_presence')->where('room_id', $room->id)->delete();
            DB::table('room_presences')->where('room_id', $room->id)->delete();
            $room->delete();
        });
    }

    // TODO: If archived World Book cleanup is ever added, keep it separate from room/message retention, measure it in years, and never silently delete user lore.
    private function archiveRoomWorldBook(Room $room): void
    {
        if (! $room->isPublicRoom()) {
            return;
        }

        $ownerUserId = $this->roomAccess->ownerUserId($room) ?? (int) ($room->created_by ?? 0);
        if ($ownerUserId <= 0) {
            Log::warning('Skipped World Book archive because room owner account could not be resolved.', [
                'room_id' => $room->id,
                'occurred_at' => now()->toISOString(),
            ]);

            return;
        }

        $entries = DB::table('world_book_entries')
            ->where('room_id', $room->id)
            ->orderBy('id')
            ->get([
                'id',
                'status',
                'sort_order',
                'title',
                'category',
                'image_url',
                'body',
                'tags',
                'draft_title',
                'draft_category',
                'draft_image_url',
                'draft_body',
                'draft_tags',
                'published_at',
                'reviewed_at',
                'rejection_note',
                'rejected_at',
                'created_at',
                'updated_at',
            ]);

        if ($entries->isEmpty()) {
            return;
        }

        $archive = ArchivedWorldBook::query()->firstOrCreate(
            ['original_room_id' => $room->id],
            [
                'owner_user_id' => $ownerUserId,
                'original_room_name' => (string) $room->name,
                'room_deleted_at' => $room->deleted_at,
                'entry_count' => $entries->count(),
                'recovery_key' => (string) Str::uuid(),
                'archived_at' => now(),
            ]
        );

        foreach ($entries as $entry) {
            ArchivedWorldBookEntry::query()->updateOrCreate(
                [
                    'archived_world_book_id' => $archive->id,
                    'source_world_book_entry_id' => $entry->id,
                ],
                [
                    'status' => $entry->status,
                    'sort_order' => $entry->sort_order,
                    'title' => $entry->title,
                    'category' => $entry->category,
                    'image_url' => $entry->image_url,
                    'body' => $entry->body,
                    'tags' => $this->decodeJsonArray($entry->tags),
                    'draft_title' => $entry->draft_title,
                    'draft_category' => $entry->draft_category,
                    'draft_image_url' => $entry->draft_image_url,
                    'draft_body' => $entry->draft_body,
                    'draft_tags' => $this->decodeJsonArray($entry->draft_tags),
                    'published_at' => $entry->published_at,
                    'reviewed_at' => $entry->reviewed_at,
                    'rejection_note' => $entry->rejection_note,
                    'rejected_at' => $entry->rejected_at,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ]
            );
        }

        $archive->forceFill([
            'owner_user_id' => $ownerUserId,
            'original_room_name' => (string) $room->name,
            'room_deleted_at' => $room->deleted_at,
            'entry_count' => $entries->count(),
        ])->save();

        Log::info('Archived World Book before room hard delete.', [
            'room_id' => $room->id,
            'owner_user_id' => $ownerUserId,
            'archived_world_book_id' => $archive->id,
            'entry_count' => $entries->count(),
            'occurred_at' => now()->toISOString(),
        ]);
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function tierConfigForUser(User $user): array
    {
        return config('retention.rooms.tiers.' . $this->tierForUser($user), []);
    }

    private function isPremiumUser(User $user): bool
    {
        return false;
    }
}
