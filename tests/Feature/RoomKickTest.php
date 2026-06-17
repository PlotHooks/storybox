<?php

namespace Tests\Feature;

use App\Events\CharacterKickedFromRoom;
use App\Models\Character;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\RoomCharacterRole;
use App\Models\User;
use App\Services\RoomParticipationStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomKickTest extends TestCase
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
        ]);
    }

    public function test_owner_can_kick_from_room_without_mutating_other_moderation_state(): void
    {

        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        RoomCharacterRole::create([
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'role' => RoomCharacterRole::ROLE_MODERATOR,
        ]);

        DB::table('character_presences')->insert([
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.kick.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $targetCharacter->id,
                'reason' => 'Cooling off.',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
        ]);

        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
        ]);

        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
        ]);

        $this->assertDatabaseHas('room_character_roles', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'role' => RoomCharacterRole::ROLE_MODERATOR,
        ]);

        Event::assertDispatched(CharacterKickedFromRoom::class, function (CharacterKickedFromRoom $event) use ($room, $targetCharacter) {
            $payload = $event->broadcastWith();

            return (int) $payload['room_id'] === (int) $room->id
                && (int) $payload['target_character_id'] === (int) $targetCharacter->id
                && $payload['reason'] === 'Cooling off.';
        });
    }

    public function test_moderator_can_kick_regular_character_but_not_room_owner(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->addModerator($room, $moderatorCharacter);

        $this->actingAs($moderatorUser)
            ->postJson(route('rooms.kick.store', $room->slug), [
                'character_id' => $moderatorCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($moderatorUser)
            ->postJson(route('rooms.kick.store', $room->slug), [
                'character_id' => $moderatorCharacter->id,
                'target_character_id' => $ownerCharacter->id,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_kick_from_room_and_normal_user_cannot(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $adminUser = User::factory()->create(['is_admin' => true]);
        $adminCharacter = $this->createCharacter($adminUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($adminUser)
            ->postJson(route('rooms.kick.store', $room->slug), [
                'character_id' => $adminCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($viewerUser)
            ->postJson(route('rooms.kick.store', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertForbidden();
    }

    public function test_kicked_character_cannot_continue_participating_with_stale_state_but_can_rejoin_later(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $staleToken = $this->issueParticipationToken($room, $targetCharacter);

        DB::table('character_presences')->insert([
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.kick.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk();

        $this->actingAs($targetUser)
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $targetCharacter->id,
                'room_participation_token' => $staleToken,
                'body' => 'Stale room post.',
            ])
            ->assertForbidden();

        $this->actingAs($targetUser)
            ->postJson(route('rooms.presence', $room->slug), [
                'character_id' => $targetCharacter->id,
                'room_participation_token' => $staleToken,
            ])
            ->assertForbidden();

        $freshToken = $this->issueParticipationToken($room, $targetCharacter);

        $this->actingAs($targetUser)
            ->postJson(route('rooms.presence', $room->slug), [
                'character_id' => $targetCharacter->id,
                'room_participation_token' => $freshToken,
            ])
            ->assertOk();

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
        ]);
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
