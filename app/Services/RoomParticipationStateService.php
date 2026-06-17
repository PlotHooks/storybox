<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Room;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RoomParticipationStateService
{
    public function issueToken(Room $room, Character $character): string
    {
        $key = $this->cacheKey($room, $character);
        $token = Cache::get($key);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = Str::random(64);

        Cache::put($key, $token, now()->addHours(12));

        return $token;
    }

    public function hasValidToken(Room $room, Character $character, ?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $stored = Cache::get($this->cacheKey($room, $character));

        return is_string($stored) && hash_equals($stored, $token);
    }

    public function clear(Room $room, Character $character): void
    {
        Cache::forget($this->cacheKey($room, $character));
    }

    private function cacheKey(Room $room, Character $character): string
    {
        return sprintf('room-participation:%d:%d', $room->id, $character->id);
    }
}
