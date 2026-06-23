<?php

namespace Tests\Feature;

use App\Events\MessageCreated;
use App\Events\ModerationMessageCreated;
use App\Models\Character;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\RoomCharacterRole;
use App\Models\User;
use App\Services\RoomParticipationStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomAccessControlTest extends TestCase
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

        Event::fake([
            \App\Events\CharacterKickedFromRoom::class,
            MessageCreated::class,
            ModerationMessageCreated::class,
        ]);

        Broadcast::purge('reverb');

        require base_path('routes/channels.php');
    }

    public function test_public_room_appears_in_normal_listing(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user, $character);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee($room->name);
    }

    public function test_hidden_room_is_not_listed_or_viewable_without_access(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter, visibility: Room::VISIBILITY_HIDDEN);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertDontSee($room->name);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertForbidden();

        $this->actingAs($viewerUser)
            ->postJson(route('rooms.presence', $room->slug), [
                'character_id' => $viewerCharacter->id,
            ])
            ->assertForbidden();

        $this->actingAs($viewerUser)
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'body' => 'Denied.',
            ])
            ->assertForbidden();
    }


    public function test_room_message_post_without_character_returns_structured_missing_character_response(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->postJson(route('rooms.messages.store', $room->slug), [
                'body' => 'Hello?',
            ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'You need to create and select a character before posting in chat.',
                'code' => 'missing_character',
            ]);
    }

    public function test_public_room_message_post_with_valid_character_still_succeeds(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user, $character);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $this->issueParticipationToken($room, $character),
                'body' => 'Legitimate room post.',
            ])
            ->assertOk()
            ->assertJsonPath('body', 'Legitimate room post.');
    }

    public function test_hidden_room_is_accessible_to_whitelisted_character_and_revoked_when_removed(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter, visibility: Room::VISIBILITY_HIDDEN);

        $this->whitelist($room, $viewerCharacter, $ownerCharacter);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee($room->name);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();

        $this->actingAs($viewerUser)
            ->postJson(route('rooms.presence', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $viewerCharacter),
            ])
            ->assertOk();

        $this->actingAs($viewerUser)
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $viewerCharacter),
                'body' => 'Allowed.',
            ])
            ->assertOk();

        $this->actingAs($ownerUser)
            ->deleteJson(route('rooms.whitelist.destroy', [$room->slug, $viewerCharacter]), [
                'character_id' => $ownerCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertForbidden();
    }

    public function test_hidden_room_is_accessible_to_owner_moderator_and_admin(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        $admin = User::factory()->create(['is_admin' => true]);
        $adminCharacter = $this->createCharacter($admin);
        $room = $this->createRoom($ownerUser, $ownerCharacter, visibility: Room::VISIBILITY_HIDDEN);

        $this->addModerator($room, $moderatorCharacter);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['active_character_id' => $adminCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();
    }

    public function test_owner_and_moderator_see_room_settings_but_ordinary_character_and_dm_view_do_not(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $dmRoom = $this->createDmRoom($ownerUser, $ownerCharacter, $otherUser, $otherCharacter);

        $this->addModerator($room, $moderatorCharacter);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('Follow Room')
            ->assertSee('data-context-tool="settings"', false);

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('Follow Room')
            ->assertSee('data-context-tool="settings"', false);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('Follow Room')
            ->assertDontSee('data-context-tool="settings"', false);

        $this->actingAs($ownerUser)
            ->get(route('dms.messages.index', $dmRoom->slug))
            ->assertOk()
            ->assertDontSee('Follow Room')
            ->assertDontSee('Room Settings');
    }

    public function test_blacklisted_character_cannot_access_public_or_hidden_room_and_blacklist_beats_whitelist(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $publicRoom = $this->createRoom($ownerUser, $ownerCharacter);
        $hiddenRoom = $this->createRoom($ownerUser, $ownerCharacter, visibility: Room::VISIBILITY_HIDDEN, suffix: 'hidden');

        $this->whitelist($publicRoom, $viewerCharacter, $ownerCharacter);
        $this->whitelist($hiddenRoom, $viewerCharacter, $ownerCharacter);
        $this->blacklist($publicRoom, $viewerCharacter, $ownerCharacter);
        $this->blacklist($hiddenRoom, $viewerCharacter, $ownerCharacter);

        foreach ([$publicRoom, $hiddenRoom] as $room) {
            $this->actingAs($viewerUser)
                ->withSession(['active_character_id' => $viewerCharacter->id])
                ->get(route('rooms.show', $room->slug))
                ->assertForbidden();

            $this->actingAs($viewerUser)
                ->postJson(route('rooms.messages.store', $room->slug), [
                    'character_id' => $viewerCharacter->id,
                    'body' => 'Blocked.',
                ])
                ->assertForbidden();

            $this->actingAs($viewerUser)->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-conversation.{$room->id}",
                'character_id' => $viewerCharacter->id,
            ])->assertForbidden();
        }
    }

    public function test_admin_override_works_for_hidden_and_blacklisted_rooms(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $admin = User::factory()->create(['is_admin' => true]);
        $adminCharacter = $this->createCharacter($admin);
        $room = $this->createRoom($ownerUser, $ownerCharacter, visibility: Room::VISIBILITY_HIDDEN);

        $this->blacklist($room, $adminCharacter, $ownerCharacter);

        $this->actingAs($admin)
            ->withSession(['active_character_id' => $adminCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $adminCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $adminCharacter),
                'body' => 'Admin override.',
            ])
            ->assertOk();

        $this->actingAs($admin)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$room->id}",
            'character_id' => $adminCharacter->id,
        ])->assertOk();
    }

    public function test_owner_can_manage_room_settings_access_lists_and_moderators(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->patchJson(route('rooms.update', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'name' => 'Updated Room',
                'description' => 'Updated description',
                'visibility' => Room::VISIBILITY_HIDDEN,
            ])
            ->assertOk();

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.whitelist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.blacklist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($ownerUser)
            ->deleteJson(route('rooms.blacklist.destroy', [$room->slug, $targetCharacter]), [
                'character_id' => $ownerCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.moderators.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($ownerUser)
            ->deleteJson(route('rooms.moderators.destroy', [$room->slug, $targetCharacter]), [
                'character_id' => $ownerCharacter->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Updated Room',
            'visibility' => Room::VISIBILITY_HIDDEN,
        ]);
    }

    public function test_moderator_can_manage_whitelist_and_blacklist_but_not_owner_or_moderator_roles(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->addModerator($room, $moderatorCharacter);

        $this->actingAs($moderatorUser)
            ->postJson(route('rooms.whitelist.store', $room->slug), [
                'character_id' => $moderatorCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($moderatorUser)
            ->postJson(route('rooms.blacklist.store', $room->slug), [
                'character_id' => $moderatorCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($moderatorUser)
            ->postJson(route('rooms.moderators.store', $room->slug), [
                'character_id' => $moderatorCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertForbidden();

        $this->actingAs($moderatorUser)
            ->postJson(route('rooms.blacklist.store', $room->slug), [
                'character_id' => $moderatorCharacter->id,
                'target_character_id' => $ownerCharacter->id,
            ])
            ->assertForbidden();
    }

    public function test_unauthorized_character_cannot_manage_room_access(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($viewerUser)
            ->postJson(route('rooms.whitelist.store', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertForbidden();
    }

    public function test_room_settings_forms_resolve_characters_by_full_public_handle(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $response = $this->actingAs($ownerUser)
            ->post(route('rooms.whitelist.store', ['room' => $room->slug, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
                'target_character_handle' => $targetCharacter->public_handle,
            ]);

        $response->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));

        $this->assertDatabaseHas('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
        ]);
    }

    public function test_room_settings_page_renders_added_and_removed_whitelist_entries(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->post(route('rooms.whitelist.store', ['room' => $room->slug, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
                'target_character_handle' => $targetCharacter->public_handle,
            ])
            ->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertSee($targetCharacter->public_handle);

        $this->actingAs($ownerUser)
            ->delete(route('rooms.whitelist.destroy', ['room' => $room->slug, 'character' => $targetCharacter, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
            ])
            ->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertDontSee($targetCharacter->public_handle);
    }

    public function test_room_settings_page_renders_added_and_removed_blacklist_entries(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->post(route('rooms.blacklist.store', ['room' => $room->slug, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
                'target_character_handle' => $targetCharacter->public_handle,
            ])
            ->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertSee($targetCharacter->public_handle);

        $this->actingAs($ownerUser)
            ->delete(route('rooms.blacklist.destroy', ['room' => $room->slug, 'character' => $targetCharacter, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
            ])
            ->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertDontSee($targetCharacter->public_handle);
    }

    public function test_room_settings_page_renders_added_and_removed_moderators(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->post(route('rooms.moderators.store', ['room' => $room->slug, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
                'target_character_handle' => $targetCharacter->public_handle,
            ])
            ->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertSee($targetCharacter->public_handle);

        $this->actingAs($ownerUser)
            ->delete(route('rooms.moderators.destroy', ['room' => $room->slug, 'character' => $targetCharacter, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
            ])
            ->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertDontSee($targetCharacter->public_handle);
    }

    public function test_duplicate_names_do_not_resolve_ambiguously_when_using_public_handles(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $firstTarget = $this->createNamedCharacter('Leaf');
        $secondTarget = $this->createNamedCharacter('Leaf');

        $this->assertNotSame($firstTarget->public_handle, $secondTarget->public_handle);

        $this->actingAs($ownerUser)
            ->post(route('rooms.whitelist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_handle' => $firstTarget->public_handle,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $firstTarget->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
        ]);

        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $secondTarget->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
        ]);
    }

    public function test_raw_character_ids_are_rejected_from_user_facing_room_management_forms(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $response = $this->from(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->actingAs($ownerUser)
            ->post(route('rooms.whitelist.store', ['room' => $room->slug, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
                'target_character_handle' => (string) $targetCharacter->id,
            ]);

        $response->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));
        $response->assertSessionHasErrors('target_character_handle');

        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
        ]);
    }

    public function test_room_settings_redirects_and_validation_errors_preserve_selected_tab(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->patch(route('rooms.update', ['room' => $room->slug, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
                'name' => 'Updated Room Name',
                'description' => 'Updated description',
                'visibility' => Room::VISIBILITY_HIDDEN,
            ])
            ->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));

        $errorResponse = $this->from(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->actingAs($ownerUser)
            ->post(route('rooms.whitelist.store', ['room' => $room->slug, 'tool' => 'settings']), [
                'character_id' => $ownerCharacter->id,
                'context_tool' => 'settings',
                'target_character_handle' => 'not-a-real-handle',
            ]);

        $errorResponse->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));
        $errorResponse->assertSessionHasErrors('target_character_handle');

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->assertOk()
            ->assertSee('Room Settings')
            ->assertSee('Whitelist entries grant access to hidden rooms.')
            ->assertSee('Room bans deny access even to public rooms.');
    }

    public function test_websocket_room_access_enforces_hidden_whitelist_blacklist_and_public_defaults(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $publicRoom = $this->createRoom($ownerUser, $ownerCharacter);
        $hiddenRoom = $this->createRoom($ownerUser, $ownerCharacter, visibility: Room::VISIBILITY_HIDDEN, suffix: 'private');

        $this->actingAs($viewerUser)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$hiddenRoom->id}",
            'character_id' => $viewerCharacter->id,
        ])->assertForbidden();

        $this->whitelist($hiddenRoom, $viewerCharacter, $ownerCharacter);

        $this->actingAs($viewerUser)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$hiddenRoom->id}",
            'character_id' => $viewerCharacter->id,
        ])->assertOk();

        $this->blacklist($hiddenRoom, $viewerCharacter, $ownerCharacter);

        $this->actingAs($viewerUser)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$hiddenRoom->id}",
            'character_id' => $viewerCharacter->id,
        ])->assertForbidden();

        $this->actingAs($viewerUser)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$publicRoom->id}",
            'character_id' => $viewerCharacter->id,
        ])->assertOk();
    }

    private function createUserWithCharacter(): array
    {
        $user = User::factory()->create();

        return [$user, $this->createCharacter($user)];
    }

    private function issueParticipationToken(Room $room, Character $character): string
    {
        return app(RoomParticipationStateService::class)->issueToken($room, $character);
    }

    private function createCharacter(User $user): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => 'Character ' . Str::random(8),
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createNamedCharacter(string $name): Character
    {
        $user = User::factory()->create();

        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createRoom(
        User $user,
        ?Character $ownerCharacter = null,
        string $visibility = Room::VISIBILITY_PUBLIC,
        string $suffix = 'room',
    ): Room {
        return Room::create([
            'name' => ucfirst($suffix) . ' ' . Str::random(8),
            'slug' => $suffix . '-' . Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => $visibility,
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

    private function whitelist(Room $room, Character $targetCharacter, Character $createdBy): void
    {
        RoomAccessEntry::create([
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
            'created_by_character_id' => $createdBy->id,
        ]);
    }

    private function blacklist(Room $room, Character $targetCharacter, Character $createdBy): void
    {
        RoomAccessEntry::updateOrCreate(
            [
                'room_id' => $room->id,
                'character_id' => $targetCharacter->id,
                'type' => RoomAccessEntry::TYPE_BLACKLIST,
            ],
            [
                'created_by_character_id' => $createdBy->id,
            ],
        );
    }

    private function addModerator(Room $room, Character $character): void
    {
        RoomCharacterRole::create([
            'room_id' => $room->id,
            'character_id' => $character->id,
            'role' => RoomCharacterRole::ROLE_MODERATOR,
        ]);
    }
}
