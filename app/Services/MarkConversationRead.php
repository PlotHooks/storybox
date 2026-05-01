<?php

namespace App\Services;

use App\Models\CharacterConversationRead;
use App\Models\Message;

class MarkConversationRead
{
    public function __invoke(int $characterId, int $conversationId): void
    {
        $latestMessageId = Message::where('room_id', $conversationId)
            ->latest('id')
            ->value('id');

        if (! $latestMessageId) {
            return;
        }

        $now = now();

        CharacterConversationRead::query()->insertOrIgnore([
            'character_id' => $characterId,
            'conversation_id' => $conversationId,
            'last_read_message_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        CharacterConversationRead::where('character_id', $characterId)
            ->where('conversation_id', $conversationId)
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
