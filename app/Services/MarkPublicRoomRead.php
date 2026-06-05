<?php

namespace App\Services;

use App\Models\Message;
use App\Models\UserRoomState;

class MarkPublicRoomRead
{
    public function __invoke(int $userId, int $roomId, ?int $latestMessageId = null): void
    {
        $latestMessageId ??= Message::where('room_id', $roomId)
            ->latest('id')
            ->value('id');

        if (! $latestMessageId) {
            return;
        }

        $now = now();

        UserRoomState::query()->insertOrIgnore([
            'user_id' => $userId,
            'room_id' => $roomId,
            'is_following' => false,
            'last_read_message_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        UserRoomState::query()
            ->where('user_id', $userId)
            ->where('room_id', $roomId)
            ->where(function ($query) use ($latestMessageId) {
                $query->whereNull('last_read_message_id')
                    ->orWhere('last_read_message_id', '<', $latestMessageId);
            })
            ->update([
                'last_read_message_id' => $latestMessageId,
                'updated_at' => $now,
            ]);
    }
}
