<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterPresence;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomLandingRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_chat_landing(): void
    {
        $this->get('/')
            ->assertRedirect('/chat');
    }

    public function test_chat_landing_redirects_to_current_accessible_room(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, 'Alice');
        $currentRoom = $this->createRoom($user, $character, 'Current Room');

        CharacterPresence::create([
            'character_id' => $character->id,
            'room_id' => $currentRoom->id,
            'last_seen_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.landing'))
            ->assertRedirect(route('rooms.show', $currentRoom->slug));
    }

    public function test_chat_landing_redirects_to_first_accessible_public_room_when_no_current_room_exists(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, 'Alice');
        $firstRoom = $this->createRoom($user, $character, 'First Room');
        $this->createRoom($user, $character, 'Second Room');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.landing'))
            ->assertRedirect(route('rooms.show', $firstRoom->slug));
    }

    public function test_chat_landing_skips_inaccessible_current_room_and_falls_back_to_first_accessible_room(): void
    {
        [$owner, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewer, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $hiddenRoom = $this->createRoom($owner, $ownerCharacter, 'Hidden Room', Room::VISIBILITY_HIDDEN);
        $publicRoom = $this->createRoom($owner, $ownerCharacter, 'Public Room');

        CharacterPresence::create([
            'character_id' => $viewerCharacter->id,
            'room_id' => $hiddenRoom->id,
            'last_seen_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.landing'))
            ->assertRedirect(route('rooms.show', $publicRoom->slug));
    }

    public function test_chat_landing_falls_back_to_rooms_index_when_no_accessible_rooms_exist(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, 'Alice');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.landing'))
            ->assertRedirect(route('rooms.index'));
    }

    public function test_login_redirects_directly_to_current_accessible_room(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, 'Alice');
        $room = $this->createRoom($user, $character, 'Current Room');

        CharacterPresence::create([
            'character_id' => $character->id,
            'room_id' => $room->id,
            'last_seen_at' => now(),
        ]);

        $response = $this->withSession(['active_character_id' => $character->id])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('rooms.show', $room->slug, false));
    }

    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::random(6).'@example.com',
        ]);

        return [$user, $this->createCharacter($user, $name.' Character')];
    }

    private function createCharacter(User $user, string $name): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ]);
    }

    private function createRoom(User $user, Character $ownerCharacter, string $name, string $visibility = Room::VISIBILITY_PUBLIC): Room
    {
        return Room::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => null,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => $visibility,
            'owner_character_id' => $ownerCharacter->id,
        ]);
    }
}
