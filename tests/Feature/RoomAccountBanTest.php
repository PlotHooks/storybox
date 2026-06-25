<?php

namespace Tests\Feature;

use App\Events\CharacterKickedFromRoom;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomAccountBanTest extends TestCase
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
            CharacterKickedFromRoom::class,
            MessageCreated::class,
            ModerationMessageCreated::class,
        ]);

        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
    }

    public function test_character_ban_blocks_only_that_character(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $firstCharacter] = $this->createUserWithCharacter();
        $secondCharacter = $this->createCharacter($targetUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.blacklist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $firstCharacter->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $firstCharacter->id,
            'user_id' => null,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
            'scope' => RoomAccessEntry::SCOPE_CHARACTER,
        ]);

        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'user_id' => $targetUser->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
            'scope' => RoomAccessEntry::SCOPE_ACCOUNT,
        ]);

        $this->actingAs($targetUser)
            ->withSession(['active_character_id' => $firstCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertForbidden();

        $this->actingAs($targetUser)
            ->withSession(['active_character_id' => $secondCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();
    }

    public function test_account_ban_blocks_all_characters_owned_by_that_user_for_view_posting_and_websocket(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $firstCharacter] = $this->createUserWithCharacter();
        $secondCharacter = $this->createCharacter($targetUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $firstToken = $this->issueParticipationToken($room, $firstCharacter);
        $secondToken = $this->issueParticipationToken($room, $secondCharacter);

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.account-blacklist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $firstCharacter->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('room_access_entries', [
            'room_id' => $room->id,
            'user_id' => $targetUser->id,
            'character_id' => null,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
            'scope' => RoomAccessEntry::SCOPE_ACCOUNT,
        ]);

        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $firstCharacter->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
            'scope' => RoomAccessEntry::SCOPE_CHARACTER,
        ]);

        foreach ([[$firstCharacter, $firstToken], [$secondCharacter, $secondToken]] as [$character, $token]) {
            $this->actingAs($targetUser)
                ->withSession(['active_character_id' => $character->id])
                ->get(route('rooms.show', $room->slug))
                ->assertForbidden();

            $this->actingAs($targetUser)
                ->postJson(route('rooms.messages.store', $room->slug), [
                    'character_id' => $character->id,
                    'room_participation_token' => $token,
                    'body' => 'Blocked by account ban.',
                ])
                ->assertForbidden();

            $this->actingAs($targetUser)
                ->postJson('/broadcasting/auth', [
                    'socket_id' => '123.456',
                    'channel_name' => "private-conversation.{$room->id}",
                    'character_id' => $character->id,
                ])
                ->assertForbidden();
        }

        $this->assertFalse((bool) ($targetUser->fresh()->is_banned ?? false));
    }

    public function test_account_ban_ejects_all_active_room_characters_owned_by_that_user(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $firstCharacter] = $this->createUserWithCharacter();
        $secondCharacter = $this->createCharacter($targetUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        foreach ([$firstCharacter, $secondCharacter] as $character) {
            DB::table('character_presences')->insert([
                'room_id' => $room->id,
                'character_id' => $character->id,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.account-blacklist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $firstCharacter->id,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $room->id,
            'character_id' => $firstCharacter->id,
        ]);

        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $room->id,
            'character_id' => $secondCharacter->id,
        ]);

        Event::assertDispatched(CharacterKickedFromRoom::class, 2);
    }

    public function test_account_ban_moderation_state_does_not_reveal_account_identity(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.account-blacklist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $response = $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->getJson(route('rooms.moderation.characters.show', [$room->slug, $targetCharacter]))
            ->assertOk();

        $payload = $response->json();
        $target = $payload['target'] ?? [];

        $this->assertArrayNotHasKey('user_id', $target);
        $this->assertArrayNotHasKey('username', $target);
        $this->assertArrayNotHasKey('email', $target);
        $this->assertArrayNotHasKey('account_name', $target);
        $this->assertArrayNotHasKey('user', $payload);
        $this->assertTrue((bool) ($target['is_account_banned'] ?? false));
    }

    public function test_normal_user_cannot_ban_character_or_account(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($viewerUser)
            ->postJson(route('rooms.blacklist.store', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertForbidden();

        $this->actingAs($viewerUser)
            ->postJson(route('rooms.account-blacklist.store', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertForbidden();
    }

    public function test_moderator_cannot_ban_room_owner_account(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->addModerator($room, $moderatorCharacter);

        $this->actingAs($moderatorUser)
            ->postJson(route('rooms.account-blacklist.store', $room->slug), [
                'character_id' => $moderatorCharacter->id,
                'target_character_id' => $ownerCharacter->id,
            ])
            ->assertForbidden();
    }

    public function test_room_message_json_does_not_include_account_identity_for_non_admin_viewers(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $targetUser->forceFill([
            'name' => 'Hidden Account Name',
            'email' => 'hidden-account@example.test',
        ])->save();

        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $room->messages()->create([
            'user_id' => $targetUser->id,
            'character_id' => $targetCharacter->id,
            'body' => 'Existing message.',
        ]);

        $latestResponse = $this->actingAs($viewerUser)
            ->getJson(route('rooms.messages.index', ['room' => $room->slug, 'character_id' => $viewerCharacter->id]))
            ->assertOk();

        $latestPayload = $latestResponse->json();
        $this->assertIsArray($latestPayload);
        $this->assertNotEmpty($latestPayload);
        $latestMessage = $latestPayload[0];

        foreach (['user_id', 'user_name', 'username', 'email', 'account_name', 'user'] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $latestMessage);
        }

        $storeResponse = $this->actingAs($viewerUser)
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $viewerCharacter),
                'body' => 'Viewer post.',
            ])
            ->assertOk();

        $storedMessage = $storeResponse->json();

        foreach (['user_id', 'user_name', 'username', 'email', 'account_name', 'user'] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $storedMessage);
        }
    }

    public function test_room_roster_keeps_recently_heartbeating_characters_visible_without_post_activity(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        DB::table('character_presences')->insert([
            'room_id' => $room->id,
            'character_id' => $viewerCharacter->id,
            'last_seen_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.roster', $room->slug))
            ->assertOk()
            ->assertJsonPath('roster.0.character_id', $viewerCharacter->id);
    }

    public function test_room_roster_still_expires_stale_characters_when_presence_is_old(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        DB::table('character_presences')->insert([
            'room_id' => $room->id,
            'character_id' => $viewerCharacter->id,
            'last_seen_at' => now()->subMinutes(20),
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.roster', $room->slug))
            ->assertOk()
            ->assertJsonCount(0, 'roster');
    }


    public function test_presence_ping_refreshes_all_of_the_users_existing_presence_rows_in_the_room_and_adds_the_selected_character(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $firstCharacter] = $this->createUserWithCharacter();
        $secondCharacter = $this->createCharacter($viewerUser);
        $thirdCharacter = $this->createCharacter($viewerUser);
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $staleTime = now()->subMinutes(20);

        DB::table('character_presences')->insert([
            [
                'room_id' => $room->id,
                'character_id' => $firstCharacter->id,
                'last_seen_at' => $staleTime,
                'created_at' => $staleTime,
                'updated_at' => $staleTime,
            ],
            [
                'room_id' => $room->id,
                'character_id' => $secondCharacter->id,
                'last_seen_at' => $staleTime,
                'created_at' => $staleTime,
                'updated_at' => $staleTime,
            ],
            [
                'room_id' => $room->id,
                'character_id' => $otherCharacter->id,
                'last_seen_at' => $staleTime,
                'created_at' => $staleTime,
                'updated_at' => $staleTime,
            ],
        ]);

        $heartbeatTime = now();
        Carbon::setTestNow($heartbeatTime);

        try {
            $response = $this->actingAs($viewerUser)
                ->withSession(['active_character_id' => $firstCharacter->id])
                ->postJson(route('rooms.presence', $room->slug), [
                    'character_id' => $firstCharacter->id,
                    'room_participation_token' => $this->issueParticipationToken($room, $firstCharacter),
                ])
                ->assertOk();
        } finally {
            Carbon::setTestNow();
        }

        $response->assertJsonPath('refreshed_character_ids', [$firstCharacter->id, $secondCharacter->id]);

        foreach ([$firstCharacter->id, $secondCharacter->id] as $characterId) {
            $this->assertDatabaseHas('character_presences', [
                'room_id' => $room->id,
                'character_id' => $characterId,
                'last_seen_at' => $heartbeatTime->toDateTimeString(),
            ]);
        }

        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $room->id,
            'character_id' => $thirdCharacter->id,
        ]);

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $room->id,
            'character_id' => $otherCharacter->id,
            'last_seen_at' => $staleTime->toDateTimeString(),
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $firstCharacter->id])
            ->getJson(route('rooms.roster', $room->slug))
            ->assertOk()
            ->assertJsonCount(2, 'roster');
    }

    public function test_presence_ping_still_creates_a_single_presence_row_for_the_selected_character_when_none_exist_yet(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $otherOwnedCharacter = $this->createCharacter($viewerUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.presence', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $viewerCharacter),
            ])
            ->assertOk();

        $response->assertJsonPath('refreshed_character_ids', [$viewerCharacter->id]);

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $room->id,
            'character_id' => $viewerCharacter->id,
        ]);

        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $room->id,
            'character_id' => $otherOwnedCharacter->id,
        ]);
    }


    public function test_same_character_can_be_present_in_two_different_rooms_at_the_same_time(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $firstRoom = $this->createRoom($ownerUser, $ownerCharacter);
        $secondRoom = $this->createRoom($ownerUser, $ownerCharacter);

        $firstToken = $this->issueParticipationToken($firstRoom, $viewerCharacter);
        $secondToken = $this->issueParticipationToken($secondRoom, $viewerCharacter);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.presence', $firstRoom->slug), [
                'character_id' => $viewerCharacter->id,
                'room_participation_token' => $firstToken,
            ])
            ->assertOk();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.presence', $secondRoom->slug), [
                'character_id' => $viewerCharacter->id,
                'room_participation_token' => $secondToken,
            ])
            ->assertOk();

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $firstRoom->id,
            'character_id' => $viewerCharacter->id,
        ]);

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $secondRoom->id,
            'character_id' => $viewerCharacter->id,
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.roster', $firstRoom->slug))
            ->assertOk()
            ->assertJsonPath('roster.0.character_id', $viewerCharacter->id);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.roster', $secondRoom->slug))
            ->assertOk()
            ->assertJsonPath('roster.0.character_id', $viewerCharacter->id);
    }

    public function test_pinging_second_room_does_not_remove_same_character_from_first_room_roster(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $firstRoom = $this->createRoom($ownerUser, $ownerCharacter);
        $secondRoom = $this->createRoom($ownerUser, $ownerCharacter);

        DB::table('character_presences')->insert([
            'room_id' => $firstRoom->id,
            'character_id' => $viewerCharacter->id,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.presence', $secondRoom->slug), [
                'character_id' => $viewerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($secondRoom, $viewerCharacter),
            ])
            ->assertOk();

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $firstRoom->id,
            'character_id' => $viewerCharacter->id,
        ]);

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $secondRoom->id,
            'character_id' => $viewerCharacter->id,
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.roster', $firstRoom->slug))
            ->assertOk()
            ->assertJsonPath('roster.0.character_id', $viewerCharacter->id);
    }

    public function test_leaving_one_room_does_not_remove_same_character_from_other_room_presence(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $firstRoom = $this->createRoom($ownerUser, $ownerCharacter);
        $secondRoom = $this->createRoom($ownerUser, $ownerCharacter);

        foreach ([$firstRoom, $secondRoom] as $room) {
            DB::table('character_presences')->insert([
                'room_id' => $room->id,
                'character_id' => $viewerCharacter->id,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.leave', $firstRoom->slug), [
                'character_id' => $viewerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($firstRoom, $viewerCharacter),
            ])
            ->assertOk();

        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $firstRoom->id,
            'character_id' => $viewerCharacter->id,
        ]);

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $secondRoom->id,
            'character_id' => $viewerCharacter->id,
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.roster', $secondRoom->slug))
            ->assertOk()
            ->assertJsonPath('roster.0.character_id', $viewerCharacter->id);
    }

    public function test_room_roster_json_does_not_include_account_identity_for_non_admin_viewers(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $targetUser->forceFill([
            'name' => 'Roster Hidden Account',
            'email' => 'roster-hidden@example.test',
        ])->save();

        $room = $this->createRoom($ownerUser, $ownerCharacter);

        foreach ([$viewerCharacter, $targetCharacter] as $character) {
            DB::table('character_presences')->insert([
                'room_id' => $room->id,
                'character_id' => $character->id,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.roster', $room->slug))
            ->assertOk();

        $payload = $response->json('roster');
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);

        foreach ($payload as $entry) {
            foreach (['user_id', 'user_name', 'username', 'email', 'account_name', 'user'] as $forbiddenKey) {
                $this->assertArrayNotHasKey($forbiddenKey, $entry);
            }
        }

        $this->assertStringNotContainsString($targetUser->email, $response->getContent());
        $this->assertStringNotContainsString($targetUser->name, $response->getContent());
    }

    public function test_rendered_room_html_does_not_include_account_identifiers_for_non_admin_viewers(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $targetUser->forceFill([
            'name' => 'Blade Hidden Account',
            'email' => 'blade-hidden@example.test',
        ])->save();

        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $room->messages()->create([
            'user_id' => $targetUser->id,
            'character_id' => $targetCharacter->id,
            'body' => 'Blade privacy check.',
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertDontSee('data-user-id', false)
            ->assertDontSee($targetUser->email, false)
            ->assertDontSee($targetUser->name, false);
    }

    public function test_character_unban_does_not_remove_account_ban(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $otherCharacter = $this->createCharacter($targetUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)->postJson(route('rooms.blacklist.store', $room->slug), [
            'character_id' => $ownerCharacter->id,
            'target_character_id' => $targetCharacter->id,
        ])->assertOk();

        $this->actingAs($ownerUser)->postJson(route('rooms.account-blacklist.store', $room->slug), [
            'character_id' => $ownerCharacter->id,
            'target_character_id' => $targetCharacter->id,
        ])->assertOk();

        $this->actingAs($ownerUser)->deleteJson(route('rooms.blacklist.destroy', [$room->slug, $targetCharacter]), [
            'character_id' => $ownerCharacter->id,
        ])->assertOk();

        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
            'scope' => RoomAccessEntry::SCOPE_CHARACTER,
        ]);

        $this->assertDatabaseHas('room_access_entries', [
            'room_id' => $room->id,
            'user_id' => $targetUser->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
            'scope' => RoomAccessEntry::SCOPE_ACCOUNT,
        ]);

        $this->actingAs($targetUser)
            ->withSession(['active_character_id' => $targetCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertForbidden();

        $this->actingAs($targetUser)
            ->withSession(['active_character_id' => $otherCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertForbidden();
    }

    public function test_account_unban_does_not_remove_character_ban(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $otherCharacter = $this->createCharacter($targetUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)->postJson(route('rooms.blacklist.store', $room->slug), [
            'character_id' => $ownerCharacter->id,
            'target_character_id' => $targetCharacter->id,
        ])->assertOk();

        $this->actingAs($ownerUser)->postJson(route('rooms.account-blacklist.store', $room->slug), [
            'character_id' => $ownerCharacter->id,
            'target_character_id' => $targetCharacter->id,
        ])->assertOk();

        $this->actingAs($ownerUser)->deleteJson(route('rooms.account-blacklist.destroy', [$room->slug, $targetCharacter]), [
            'character_id' => $ownerCharacter->id,
        ])->assertOk();

        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'user_id' => $targetUser->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
            'scope' => RoomAccessEntry::SCOPE_ACCOUNT,
        ]);

        $this->assertDatabaseHas('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
            'scope' => RoomAccessEntry::SCOPE_CHARACTER,
        ]);

        $this->actingAs($targetUser)
            ->withSession(['active_character_id' => $targetCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertForbidden();

        $this->actingAs($targetUser)
            ->withSession(['active_character_id' => $otherCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();
    }

    public function test_character_ban_ejects_only_the_targeted_character(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $otherCharacter = $this->createCharacter($targetUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $otherToken = $this->issueParticipationToken($room, $otherCharacter);

        foreach ([$targetCharacter, $otherCharacter] as $character) {
            DB::table('character_presences')->insert([
                'room_id' => $room->id,
                'character_id' => $character->id,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Event::fake([CharacterKickedFromRoom::class]);

        $this->actingAs($ownerUser)->postJson(route('rooms.blacklist.store', $room->slug), [
            'character_id' => $ownerCharacter->id,
            'target_character_id' => $targetCharacter->id,
        ])->assertOk();

        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
        ]);

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $room->id,
            'character_id' => $otherCharacter->id,
        ]);

        $this->actingAs($targetUser)->postJson(route('rooms.presence', $room->slug), [
            'character_id' => $otherCharacter->id,
            'room_participation_token' => $otherToken,
        ])->assertOk();

        Event::assertDispatched(CharacterKickedFromRoom::class, 1);
        Event::assertDispatched(CharacterKickedFromRoom::class, function (CharacterKickedFromRoom $event) use ($targetCharacter) {
            return (int) $event->broadcastWith()['target_character_id'] === (int) $targetCharacter->id;
        });
    }

    public function test_admin_override_still_works_for_account_level_bans(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $adminUser = User::factory()->create(['is_admin' => true]);
        $adminCharacter = $this->createCharacter($adminUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)->postJson(route('rooms.account-blacklist.store', $room->slug), [
            'character_id' => $ownerCharacter->id,
            'target_character_id' => $adminCharacter->id,
        ])->assertOk();

        $freshToken = $this->issueParticipationToken($room, $adminCharacter);

        $this->actingAs($adminUser)
            ->withSession(['active_character_id' => $adminCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();

        $this->actingAs($adminUser)
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $adminCharacter->id,
                'room_participation_token' => $freshToken,
                'body' => 'Admin account-ban override.',
            ])
            ->assertOk();

        $this->actingAs($adminUser)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$room->id}",
            'character_id' => $adminCharacter->id,
        ])->assertOk();
    }

    private function issueParticipationToken(Room $room, Character $character): string
    {
        return app(RoomParticipationStateService::class)->issueToken($room, $character);
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

    private function addModerator(Room $room, Character $character): void
    {
        RoomCharacterRole::create([
            'room_id' => $room->id,
            'character_id' => $character->id,
            'role' => RoomCharacterRole::ROLE_MODERATOR,
        ]);
    }
}
