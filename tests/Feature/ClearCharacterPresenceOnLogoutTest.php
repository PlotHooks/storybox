<?php

namespace Tests\Feature;

use App\Listeners\ClearCharacterPresenceOnLogout;
use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClearCharacterPresenceOnLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_listener_clears_all_character_presence_rows_for_the_users_characters(): void
    {
        $user = User::factory()->create();
        $firstCharacter = $this->createCharacter($user);
        $secondCharacter = $this->createCharacter($user);
        $otherUser = User::factory()->create();
        $otherCharacter = $this->createCharacter($otherUser);
        $firstRoom = $this->createRoom($otherUser);
        $secondRoom = $this->createRoom($otherUser);

        DB::table('character_presences')->insert([
            [
                'room_id' => $firstRoom->id,
                'character_id' => $firstCharacter->id,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => $secondRoom->id,
                'character_id' => $secondCharacter->id,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => $firstRoom->id,
                'character_id' => $otherCharacter->id,
                'last_seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(ClearCharacterPresenceOnLogout::class)->handle(new Logout('web', $user));

        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $firstRoom->id,
            'character_id' => $firstCharacter->id,
        ]);

        $this->assertDatabaseMissing('character_presences', [
            'room_id' => $secondRoom->id,
            'character_id' => $secondCharacter->id,
        ]);

        $this->assertDatabaseHas('character_presences', [
            'room_id' => $firstRoom->id,
            'character_id' => $otherCharacter->id,
        ]);
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
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
        ]);
    }
}
