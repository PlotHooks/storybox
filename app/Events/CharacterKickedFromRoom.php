<?php

namespace App\Events;

use App\Models\Character;
use App\Models\Room;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CharacterKickedFromRoom implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        private readonly Room $room,
        private readonly Character $target,
        private readonly ?Character $actor = null,
        private readonly ?string $reason = null,
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("conversation.{$this->room->id}");
    }

    public function broadcastAs(): string
    {
        return 'room.character-kicked';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->room->id,
            'room_slug' => $this->room->slug,
            'target_character_id' => $this->target->id,
            'actor_character_id' => $this->actor?->id,
            'reason' => $this->reason,
            'destination' => route('rooms.landing', absolute: false),
        ];
    }
}
