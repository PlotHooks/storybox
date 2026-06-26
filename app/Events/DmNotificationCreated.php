<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DmNotificationCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        private readonly int $recipientUserId,
        private readonly int $roomId,
        private readonly string $roomSlug,
        private readonly int $messageId,
        private readonly int $senderCharacterId,
        private readonly int $recipientCharacterId,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("dm-notifications.{$this->recipientUserId}");
    }

    public function broadcastAs(): string
    {
        return 'dm.notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'room_slug' => $this->roomSlug,
            'message_id' => $this->messageId,
            'sender_character_id' => $this->senderCharacterId,
            'recipient_character_id' => $this->recipientCharacterId,
        ];
    }
}
