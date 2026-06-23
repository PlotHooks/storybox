<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_their_own_recoverable_rooms(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Other');

        $ownedRoom = $this->createRoom($ownerUser, $ownerCharacter, [
            'name' => 'Owner Hidden Room',
            'visibility' => Room::VISIBILITY_HIDDEN,
        ]);
        $otherRoom = $this->createRoom($otherUser, $otherCharacter, [
            'name' => 'Other Recoverable Room',
        ]);

        $this->softDeleteRoom($ownedRoom, now()->subDays(10));
        $this->softDeleteRoom($otherRoom, now()->subDays(10));

        $this->actingAs($ownerUser)
            ->get(route('rooms.recovery'))
            ->assertOk()
            ->assertSee('Recoverable Rooms')
            ->assertSee('Owner Hidden Room')
            ->assertDontSee('Other Recoverable Room');
    }

    public function test_admin_sees_all_recoverable_rooms(): void
    {
        [$firstOwner, $firstCharacter] = $this->createUserWithCharacter('First');
        [$secondOwner, $secondCharacter] = $this->createUserWithCharacter('Second');
        [$adminUser] = $this->createUserWithCharacter('Admin');
        $adminUser->forceFill(['is_admin' => true])->save();

        $firstRoom = $this->createRoom($firstOwner, $firstCharacter, ['name' => 'First Recoverable']);
        $secondRoom = $this->createRoom($secondOwner, $secondCharacter, ['name' => 'Second Recoverable']);

        $this->softDeleteRoom($firstRoom, now()->subDays(14));
        $this->softDeleteRoom($secondRoom, now()->subDays(14));

        $this->actingAs($adminUser)
            ->get(route('rooms.recovery'))
            ->assertOk()
            ->assertSee('First Recoverable')
            ->assertSee('Second Recoverable');
    }

    public function test_non_owner_cannot_restore_recoverable_room(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$otherUser] = $this->createUserWithCharacter('Other');
        $room = $this->createRoom($ownerUser, $ownerCharacter, ['name' => 'Protected Room']);

        $this->softDeleteRoom($room, now()->subDays(5));

        $this->actingAs($otherUser)
            ->post(route('rooms.recoverable.restore', $room->id))
            ->assertForbidden();

        $this->assertNotNull(Room::withTrashed()->find($room->id)?->deleted_at);
    }

    public function test_owner_cannot_restore_room_after_recovery_window_expires(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter, ['name' => 'Expired Room']);

        $this->softDeleteRoom($room, now()->subDays(91));

        $this->actingAs($ownerUser)
            ->post(route('rooms.recoverable.restore', $room->id))
            ->assertNotFound();

        $this->assertNotNull(Room::withTrashed()->find($room->id)?->deleted_at);
    }

    public function test_owner_can_restore_recoverable_public_room(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter, [
            'name' => 'Restorable Hidden Room',
            'visibility' => Room::VISIBILITY_HIDDEN,
        ]);

        $this->softDeleteRoom($room, now()->subDays(3));

        $this->actingAs($ownerUser)
            ->post(route('rooms.recoverable.restore', $room->id))
            ->assertRedirect(route('rooms.recovery'));

        $this->assertNull(Room::withTrashed()->find($room->id)?->deleted_at);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();
    }


    public function test_nexus_rooms_tab_shows_recovery_entry_only_when_needed_or_admin(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Other');
        $adminUser = User::factory()->create(['is_admin' => true]);
        $adminCharacter = $this->createCharacter($adminUser, 'Admin');

        $activeRoom = $this->createRoom($ownerUser, $ownerCharacter, ['name' => 'Active Room']);
        $recoverableRoom = $this->createRoom($ownerUser, $ownerCharacter, ['name' => 'Recoverable Room']);
        $this->softDeleteRoom($recoverableRoom, now()->subDays(4));

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.show', $activeRoom->slug))
            ->assertOk()
            ->assertSee('Recoverable Rooms')
            ->assertSee('1');

        $otherActiveRoom = $this->createRoom($otherUser, $otherCharacter, ['name' => 'Other Active Room']);

        $this->actingAs($otherUser)
            ->withSession(['active_character_id' => $otherCharacter->id])
            ->get(route('rooms.show', $otherActiveRoom->slug))
            ->assertOk()
            ->assertDontSee('Recoverable Rooms');

        $this->actingAs($adminUser)
            ->withSession(['active_character_id' => $adminCharacter->id])
            ->get(route('rooms.show', $activeRoom->slug))
            ->assertOk()
            ->assertSee('Recoverable Rooms');
    }

    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create();

        return [$user, $this->createCharacter($user, $name)];
    }

    private function createCharacter(User $user, string $name): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createRoom(User $user, Character $ownerCharacter, array $overrides = []): Room
    {
        return Room::create(array_merge([
            'name' => 'Room ' . Str::random(8),
            'slug' => 'room-' . Str::random(16),
            'description' => 'Test room',
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $ownerCharacter->id,
        ], $overrides));
    }

    private function softDeleteRoom(Room $room, Carbon $deletedAt): void
    {
        $room->delete();
        $room->forceFill(['deleted_at' => $deletedAt])->saveQuietly();
    }
}
