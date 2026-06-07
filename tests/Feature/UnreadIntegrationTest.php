<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterConversationRead;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Models\UserRoomState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnreadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_presence_ping_marks_a_public_room_read_for_the_user(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($otherUser);

        $message = $this->createMessage($room, $otherUser, $otherCharacter, 'Hello.');

        $this->actingAs($user)
            ->postJson(route('rooms.presence', $room->slug), [
                'character_id' => $character->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('user_room_states', [
            'user_id' => $user->id,
            'room_id' => $room->id,
            'last_read_message_id' => $message->id,
        ]);
    }

    public function test_followed_public_room_sidebar_unread_count_uses_the_user_read_marker_and_newer_messages(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($otherUser);

        $readMessage = $this->createMessage($room, $otherUser, $otherCharacter, 'Already read.');
        $this->createMessage($room, $otherUser, $otherCharacter, 'Unread one.');
        $this->createMessage($room, $otherUser, $otherCharacter, 'Unread two.');

        UserRoomState::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => true,
            'last_read_message_id' => $readMessage->id,
        ]);

        $rooms = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $character->id]))
            ->assertOk()
            ->json('rooms');

        $sidebarRoom = collect($rooms)->firstWhere('id', $room->id);

        $this->assertSame(2, (int) $sidebarRoom['unread_count']);
    }

    public function test_followed_public_room_sidebar_unread_count_excludes_soft_deleted_messages(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($otherUser);

        $readMessage = $this->createMessage($room, $otherUser, $otherCharacter, 'Already read.');
        $this->createMessage($room, $otherUser, $otherCharacter, 'Unread.');
        $deletedMessage = $this->createMessage($room, $otherUser, $otherCharacter, 'Deleted unread.');
        $deletedMessage->delete();

        UserRoomState::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => true,
            'last_read_message_id' => $readMessage->id,
        ]);

        $rooms = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $character->id]))
            ->assertOk()
            ->json('rooms');

        $sidebarRoom = collect($rooms)->firstWhere('id', $room->id);

        $this->assertSame(1, (int) $sidebarRoom['unread_count']);
    }

    public function test_switching_characters_does_not_change_public_room_unread_state(): void
    {
        $user = User::factory()->create();
        $firstCharacter = $this->createCharacter($user);
        $secondCharacter = $this->createCharacter($user);
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($otherUser);

        $readMessage = $this->createMessage($room, $otherUser, $otherCharacter, 'First.');
        $this->createMessage($room, $otherUser, $otherCharacter, 'Second.');

        UserRoomState::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => true,
            'last_read_message_id' => $readMessage->id,
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
        $this->assertSame(1, (int) collect($secondRooms)->firstWhere('id', $room->id)['unread_count']);
    }

    public function test_unfollowed_public_rooms_do_not_show_unread_counts(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($otherUser);

        $readMessage = $this->createMessage($room, $otherUser, $otherCharacter, 'Read.');
        $this->createMessage($room, $otherUser, $otherCharacter, 'Unread.');

        UserRoomState::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => false,
            'last_read_message_id' => $readMessage->id,
        ]);

        $rooms = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $character->id]))
            ->assertOk()
            ->json('rooms');

        $this->assertSame(0, (int) collect($rooms)->firstWhere('id', $room->id)['unread_count']);
    }

    public function test_public_room_sidebar_exposes_updated_at_for_recent_activity_sorting(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, ] = $this->createUserWithCharacter();
        $room = $this->createRoom($otherUser);

        $rooms = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $character->id]))
            ->assertOk()
            ->json('rooms');

        $sidebarRoom = collect($rooms)->firstWhere('id', $room->id);

        $this->assertIsString($sidebarRoom['updated_at'] ?? null);
        $this->assertNotSame('', $sidebarRoom['updated_at']);
    }

    public function test_creating_a_room_auto_follows_it_for_the_creator(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->post(route('rooms.store'), [
                'name' => 'Creator Follow Room',
                'description' => 'Auto follow test.',
            ])
            ->assertRedirect();

        $room = Room::query()->where('name', 'Creator Follow Room')->firstOrFail();

        $this->assertDatabaseHas('user_room_states', [
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => true,
        ]);
    }

    public function test_viewing_a_public_room_updates_read_state_without_auto_following(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($otherUser);
        $message = $this->createMessage($room, $otherUser, $otherCharacter, 'Unread message.');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();

        $this->assertDatabaseHas('user_room_states', [
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => false,
            'last_read_message_id' => $message->id,
        ]);
    }

    public function test_posting_in_a_public_room_does_not_auto_follow_it(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, ] = $this->createUserWithCharacter();
        $room = $this->createRoom($otherUser);

        $response = $this->actingAs($user)
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'body' => 'Message without follow.',
            ])
            ->assertOk();

        $messageId = (int) $response->json('id');

        $this->assertDatabaseHas('user_room_states', [
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => false,
            'last_read_message_id' => $messageId,
        ]);
    }

    public function test_follow_toggle_controls_public_room_unread_visibility_and_preserves_read_state(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($otherUser);

        $readMessage = $this->createMessage($room, $otherUser, $otherCharacter, 'Read.');
        $this->createMessage($room, $otherUser, $otherCharacter, 'Unread.');

        UserRoomState::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => true,
            'last_read_message_id' => $readMessage->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->put(route('rooms.follow', $room->slug), [
                'follow' => 0,
            ])
            ->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'follow']));

        $roomsWhileUnfollowed = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $character->id]))
            ->assertOk()
            ->json('rooms');

        $this->assertSame(0, (int) collect($roomsWhileUnfollowed)->firstWhere('id', $room->id)['unread_count']);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->put(route('rooms.follow', $room->slug), [
                'follow' => 1,
            ])
            ->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'follow']));

        $roomsAfterRefollow = $this->actingAs($user)
            ->getJson(route('rooms.sidebar', ['character_id' => $character->id]))
            ->assertOk()
            ->json('rooms');

        $this->assertSame(1, (int) collect($roomsAfterRefollow)->firstWhere('id', $room->id)['unread_count']);

        $this->assertDatabaseHas('user_room_states', [
            'user_id' => $user->id,
            'room_id' => $room->id,
            'is_following' => true,
            'last_read_message_id' => $readMessage->id,
        ]);
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

    public function test_opening_a_dm_marks_it_read_without_reordering_the_dm_list(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        [$thirdUser, $thirdCharacter] = $this->createUserWithCharacter();

        $olderRoom = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);
        $newerRoom = $this->createDmRoom($firstUser, $firstCharacter, $thirdUser, $thirdCharacter);

        DB::table('rooms')->where('id', $olderRoom->id)->update([
            'updated_at' => now()->subMinutes(10),
        ]);
        DB::table('rooms')->where('id', $newerRoom->id)->update([
            'updated_at' => now()->subMinute(),
        ]);

        $this->createMessage($olderRoom, $secondUser, $secondCharacter, 'Unread one.');
        $latestMessage = $this->createMessage($olderRoom, $secondUser, $secondCharacter, 'Unread two.');

        $roomsBeforeOpen = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertSame([$newerRoom->slug, $olderRoom->slug], collect($roomsBeforeOpen)->pluck('slug')->all());

        $this->actingAs($firstUser)
            ->getJson(route('dms.messages.index', $olderRoom->slug))
            ->assertOk();

        $this->assertDatabaseHas('character_conversation_reads', [
            'character_id' => $firstCharacter->id,
            'conversation_id' => $olderRoom->id,
            'last_read_message_id' => $latestMessage->id,
        ]);

        $roomsAfterOpen = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertSame([$newerRoom->slug, $olderRoom->slug], collect($roomsAfterOpen)->pluck('slug')->all());
        $this->assertSame(0, (int) collect($roomsAfterOpen)->firstWhere('slug', $olderRoom->slug)['unread_count']);
    }

    public function test_sending_a_dm_message_updates_dm_ordering_by_recent_activity(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        [$thirdUser, $thirdCharacter] = $this->createUserWithCharacter();

        $olderRoom = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);
        $newerRoom = $this->createDmRoom($firstUser, $firstCharacter, $thirdUser, $thirdCharacter);

        DB::table('rooms')->where('id', $olderRoom->id)->update([
            'updated_at' => now()->subMinutes(10),
        ]);
        DB::table('rooms')->where('id', $newerRoom->id)->update([
            'updated_at' => now()->subMinute(),
        ]);

        $beforeSend = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertSame([$newerRoom->slug, $olderRoom->slug], collect($beforeSend)->pluck('slug')->all());

        $this->actingAs($firstUser)
            ->postJson(route('dms.messages.store', $olderRoom->slug), [
                'body' => 'Fresh DM activity.',
            ])
            ->assertOk();

        $afterSend = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertSame([$olderRoom->slug, $newerRoom->slug], collect($afterSend)->pluck('slug')->all());
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
