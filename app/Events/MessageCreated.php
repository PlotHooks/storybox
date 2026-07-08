<?php

namespace App\Events;

use App\Support\MessageRequestTiming;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        private readonly int $roomId,
        private readonly int $id,
        private readonly string $type,
        private readonly ?string $body,
        private readonly ?int $characterId,
        private readonly ?string $createdAtIso,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("conversation.{$this->roomId}");
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return MessageRequestTiming::profileCurrentRequestStep('broadcasts_events', 'message_created_payload_build', function (): array {
            return [
                'id' => $this->id,
                'type' => $this->type,
                'body' => $this->body,
                'character_id' => $this->characterId,
                'created_at' => $this->createdAtIso,
            ];
        });
    }
}