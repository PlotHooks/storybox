<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $characterId = (int) request()->input('character_id', 0);

    if (! $user || $characterId <= 0) {
        return false;
    }

    $ownsCharacter = DB::table('characters')
        ->where('id', $characterId)
        ->where('user_id', $user->id)
        ->exists();

    if (! $ownsCharacter) {
        return false;
    }

    $room = DB::table('rooms')
        ->where('id', $conversationId)
        ->first(['id', 'type']);

    if (! $room) {
        return false;
    }

    if ($room->type === 'dm') {
        return DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->where('character_id', $characterId)
            ->exists();
    }

    if ($room->type === 'public') {
        return DB::table('character_presences')
            ->where('room_id', $room->id)
            ->where('character_id', $characterId)
            ->exists();
    }

    return false;
});
