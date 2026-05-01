<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterConversationRead;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnreadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_room_marks_it_read_for_the_active_character(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);

        $message = $this->createMessage($room, $user, $character, 'Hello.');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();

        $this->assertDatabaseHas('character_conversation_reads', [
            'character_id' => $character->id,
            'conversation_id' => $room->id,
            'last_read_message_id' => $message->id,
        ]);
    }

    public function test_sidebar_unread_count_uses_the_read_marker_and_newer_messages(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);

        $readMessage = $this->createMessage($room, $user, $character, 'Already read.');
        $this->createMessage($room, $user, $character, 'Unread one.');
        $this->createMessage($room, $user, $character, 'Unread two.');

        CharacterConversationRead::create([
            'character_id' => $character->id,
            'conversation_id' => $room->id,
            'last_read_message_id' => $readMessage->id,
        ]);

        $rooms = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $character->id]))
            ->assertOk()
            ->json('rooms');

        $sidebarRoom = collect($rooms)->firstWhere('id', $room->id);

        $this->assertSame(2, (int) $sidebarRoom['unread_count']);
    }

    public function test_sidebar_unread_count_excludes_soft_deleted_messages(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);

        $readMessage = $this->createMessage($room, $user, $character, 'Already read.');
        $this->createMessage($room, $user, $character, 'Unread.');
        $deletedMessage = $this->createMessage($room, $user, $character, 'Deleted unread.');
        $deletedMessage->delete();

        CharacterConversationRead::create([
            'character_id' => $character->id,
            'conversation_id' => $room->id,
            'last_read_message_id' => $readMessage->id,
        ]);

        $rooms = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $character->id]))
            ->assertOk()
            ->json('rooms');

        $sidebarRoom = collect($rooms)->firstWhere('id', $room->id);

        $this->assertSame(1, (int) $sidebarRoom['unread_count']);
    }

    public function test_switching_characters_does_not_share_read_state(): void
    {
        $user = User::factory()->create();
        $firstCharacter = $this->createCharacter($user);
        $secondCharacter = $this->createCharacter($user);
        $room = $this->createRoom($user);

        $firstMessage = $this->createMessage($room, $user, $firstCharacter, 'First.');
        $this->createMessage($room, $user, $firstCharacter, 'Second.');

        CharacterConversationRead::create([
            'character_id' => $firstCharacter->id,
            'conversation_id' => $room->id,
            'last_read_message_id' => $firstMessage->id,
        ]);

        $firstRooms = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $firstCharacter->id]))
            ->assertOk()
            ->json('rooms');

        $secondRooms = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $secondCharacter->id]))
            ->assertOk()
            ->json('rooms');

        $this->assertSame(1, (int) collect($firstRooms)->firstWhere('id', $room->id)['unread_count']);
        $this->assertSame(2, (int) collect($secondRooms)->firstWhere('id', $room->id)['unread_count']);
    }

    public function test_dm_list_counts_unread_messages_and_opening_dm_marks_read(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->createMessage($room, $secondUser, $secondCharacter, 'Unread one.');
        $latestMessage = $this->createMessage($room, $secondUser, $secondCharacter, 'Unread two.');

        $roomsBeforeOpen = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertSame(2, (int) collect($roomsBeforeOpen)->firstWhere('slug', $room->slug)['unread_count']);

        $this->actingAs($firstUser)
            ->getJson(route('dms.messages.index', $room->slug))
            ->assertOk();

        $this->assertDatabaseHas('character_conversation_reads', [
            'character_id' => $firstCharacter->id,
            'conversation_id' => $room->id,
            'last_read_message_id' => $latestMessage->id,
        ]);

        $roomsAfterOpen = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertSame(0, (int) collect($roomsAfterOpen)->firstWhere('slug', $room->slug)['unread_count']);
    }

    private function createUserWithCharacter(): array
    {
        $user = User::factory()->create();

        return [$user, $this->createCharacter($user)];
    }

    private function createCharacter(User $user): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => 'Character ' . Str::random(8),
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createRoom(User $user): Room
    {
        return Room::create([
            'name' => 'Room ' . Str::random(8),
            'slug' => 'room-' . Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => 'public',
        ]);
    }

    private function createDmRoom(
        User $firstUser,
        Character $firstCharacter,
        User $secondUser,
        Character $secondCharacter
    ): Room {
        $room = Room::create([
            'name' => 'DM',
            'slug' => 'dm-' . Str::random(16),
            'user_id' => $firstUser->id,
            'created_by' => $firstUser->id,
            'type' => 'dm',
            'dm_key' => Room::normalizedDmKey($firstCharacter->id, $secondCharacter->id),
        ]);

        $now = now();

        DB::table('dm_participants')->insert([
            [
                'room_id' => $room->id,
                'user_id' => $firstUser->id,
                'character_id' => $firstCharacter->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'room_id' => $room->id,
                'user_id' => $secondUser->id,
                'character_id' => $secondCharacter->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $room;
    }

    private function createMessage(Room $room, User $user, Character $character, string $body): Message
    {
        return Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => $body,
        ]);
    }
}
