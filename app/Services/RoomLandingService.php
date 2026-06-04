<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;

class RoomLandingService
{
    public function __construct(
        private readonly RoomAccessService $roomAccess,
    ) {
    }

    public function destinationFor(User $user): string
    {
        $activeCharacter = $this->resolveActiveOwnedCharacter($user);

        $currentRoom = $this->resolveCurrentRoom($user, $activeCharacter);
        if ($currentRoom) {
            return route('rooms.show', $currentRoom->slug, false);
        }

        $firstRoom = $this->resolveFirstAccessibleRoom($user, $activeCharacter);
        if ($firstRoom) {
            return route('rooms.show', $firstRoom->slug, false);
        }

        return route('rooms.index', absolute: false);
    }

    private function resolveCurrentRoom(User $user, ?Character $activeCharacter): ?Room
    {
        if (! $activeCharacter) {
            return null;
        }

        return Room::query()
            ->join('character_presences', 'rooms.id', '=', 'character_presences.room_id')
            ->where('character_presences.character_id', $activeCharacter->id)
            ->orderByDesc('character_presences.last_seen_at')
            ->select('rooms.*')
            ->get()
            ->first(fn (Room $room) => $this->roomAccess->canViewRoom($user, $room, $activeCharacter));
    }

    private function resolveFirstAccessibleRoom(User $user, ?Character $activeCharacter): ?Room
    {
        return $this->roomAccess
            ->applyVisiblePublicRoomScope(Room::query(), $user, $activeCharacter)
            ->orderBy('rooms.created_at')
            ->select('rooms.*')
            ->first();
    }

    private function resolveActiveOwnedCharacter(User $user): ?Character
    {
        $sessionCharacterId = (int) session('active_character_id', 0);

        if ($sessionCharacterId > 0) {
            $sessionCharacter = Character::query()
                ->where('id', $sessionCharacterId)
                ->where('user_id', $user->id)
                ->first();

            if ($sessionCharacter) {
                return $sessionCharacter;
            }
        }

        $firstCharacter = Character::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->first();

        if (! $firstCharacter) {
            return null;
        }

        session(['active_character_id' => $firstCharacter->id]);

        return $firstCharacter;
    }
}
