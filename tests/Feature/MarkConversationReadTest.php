<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterConversationRead;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\MarkConversationRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarkConversationReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_read_marker_for_the_latest_room_message(): void
    {
        [$user, $character, $room] = $this->createRoomWithCharacter();

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => 'First message.',
        ]);

        app(MarkConversationRead::class)($character->id, $room->id);

        $this->assertDatabaseHas('character_conversation_reads', [
            'character_id' => $character->id,
            'conversation_id' => $room->id,
            'last_read_message_id' => $message->id,
        ]);
    }

    public function test_it_never_moves_the_read_marker_backward(): void
    {
        [$user, $character, $room] = $this->createRoomWithCharacter();

        $olderMessage = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => 'Older message.',
        ]);

        $newerMessage = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => 'Newer message.',
        ]);

        CharacterConversationRead::create([
            'character_id' => $character->id,
            'conversation_id' => $room->id,
            'last_read_message_id' => $newerMessage->id,
        ]);

        $newerMessage->delete();

        app(MarkConversationRead::class)($character->id, $room->id);

        $this->assertSame(
            $newerMessage->id,
            CharacterConversationRead::where('character_id', $character->id)
                ->where('conversation_id', $room->id)
                ->value('last_read_message_id')
        );

        $this->assertNotSame($olderMessage->id, $newerMessage->id);
    }

    public function test_it_does_not_create_a_read_marker_for_an_empty_room(): void
    {
        [, $character, $room] = $this->createRoomWithCharacter();

        app(MarkConversationRead::class)($character->id, $room->id);

        $this->assertDatabaseMissing('character_conversation_reads', [
            'character_id' => $character->id,
            'conversation_id' => $room->id,
        ]);
    }

    private function createRoomWithCharacter(): array
    {
        $user = User::factory()->create();

        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Character ' . Str::random(8),
            'slug' => 'character-' . Str::random(16),
        ]);

        $room = Room::create([
            'name' => 'Public Room',
            'slug' => 'room-' . Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'public',
        ]);

        return [$user, $character, $room];
    }
}
