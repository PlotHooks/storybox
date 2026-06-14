<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomNotice;
use App\Models\RoomPinnedNote;
use App\Models\RoomToolRead;
use App\Models\User;
use App\Models\WorldBookEntry;
use Carbon\CarbonInterface;

class RoomToolIndicatorService
{
    public function __construct(
        private readonly RoomAccessService $roomAccess,
    ) {
    }

    public function indicatorsFor(User $user, Room $room): array
    {
        $viewerCharacter = $this->viewerCharacterForRoom($user, $room);
        $canManage = $viewerCharacter !== null
            && $this->roomAccess->canManageRoom($user, $room, $viewerCharacter);

        $lastSeenByTool = RoomToolRead::query()
            ->where('user_id', $user->id)
            ->where('room_id', $room->id)
            ->get()
            ->mapWithKeys(fn (RoomToolRead $read) => [$read->tool => $read->last_seen_at]);

        $latestVisibleByTool = [
            RoomToolRead::TOOL_WORLD_BOOK => $this->latestWorldBookContentAt($room, $viewerCharacter, $canManage),
            RoomToolRead::TOOL_NOTICE_BOARD => $this->latestNoticeBoardContentAt($room),
            RoomToolRead::TOOL_PINNED_NOTES => $this->latestPinnedNotesContentAt($room),
        ];

        $indicators = [];

        foreach ($latestVisibleByTool as $tool => $latestVisibleAt) {
            $lastSeenAt = $lastSeenByTool[$tool] ?? null;
            $indicators[$tool] = $latestVisibleAt !== null
                && ($lastSeenAt === null || $latestVisibleAt->gt($lastSeenAt));
        }

        return $indicators;
    }

    public function markSeen(User $user, Room $room, string $tool): void
    {
        RoomToolRead::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'room_id' => $room->id,
                'tool' => $tool,
            ],
            [
                'last_seen_at' => now(),
            ]
        );
    }

    public function viewerCharacterForRoom(User $user, Room $room): ?Character
    {
        $preferredCharacterId = (int) session('active_character_id', 0);

        if ($preferredCharacterId > 0) {
            $preferred = Character::query()
                ->where('id', $preferredCharacterId)
                ->where('user_id', $user->id)
                ->first();

            if ($preferred !== null && $this->roomAccess->canViewRoom($user, $room, $preferred)) {
                return $preferred;
            }
        }

        return Character::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->first(fn (Character $character) => $this->roomAccess->canViewRoom($user, $room, $character));
    }

    private function latestWorldBookContentAt(Room $room, ?Character $viewerCharacter, bool $canManage): ?CarbonInterface
    {
        $entries = WorldBookEntry::query()
            ->where('room_id', $room->id)
            ->get();

        $timestamps = $entries
            ->map(function (WorldBookEntry $entry) use ($viewerCharacter, $canManage) {
                if ($canManage) {
                    return $entry->updated_at;
                }

                if ($entry->hasPublishedContent()) {
                    return collect([$entry->published_at, $entry->reviewed_at])
                        ->filter()
                        ->sort()
                        ->last();
                }

                if ($viewerCharacter !== null
                    && (int) $entry->author_character_id === (int) $viewerCharacter->id
                    && in_array($entry->status, [WorldBookEntry::STATUS_PENDING, WorldBookEntry::STATUS_REJECTED], true)) {
                    return $entry->updated_at;
                }

                return null;
            })
            ->filter();

        return $timestamps->isEmpty() ? null : $timestamps->sort()->last();
    }

    private function latestNoticeBoardContentAt(Room $room): ?CarbonInterface
    {
        return RoomNotice::query()
            ->where('room_id', $room->id)
            ->whereIn('status', [RoomNotice::STATUS_ACTIVE, RoomNotice::STATUS_CLOSED])
            ->orderByDesc('updated_at')
            ->first()?->updated_at;
    }

    private function latestPinnedNotesContentAt(Room $room): ?CarbonInterface
    {
        return RoomPinnedNote::query()
            ->where('room_id', $room->id)
            ->where('status', RoomPinnedNote::STATUS_ACTIVE)
            ->orderByDesc('updated_at')
            ->first()?->updated_at;
    }
}
