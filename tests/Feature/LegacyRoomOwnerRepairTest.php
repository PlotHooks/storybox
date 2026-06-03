<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyRoomOwnerRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_public_room_with_null_owner_does_not_expose_room_settings_to_ordinary_users(): void
    {
        [$roomOwnerUser] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($roomOwnerUser, null);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertDontSee('Room Settings');
    }

    public function test_admin_can_view_filament_room_owner_and_visibility_fields_for_legacy_room(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$roomOwnerUser] = $this->createUserWithCharacter();
        $room = $this->createRoom($roomOwnerUser, null, Room::VISIBILITY_HIDDEN);

        $this->actingAs($admin)
            ->get("/panopticon/rooms/{$room->id}/edit")
            ->assertOk()
            ->assertSee('Owner Character')
            ->assertSee('Visibility');

        $this->actingAs($admin)
            ->get("/panopticon/rooms/{$room->id}")
            ->assertOk()
            ->assertSee('No owner assigned');
    }

    public function test_repair_command_dry_run_reports_unowned_public_rooms(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user, null);

        $this->artisan('rooms:repair-owners', ['--dry-run' => true])
            ->expectsTable(
                ['Room ID', 'Room Name', 'Visibility', 'Possible Owner', 'Reason', 'Would Change'],
                [[
                    $room->id,
                    $room->name,
                    $room->visibility,
                    $character->public_handle,
                    'Creator user owns exactly one character.',
                    'yes',
                ]]
            )
            ->expectsOutputToContain('Dry run only.')
            ->assertSuccessful();

        $this->assertNull($room->fresh()->owner_character_id);
    }

    public function test_repair_command_does_not_affect_dms(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $dmRoom = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->artisan('rooms:repair-owners', ['--apply' => true])
            ->expectsOutputToContain('No public rooms with missing owners were found.')
            ->assertSuccessful();

        $this->assertNull($dmRoom->fresh()->owner_character_id);
    }

    public function test_assign_owner_command_rejects_raw_character_ids(): void
    {
        [$user] = $this->createUserWithCharacter();
        [, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($user, null);

        $this->artisan('rooms:assign-owner', [
            'room_id' => $room->id,
            'public_handle' => (string) $targetCharacter->id,
        ])
            ->expectsOutputToContain('Use the public handle format Name#ABCD, not a raw character id.')
            ->assertExitCode(1);

        $this->assertNull($room->fresh()->owner_character_id);
    }

    public function test_assign_owner_command_rejects_plain_names(): void
    {
        [$user] = $this->createUserWithCharacter();
        [, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($user, null);

        $this->artisan('rooms:assign-owner', [
            'room_id' => $room->id,
            'public_handle' => $targetCharacter->name,
        ])
            ->expectsOutputToContain('Use the full public handle format Name#ABCD.')
            ->assertExitCode(1);

        $this->assertNull($room->fresh()->owner_character_id);
    }

    public function test_assign_owner_command_accepts_public_handle_and_clears_access_entries_for_that_owner(): void
    {
        [$user] = $this->createUserWithCharacter();
        [, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($user, null);

        RoomAccessEntry::create([
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
        ]);
        RoomAccessEntry::create([
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
        ]);

        $this->artisan('rooms:assign-owner', [
            'room_id' => $room->id,
            'public_handle' => $targetCharacter->public_handle,
        ])
            ->expectsOutputToContain($targetCharacter->public_handle)
            ->assertSuccessful();

        $this->assertSame($targetCharacter->id, $room->fresh()->owner_character_id);
        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
        ]);
        $this->assertDatabaseMissing('room_access_entries', [
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_BLACKLIST,
        ]);
    }

    public function test_assigned_owner_can_see_room_settings_after_command_assignment(): void
    {
        [$roomCreatorUser] = $this->createUserWithCharacter();
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($roomCreatorUser, null);

        $this->artisan('rooms:assign-owner', [
            'room_id' => $room->id,
            'public_handle' => $targetCharacter->public_handle,
        ])->assertSuccessful();

        $this->actingAs($targetUser)
            ->withSession(['active_character_id' => $targetCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('Room Settings');
    }

    public function test_ambiguous_owner_inference_is_reported_but_not_auto_assigned(): void
    {
        $user = User::factory()->create();
        $firstCharacter = $this->createCharacter($user, 'Leaf');
        $secondCharacter = $this->createCharacter($user, 'Stone');
        $room = $this->createRoom($user, null);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $firstCharacter->id,
            'body' => 'First message',
        ]);
        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $secondCharacter->id,
            'body' => 'Second message',
        ]);

        $this->artisan('rooms:repair-owners', ['--apply' => true])
            ->expectsTable(
                ['Room ID', 'Room Name', 'Visibility', 'Possible Owner', 'Reason', 'Would Change'],
                [[
                    $room->id,
                    $room->name,
                    $room->visibility,
                    $firstCharacter->public_handle,
                    'Suggestion only: the earliest message was authored by this character, but early room authors are mixed. Manual review required.',
                    'no',
                ]]
            )
            ->expectsOutputToContain('Applied 0 owner repair(s).')
            ->assertSuccessful();

        $this->assertNull($room->fresh()->owner_character_id);
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

    private function createRoom(
        User $user,
        ?Character $ownerCharacter = null,
        string $visibility = Room::VISIBILITY_PUBLIC,
    ): Room {
        return Room::create([
            'name' => 'Legacy Room ' . Str::random(8),
            'slug' => 'legacy-room-' . Str::random(16),
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

        \DB::table('dm_participants')->insert([
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
}
