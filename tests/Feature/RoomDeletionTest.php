<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\RoomCharacterRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        Broadcast::purge('reverb');

        require base_path('routes/channels.php');
    }

    public function test_owner_and_admin_see_danger_zone_but_moderator_ordinary_user_and_dm_view_do_not(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $admin = User::factory()->create(['is_admin' => true]);
        $adminCharacter = $this->createCharacter($admin);
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();

        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->addModerator($room, $moderatorCharacter);
        $dmRoom = $this->createDmRoom($ownerUser, $ownerCharacter, $otherUser, $otherCharacter);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertSee('Danger Zone')
            ->assertSee('Delete Room');

        $this->actingAs($admin)
            ->withSession(['active_character_id' => $adminCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertSee('Danger Zone');

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertDontSee('Danger Zone');

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertDontSee('Danger Zone');

        $this->actingAs($ownerUser)
            ->get(route('dms.messages.index', $dmRoom->slug))
            ->assertOk()
            ->assertDontSee('Danger Zone');
    }

    public function test_owner_can_soft_delete_room_and_messages_are_preserved(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        [$listedUser, $listedCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $message = $this->createMessage($room, $ownerUser, $ownerCharacter, 'Persist after delete.');

        $this->addModerator($room, $moderatorCharacter);
        $this->addWhitelist($room, $listedCharacter, $ownerCharacter);
        $this->addBlacklist($room, $moderatorCharacter, $ownerCharacter);

        DB::table('character_presences')->insert([
            'character_id' => $ownerCharacter->id,
            'room_id' => $room->id,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('room_user_presence')->insert([
            'room_id' => $room->id,
            'user_id' => $ownerUser->id,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->delete(route('rooms.destroy', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
                'delete_confirmation' => 'DELETE',
            ])
            ->assertRedirect(route('rooms.recovery'));

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'room_id' => $room->id,
            'body' => 'Persist after delete.',
        ]);
        $this->assertDatabaseHas('room_character_roles', [
            'room_id' => $room->id,
            'character_id' => $moderatorCharacter->id,
            'role' => RoomCharacterRole::ROLE_MODERATOR,
        ]);
        $this->assertDatabaseHas('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $listedCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
        ]);
        $this->assertDatabaseHas('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $moderatorCharacter->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
        ]);
        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $room->id,
        ]);
        $this->assertDatabaseMissing('room_user_presence', [
            'room_id' => $room->id,
        ]);
    }


    public function test_deleting_a_room_redirects_to_another_accessible_chat_room_when_available(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $deletedRoom = $this->createRoom($ownerUser, $ownerCharacter, 'Delete Me');
        $fallbackRoom = $this->createRoom($ownerUser, $ownerCharacter, 'Fallback Room');

        DB::table('character_presences')->insert([
            'character_id' => $ownerCharacter->id,
            'room_id' => $deletedRoom->id,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->delete(route('rooms.destroy', $deletedRoom->slug), [
                'character_id' => $ownerCharacter->id,
                'delete_confirmation' => 'DELETE',
            ])
            ->assertRedirect(route('rooms.show', $fallbackRoom->slug));
    }

    public function test_deleted_room_becomes_inaccessible_across_listing_show_presence_messages_websocket_and_unread_counts(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->createMessage($room, $ownerUser, $ownerCharacter, 'Hello.');

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->delete(route('rooms.destroy', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'delete_confirmation' => 'DELETE',
            ])
            ->assertRedirect(route('rooms.recovery'));

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertDontSee($room->name);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertNotFound();

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.presence', $room->slug), [
                'character_id' => $ownerCharacter->id,
            ])
            ->assertNotFound();

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'body' => 'Nope.',
            ])
            ->assertNotFound();

        $this->actingAs($ownerUser)
            ->getJson(route('rooms.messages.index', ['room' => $room->slug, 'character_id' => $ownerCharacter->id]))
            ->assertNotFound();

        $this->actingAs($viewerUser)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$room->id}",
            'character_id' => $viewerCharacter->id,
        ])->assertForbidden();

        $rooms = $this->actingAs($ownerUser)
            ->getJson(route('rooms.sidebar', ['character_id' => $ownerCharacter->id]))
            ->assertOk()
            ->json('rooms');

        $this->assertNull(collect($rooms)->firstWhere('id', $room->id));
    }

    public function test_moderator_ordinary_user_and_wrong_active_character_cannot_delete_room(): void
    {
        $ownerUser = User::factory()->create();
        $ownerCharacter = $this->createCharacter($ownerUser, 'Owner');
        $otherOwnedCharacter = $this->createCharacter($ownerUser, 'Other Owned');
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->addModerator($room, $moderatorCharacter);

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->deleteJson(route('rooms.destroy', $room->slug), [
                'character_id' => $moderatorCharacter->id,
                'delete_confirmation' => 'DELETE',
            ])
            ->assertForbidden();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->deleteJson(route('rooms.destroy', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'delete_confirmation' => 'DELETE',
            ])
            ->assertForbidden();

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $otherOwnedCharacter->id])
            ->deleteJson(route('rooms.destroy', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'delete_confirmation' => 'DELETE',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_override_can_delete_room_with_owned_active_character(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $admin = User::factory()->create(['is_admin' => true]);
        $adminCharacter = $this->createCharacter($admin);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($admin)
            ->withSession(['active_character_id' => $adminCharacter->id])
            ->delete(route('rooms.destroy', $room->slug), [
                'character_id' => $adminCharacter->id,
                'delete_confirmation' => 'DELETE',
            ])
            ->assertRedirect(route('rooms.recovery'));

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
    }

    public function test_delete_confirmation_must_exactly_match_delete(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        foreach (['delete', 'Delete', 'DELETE NOW', '', ' DELETE'] as $invalidConfirmation) {
            $this->actingAs($ownerUser)
                ->withSession(['active_character_id' => $ownerCharacter->id])
                ->deleteJson(route('rooms.destroy', $room->slug), [
                    'character_id' => $ownerCharacter->id,
                    'delete_confirmation' => $invalidConfirmation,
                ])
                ->assertStatus(422);
        }

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'deleted_at' => null,
        ]);
    }

    public function test_dm_rooms_cannot_be_deleted_and_do_not_render_danger_zone(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->actingAs($firstUser)
            ->get(route('dms.messages.index', $room->slug))
            ->assertOk()
            ->assertDontSee('Danger Zone');

        $this->actingAs($firstUser)
            ->deleteJson(route('rooms.destroy', $room->slug), [
                'character_id' => $firstCharacter->id,
                'delete_confirmation' => 'DELETE',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'deleted_at' => null,
        ]);
    }

    private function createUserWithCharacter(): array
    {
        $user = User::factory()->create();

        return [$user, $this->createCharacter($user)];
    }

    private function createCharacter(User $user, ?string $name = null): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name ?? 'Character ' . Str::random(8),
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createRoom(User $user, ?Character $ownerCharacter = null): Room
    {
        return Room::create([
            'name' => 'Room ' . Str::random(8),
            'slug' => 'room-' . Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $ownerCharacter?->id,
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
            'type' => Room::TYPE_DM,
            'visibility' => Room::VISIBILITY_PUBLIC,
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

    private function addModerator(Room $room, Character $character): void
    {
        RoomCharacterRole::create([
            'room_id' => $room->id,
            'character_id' => $character->id,
            'role' => RoomCharacterRole::ROLE_MODERATOR,
        ]);
    }

    private function addWhitelist(Room $room, Character $targetCharacter, Character $createdBy): void
    {
        RoomAccessEntry::create([
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
            'created_by_character_id' => $createdBy->id,
        ]);
    }

    private function addBlacklist(Room $room, Character $targetCharacter, Character $createdBy): void
    {
        RoomAccessEntry::create([
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
            'created_by_character_id' => $createdBy->id,
        ]);
    }
}
