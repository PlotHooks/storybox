<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BroadcastChannelAuthorizationTest extends TestCase
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

    public function test_public_room_channel_requires_owned_character_in_room(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $room = $this->createRoom($user, 'public');

        $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$room->id}",
            'character_id' => $character->id,
        ])->assertForbidden();

        DB::table('character_presences')->insert([
            'room_id' => $room->id,
            'character_id' => $character->id,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$room->id}",
            'character_id' => $character->id,
        ])->assertOk();
    }

    public function test_dm_channel_requires_matching_participant_character(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $outsider = User::factory()->create();

        $ownerCharacter = $this->createCharacter($owner);
        $participantCharacter = $this->createCharacter($participant);
        $outsiderCharacter = $this->createCharacter($outsider);
        $room = $this->createRoom($owner, 'dm');

        DB::table('dm_participants')->insert([
            [
                'room_id' => $room->id,
                'user_id' => $owner->id,
                'character_id' => $ownerCharacter->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => $room->id,
                'user_id' => $participant->id,
                'character_id' => $participantCharacter->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($participant)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$room->id}",
            'character_id' => $participantCharacter->id,
        ])->assertOk();

        $this->actingAs($outsider)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$room->id}",
            'character_id' => $outsiderCharacter->id,
        ])->assertForbidden();

        $this->actingAs($participant)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$room->id}",
            'character_id' => $ownerCharacter->id,
        ])->assertForbidden();
    }

    private function createRoom(User $user, string $type): Room
    {
        return Room::create([
            'name' => $type === 'dm' ? 'DM' : 'Public Room',
            'slug' => $type . '-' . Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => $type,
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
}
