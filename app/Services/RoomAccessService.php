<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\RoomCharacterRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RoomAccessService
{
    public function canViewRoom(User $user, Room $room, ?Character $character): bool
    {
        if ($this->isSoftDeleted($room)) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $room->isPublicRoom()) {
            return false;
        }

        if ($this->isOwner($room, $character)) {
            return true;
        }

        if ($this->isBlacklisted($room, $character)) {
            return false;
        }

        if (! $room->isHidden()) {
            return true;
        }

        return $this->isModerator($room, $character) || $this->isWhitelisted($room, $character);
    }

    public function canJoinRoom(User $user, Room $room, ?Character $character): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $this->characterBelongsToUser($user, $character)) {
            return false;
        }

        return $this->canViewRoom($user, $room, $character);
    }

    public function canMessageRoom(User $user, Room $room, Character $character): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->characterBelongsToUser($user, $character)
            && $this->canJoinRoom($user, $room, $character);
    }

    public function canManageRoom(User $user, Room $room, Character $character): bool
    {
        if ($this->isSoftDeleted($room)) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $this->characterBelongsToUser($user, $character) || ! $room->isPublicRoom()) {
            return false;
        }

        return $this->isOwner($room, $character) || $this->isModerator($room, $character);
    }

    public function canModerateRoom(User $user, Room $room, Character $character): bool
    {
        return $this->canManageRoom($user, $room, $character);
    }

    public function canManageRoomAccess(User $user, Room $room, Character $character): bool
    {
        return $this->canManageRoom($user, $room, $character);
    }

    public function canSubscribeToRoom(User $user, Room $room, ?Character $character): bool
    {
        if ($this->isSoftDeleted($room)) {
            return false;
        }

        if (! $room->isPublicRoom()) {
            return false;
        }

        return $this->canJoinRoom($user, $room, $character);
    }

    public function applyVisiblePublicRoomScope(Builder $query, User $user, ?Character $character): Builder
    {
        $query->where('rooms.type', Room::TYPE_PUBLIC);

        if ($this->isAdmin($user)) {
            return $query;
        }

        if (! $this->characterBelongsToUser($user, $character)) {
            return $query->where('rooms.visibility', Room::VISIBILITY_PUBLIC);
        }

        $characterId = $character->id;

        return $query->where(function (Builder $roomQuery) use ($characterId) {
            $roomQuery->where('rooms.owner_character_id', $characterId)
                ->orWhere(function (Builder $accessibleQuery) use ($characterId) {
                    $accessibleQuery
                        ->whereNotExists($this->blacklistSubquery($characterId))
                        ->where(function (Builder $visibilityQuery) use ($characterId) {
                            $visibilityQuery
                                ->where('rooms.visibility', Room::VISIBILITY_PUBLIC)
                                ->orWhereExists($this->moderatorSubquery($characterId))
                                ->orWhereExists($this->whitelistSubquery($characterId));
                        });
                });
        });
    }

    public function isAdmin(User $user): bool
    {
        return (bool) ($user->is_admin ?? false);
    }

    public function isOwner(Room $room, ?Character $character): bool
    {
        return $character !== null
            && (int) $room->owner_character_id > 0
            && (int) $room->owner_character_id === (int) $character->id;
    }

    public function isModerator(Room $room, ?Character $character): bool
    {
        if ($character === null || ! $room->isPublicRoom()) {
            return false;
        }

        return DB::table('room_character_roles')
            ->where('room_id', $room->id)
            ->where('character_id', $character->id)
            ->where('role', RoomCharacterRole::ROLE_MODERATOR)
            ->exists();
    }

    public function isWhitelisted(Room $room, ?Character $character): bool
    {
        if ($character === null || ! $room->isPublicRoom()) {
            return false;
        }

        return DB::table('room_access_entries')
            ->where('room_id', $room->id)
            ->where('character_id', $character->id)
            ->where('type', RoomAccessEntry::TYPE_WHITELIST)
            ->exists();
    }

    public function isBlacklisted(Room $room, ?Character $character): bool
    {
        if ($character === null || ! $room->isPublicRoom() || $this->isOwner($room, $character)) {
            return false;
        }

        return DB::table('room_access_entries')
            ->where('room_id', $room->id)
            ->where('character_id', $character->id)
            ->where('type', RoomAccessEntry::TYPE_BLACKLIST)
            ->exists();
    }

    public function canDeleteRoom(User $user, Room $room, ?Character $character): bool
    {
        if ($this->isSoftDeleted($room) || ! $room->isPublicRoom()) {
            return false;
        }

        if ($character === null || ! $this->characterBelongsToUser($user, $character)) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->isOwner($room, $character);
    }

    private function characterBelongsToUser(User $user, ?Character $character): bool
    {
        return $character !== null && (int) $character->user_id === (int) $user->id;
    }

    private function isSoftDeleted(Room $room): bool
    {
        return method_exists($room, 'trashed') && $room->trashed();
    }

    private function moderatorSubquery(int $characterId)
    {
        return DB::table('room_character_roles')
            ->selectRaw('1')
            ->whereColumn('room_character_roles.room_id', 'rooms.id')
            ->where('room_character_roles.character_id', $characterId)
            ->where('room_character_roles.role', RoomCharacterRole::ROLE_MODERATOR);
    }

    private function whitelistSubquery(int $characterId)
    {
        return DB::table('room_access_entries')
            ->selectRaw('1')
            ->whereColumn('room_access_entries.room_id', 'rooms.id')
            ->where('room_access_entries.character_id', $characterId)
            ->where('room_access_entries.type', RoomAccessEntry::TYPE_WHITELIST);
    }

    private function blacklistSubquery(int $characterId)
    {
        return DB::table('room_access_entries')
            ->selectRaw('1')
            ->whereColumn('room_access_entries.room_id', 'rooms.id')
            ->where('room_access_entries.character_id', $characterId)
            ->where('room_access_entries.type', RoomAccessEntry::TYPE_BLACKLIST);
    }
}
