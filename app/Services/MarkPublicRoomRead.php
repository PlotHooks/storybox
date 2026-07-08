<?php

namespace App\Services;

use App\Models\Message;
use App\Models\UserRoomState;
use Illuminate\Support\Facades\DB;

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

        $updated = UserRoomState::query()
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

        if ($updated > 0) {
            return;
        }

        $currentMessageId = UserRoomState::query()
            ->where('user_id', $userId)
            ->where('room_id', $roomId)
            ->value('last_read_message_id');

        if ($currentMessageId !== null && (int) $currentMessageId >= $latestMessageId) {
            return;
        }

        DB::table('user_room_states')->insertOrIgnore([
            'user_id' => $userId,
            'room_id' => $roomId,
            'is_following' => false,
            'last_read_message_id' => $latestMessageId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
