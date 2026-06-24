<?php

namespace App\Events;

use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Services\DiceMessageFormatter;
use Illuminate\Support\Str;

class ModerationMessageCreated implements ShouldBroadcast
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
        return new PrivateChannel('moderation.messages');
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return self::payloadFor($this->message);
    }

    public static function payloadFor(Message $message): array
    {
        $message = $message->loadMissing(['room', 'character', 'user']);
        $room = $message->room;

        return [
            'id' => $message->id,
            'room_id' => $message->room_id,
            'room_type' => $room?->type,
            'room_label' => $room?->type === 'dm'
                ? 'DM #' . $message->room_id
                : ($room?->name ?? 'Room #' . $message->room_id),
            'character_id' => $message->character_id,
            'character_name' => $message->character?->name,
            'type' => $message->type ?? Message::TYPE_NORMAL,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'created_at' => $message->created_at?->toISOString(),
            'preview' => Str::limit($message->isDice()
                ? app(DiceMessageFormatter::class)->renderPlainText($message->structured_data)
                : ($message->body ?? ''), 160),
            'deleted' => (bool) $message->deleted_at,
            'view_url' => MessageResource::getUrl('view', ['record' => $message], panel: 'admin'),
        ];
    }
}
