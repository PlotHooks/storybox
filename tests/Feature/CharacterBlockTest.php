<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterBlock;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CharacterBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_can_block_another_character_they_do_not_own(): void
    {
        [$blockerUser, $blockerCharacter] = $this->createUserWithCharacter();
        [$blockedUser, $blockedCharacter] = $this->createUserWithCharacter();

        $this->actingAs($blockerUser)
            ->postJson(route('characters.blocks.store', [$blockerCharacter, $blockedCharacter]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('character_blocks', [
            'blocker_character_id' => $blockerCharacter->id,
            'blocked_character_id' => $blockedCharacter->id,
        ]);
    }

    public function test_user_cannot_create_block_using_someone_elses_character_as_blocker(): void
    {
        [$owner, $ownerCharacter] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();

        $this->actingAs($otherUser)
            ->postJson(route('characters.blocks.store', [$ownerCharacter, $otherCharacter]))
            ->assertForbidden();

        $this->assertDatabaseCount('character_blocks', 0);
    }

    public function test_duplicate_block_is_idempotent(): void
    {
        [$blockerUser, $blockerCharacter] = $this->createUserWithCharacter();
        [$blockedUser, $blockedCharacter] = $this->createUserWithCharacter();

        $first = $this->actingAs($blockerUser)
            ->postJson(route('characters.blocks.store', [$blockerCharacter, $blockedCharacter]))
            ->assertOk();

        $second = $this->actingAs($blockerUser)
            ->postJson(route('characters.blocks.store', [$blockerCharacter, $blockedCharacter]))
            ->assertOk();

        $this->assertSame($first->json('block_id'), $second->json('block_id'));
        $this->assertDatabaseCount('character_blocks', 1);
    }

    public function test_self_block_is_prevented(): void
    {
        [$user, $character] = $this->createUserWithCharacter();

        $this->actingAs($user)
            ->postJson(route('characters.blocks.store', [$character, $character]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('character_blocks', 0);
    }

    public function test_unblock_is_idempotent(): void
    {
        [$blockerUser, $blockerCharacter] = $this->createUserWithCharacter();
        [$blockedUser, $blockedCharacter] = $this->createUserWithCharacter();

        CharacterBlock::create([
            'blocker_character_id' => $blockerCharacter->id,
            'blocked_character_id' => $blockedCharacter->id,
        ]);

        $this->actingAs($blockerUser)
            ->deleteJson(route('characters.blocks.destroy', [$blockerCharacter, $blockedCharacter]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAs($blockerUser)
            ->deleteJson(route('characters.blocks.destroy', [$blockerCharacter, $blockedCharacter]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseCount('character_blocks', 0);
    }

    public function test_if_a_blocks_b_a_cannot_create_dm_with_b(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();

        CharacterBlock::create([
            'blocker_character_id' => $firstCharacter->id,
            'blocked_character_id' => $secondCharacter->id,
        ]);

        $this->actingAs($firstUser)
            ->postJson(route('dms.start'), [
                'my_character_id' => $firstCharacter->id,
                'other_character_id' => $secondCharacter->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Room::where('type', 'dm')->count());
    }

    public function test_if_a_blocks_b_b_cannot_create_dm_with_a(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();

        CharacterBlock::create([
            'blocker_character_id' => $firstCharacter->id,
            'blocked_character_id' => $secondCharacter->id,
        ]);

        $this->actingAs($secondUser)
            ->postJson(route('dms.start'), [
                'my_character_id' => $secondCharacter->id,
                'other_character_id' => $firstCharacter->id,
            ])
            ->assertForbidden()
            ->assertJson(['message' => 'You cannot send a DM to this character.']);

        $this->assertSame(0, Room::where('type', 'dm')->count());
    }

    public function test_if_a_blocks_b_neither_can_send_new_dm_messages_and_existing_messages_remain(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $existingMessage = $this->createMessage($room, $secondUser, $secondCharacter, 'Existing DM message.');

        CharacterBlock::create([
            'blocker_character_id' => $firstCharacter->id,
            'blocked_character_id' => $secondCharacter->id,
        ]);

        $this->actingAs($firstUser)
            ->postJson(route('dms.messages.store', $room->slug), ['body' => 'Blocked from A.'])
            ->assertForbidden()
            ->assertJson(['message' => 'You cannot send a DM to this character.']);

        $this->actingAs($secondUser)
            ->postJson(route('dms.messages.store', $room->slug), ['body' => 'Blocked from B.'])
            ->assertForbidden()
            ->assertJson(['message' => 'You cannot send a DM to this character.']);

        $this->assertDatabaseHas('messages', [
            'id' => $existingMessage->id,
            'body' => 'Existing DM message.',
        ]);
        $this->assertSame(1, Message::where('room_id', $room->id)->count());
    }

    public function test_room_visibility_only_flags_characters_the_viewer_blocked_not_reverse_blocks(): void
    {
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$viewerBlockedUser, $viewerBlockedCharacter] = $this->createUserWithCharacter();
        [$blockedViewerUser, $blockedViewerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($viewerUser);

        $viewerBlockedMessage = $this->createMessage($room, $viewerBlockedUser, $viewerBlockedCharacter, 'Viewer blocked sender.');
        $blockedViewerMessage = $this->createMessage($room, $blockedViewerUser, $blockedViewerCharacter, 'Sender blocked viewer.');

        CharacterBlock::create([
            'blocker_character_id' => $viewerCharacter->id,
            'blocked_character_id' => $viewerBlockedCharacter->id,
        ]);
        CharacterBlock::create([
            'blocker_character_id' => $blockedViewerCharacter->id,
            'blocked_character_id' => $viewerCharacter->id,
        ]);

        $messages = $this->actingAs($viewerUser)
            ->getJson(route('rooms.messages.index', [
                'room' => $room->slug,
                'character_id' => $viewerCharacter->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertTrue((bool) collect($messages)->firstWhere('id', $viewerBlockedMessage->id)['is_blocked_by_viewer']);
        $this->assertFalse((bool) collect($messages)->firstWhere('id', $blockedViewerMessage->id)['is_blocked_by_viewer']);
    }

    public function test_admin_room_messages_are_not_flagged_by_user_blocks(): void
    {
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$blockedUser, $blockedCharacter] = $this->createUserWithCharacter();
        $admin = User::factory()->create(['is_admin' => true]);
        $adminCharacter = $this->createCharacter($admin);
        $room = $this->createRoom($viewerUser);
        $message = $this->createMessage($room, $blockedUser, $blockedCharacter, 'Moderator-visible message.');

        CharacterBlock::create([
            'blocker_character_id' => $adminCharacter->id,
            'blocked_character_id' => $blockedCharacter->id,
        ]);

        $messages = $this->actingAs($admin)
            ->getJson(route('rooms.messages.index', [
                'room' => $room->slug,
                'character_id' => $adminCharacter->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertFalse((bool) collect($messages)->firstWhere('id', $message->id)['is_blocked_by_viewer']);
    }

    public function test_reports_still_work_for_blocked_room_messages(): void
    {
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$blockedUser, $blockedCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($viewerUser);
        $message = $this->createMessage($room, $blockedUser, $blockedCharacter, 'Reportable blocked message.');

        CharacterBlock::create([
            'blocker_character_id' => $viewerCharacter->id,
            'blocked_character_id' => $blockedCharacter->id,
        ]);

        $this->actingAs($viewerUser)
            ->postJson(route('messages.report', $message), [
                'reason' => 'Still reportable.',
            ])
            ->assertCreated()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('message_reports', [
            'message_id' => $message->id,
            'reporter_user_id' => $viewerUser->id,
            'status' => 'pending',
        ]);
    }

    public function test_room_unread_counts_include_messages_when_no_blocks_exist(): void
    {
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($viewerUser);

        $this->createMessage($room, $otherUser, $otherCharacter, 'Unread message.');

        DB::table('user_room_states')->insert([
            'user_id' => $viewerUser->id,
            'room_id' => $room->id,
            'is_following' => true,
            'last_read_message_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rooms = $this->actingAs($viewerUser)
            ->getJson(route('rooms.sidebar', ['character_id' => $viewerCharacter->id]))
            ->assertOk()
            ->json('rooms');

        $this->assertSame(1, (int) collect($rooms)->firstWhere('id', $room->id)['unread_count']);
    }

    public function test_room_unread_counts_exclude_blocked_messages_bidirectionally(): void
    {
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$viewerBlockedUser, $viewerBlockedCharacter] = $this->createUserWithCharacter();
        [$blockedViewerUser, $blockedViewerCharacter] = $this->createUserWithCharacter();
        [$allowedUser, $allowedCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($viewerUser);

        $this->createMessage($room, $viewerBlockedUser, $viewerBlockedCharacter, 'Viewer blocked sender.');
        $this->createMessage($room, $blockedViewerUser, $blockedViewerCharacter, 'Sender blocked viewer.');
        $this->createMessage($room, $allowedUser, $allowedCharacter, 'Allowed unread.');

        CharacterBlock::create([
            'blocker_character_id' => $viewerCharacter->id,
            'blocked_character_id' => $viewerBlockedCharacter->id,
        ]);
        CharacterBlock::create([
            'blocker_character_id' => $blockedViewerCharacter->id,
            'blocked_character_id' => $viewerCharacter->id,
        ]);

        DB::table('user_room_states')->insert([
            'user_id' => $viewerUser->id,
            'room_id' => $room->id,
            'is_following' => true,
            'last_read_message_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rooms = $this->actingAs($viewerUser)
            ->getJson(route('rooms.sidebar', ['character_id' => $viewerCharacter->id]))
            ->assertOk()
            ->json('rooms');

        $this->assertSame(1, (int) collect($rooms)->firstWhere('id', $room->id)['unread_count']);
    }

    public function test_dm_unread_counts_exclude_blocked_messages_bidirectionally(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->createMessage($room, $secondUser, $secondCharacter, 'Unread before block.');

        CharacterBlock::create([
            'blocker_character_id' => $secondCharacter->id,
            'blocked_character_id' => $firstCharacter->id,
        ]);

        $rooms = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertSame(0, (int) collect($rooms)->firstWhere('slug', $room->slug)['unread_count']);
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
