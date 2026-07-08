<?php

namespace App\Events;

use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use App\Services\DiceMessageFormatter;
use App\Support\MessageRequestTiming;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
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
        return MessageRequestTiming::profileCurrentRequestStep('broadcasts_events', 'moderation_payload_build', function () use ($message): array {
            $message = MessageRequestTiming::profileCurrentRequestStep(
                'broadcasts_events',
                'moderation_relationship_loads',
                fn () => $message->loadMissing(['room', 'character', 'user'])
            );
            $room = $message->room;

            $preview = MessageRequestTiming::profileCurrentRequestStep('broadcasts_events', 'moderation_preview_build', function () use ($message): string {
                return Str::limit($message->isDice()
                    ? app(DiceMessageFormatter::class)->renderPlainText($message->structured_data)
                    : ($message->body ?? ''), 160);
            });

            $viewUrl = MessageRequestTiming::profileCurrentRequestStep(
                'broadcasts_events',
                'moderation_view_url_build',
                fn () => MessageResource::getUrl('view', ['record' => $message], panel: 'admin')
            );

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
                'preview' => $preview,
                'deleted' => (bool) $message->deleted_at,
                'view_url' => $viewUrl,
            ];
        });
    }
}