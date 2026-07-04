<?php

namespace App\Events;

use App\Models\Character;
use App\Models\Room;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomDisplayCleared implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        private readonly Room $room,
        private readonly ?Character $actor = null,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("conversation.{$this->room->id}");
    }

    public function broadcastAs(): string
    {
        return 'room.display-cleared';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => (int) $this->room->id,
            'room_slug' => (string) $this->room->slug,
            'actor_character_id' => $this->actor?->id ? (int) $this->actor->id : null,
        ];
    }
}
