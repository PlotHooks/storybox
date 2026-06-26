<?php

use App\Models\Character;
use App\Models\Room;
use App\Services\RoomAccessService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('moderation.messages', function ($user) {
    return Gate::allows('accessFilament', $user);
});

Broadcast::channel('dm-notifications.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $characterId = (int) request()->input('character_id', 0);

    if (! $user || $characterId <= 0) {
        return false;
    }

    $character = Character::query()
        ->where('id', $characterId)
        ->where('user_id', $user->id)
        ->first();

    if (! $character) {
        return false;
    }

    $conversation = Room::query()
        ->where('id', $conversationId)
        ->first(['id', 'type', 'owner_character_id', 'visibility']);

    if (! $conversation) {
        return false;
    }

    if ($conversation->type === Room::TYPE_DM) {
        return DB::table('dm_participants')
            ->where('room_id', $conversation->id)
            ->where('user_id', $user->id)
            ->where('character_id', $characterId)
            ->exists();
    }

    if ($conversation->type === Room::TYPE_PUBLIC) {
        return app(RoomAccessService::class)->canSubscribeToRoom($user, $conversation, $character);
    }

    return false;
});
