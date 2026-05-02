<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('moderation.messages', function ($user) {
    return Gate::allows('accessFilament', $user);
});

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

    // rooms table is the conversation model.
    $conversation = DB::table('rooms')
        ->where('id', $conversationId)
        ->first(['id', 'type']);

    if (! $conversation) {
        return false;
    }

    if ($conversation->type === 'dm') {
        return DB::table('dm_participants')
            ->where('room_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('character_id', $characterId)
            ->exists();
    }

    if ($conversation->type === 'public') {
        return DB::table('character_presences')
            ->where('room_id', $conversation->id)
            ->where('character_id', $characterId)
            ->exists();
    }

    return false;
});
