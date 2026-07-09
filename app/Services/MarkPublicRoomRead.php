<?php

namespace App\Services;

use App\Models\Message;
use App\Models\UserRoomState;
use App\Support\MessageRequestTiming;
use Illuminate\Support\Facades\DB;

class MarkPublicRoomRead
{
    public function __invoke(int $userId, int $roomId, ?int $latestMessageId = null): void
    {
        $latestMessageId ??= MessageRequestTiming::profileCurrentRequestStep(
            'mark_public_room_read',
            'latest_message_lookup',
            fn (): ?int => Message::where('room_id', $roomId)
                ->latest('id')
                ->value('id')
        );

        if (! $latestMessageId) {
            return;
        }

        $now = now();

        $updated = MessageRequestTiming::profileCurrentRequestStep(
            'mark_public_room_read',
            'conditional_update',
            fn (): int => UserRoomState::query()
                ->where('user_id', $userId)
                ->where('room_id', $roomId)
                ->where(function ($query) use ($latestMessageId) {
                    $query->whereNull('last_read_message_id')
                        ->orWhere('last_read_message_id', '<', $latestMessageId);
                })
                ->update([
                    'last_read_message_id' => $latestMessageId,
                    'updated_at' => $now,
                ])
        );

        if ($updated > 0) {
            return;
        }

        MessageRequestTiming::profileCurrentRequestStep(
            'mark_public_room_read',
            'insert_missing_state',
            function () use ($userId, $roomId, $latestMessageId, $now): void {
                DB::table('user_room_states')->insertOrIgnore([
                    'user_id' => $userId,
                    'room_id' => $roomId,
                    'is_following' => false,
                    'last_read_message_id' => $latestMessageId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        );
    }
}
