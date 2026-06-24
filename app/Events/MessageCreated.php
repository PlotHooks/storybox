<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        private readonly Message $message,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("conversation.{$this->message->room_id}");
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'type' => $this->message->type ?? \App\Models\Message::TYPE_NORMAL,
            'body' => $this->message->body,
            'character_id' => $this->message->character_id,
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}
