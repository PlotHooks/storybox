<?php

namespace App\Services;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoomRecoveryService
{
    public function __construct(
        private readonly RoomAccessService $roomAccess,
    ) {
    }

    public function recoverableRoomsForUser(User $user): Collection
    {
        return $this->recoverableRoomsQueryForUser($user)->get();
    }


    public function recoverableRoomCountForUser(User $user): int
    {
        return $this->recoverableRoomsQueryForUser($user)->count();
    }

    public function recoverableRoomsQueryForUser(User $user): Builder
    {
        $query = Room::query()
            ->onlyTrashed()
            ->with(['ownerCharacter', 'creator'])
            ->where('type', Room::TYPE_PUBLIC)
            ->where('deleted_at', '>', $this->recoveryCutoff())
            ->orderByDesc('deleted_at')
            ->orderByDesc('id');

        if ($this->roomAccess->isAdmin($user)) {
            return $query;
        }

        return $query->whereExists(function ($subquery) use ($user) {
            $subquery->selectRaw('1')
                ->from('characters')
                ->whereColumn('characters.id', 'rooms.owner_character_id')
                ->where('characters.user_id', $user->id);
        });
    }

    public function canRestoreRoom(User $user, Room $room): bool
    {
        if (! $room->isPublicRoom()) {
            return false;
        }

        if ($this->roomAccess->isAdmin($user)) {
            return true;
        }

        return (int) ($this->roomAccess->ownerUserId($room) ?? 0) === (int) $user->id;
    }

    public function isRecoverable(Room $room, ?Carbon $now = null): bool
    {
        if (! $room->isPublicRoom() || ! $room->trashed() || ! $room->deleted_at) {
            return false;
        }

        $now ??= now();

        return $room->deleted_at->gt($now->copy()->subDays($this->recoveryWindowDays()));
    }

    public function recoveryExpiresAt(Room $room): ?Carbon
    {
        return $room->deleted_at?->copy()->addDays($this->recoveryWindowDays());
    }

    public function restore(Room $room): void
    {
        $room->restore();
    }

    private function recoveryCutoff(): Carbon
    {
        return now()->subDays($this->recoveryWindowDays());
    }

    private function recoveryWindowDays(): int
    {
        return (int) config('retention.rooms.recovery_window_days', 90);
    }
}
