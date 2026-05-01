<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_room_message_sending_is_limited_per_character(): void
    {
        config([
            'rate_limits.chat_message_character_max' => 2,
            'rate_limits.chat_message_character_decay' => 10,
            'rate_limits.chat_message_user_max' => 20,
        ]);

        [$user, $character, $room] = $this->createUserCharacterAndRoom();

        $this->actingAs($user)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $character->id,
            'body' => 'First.',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $character->id,
            'body' => 'Second.',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $character->id,
            'body' => 'Third.',
        ])
            ->assertTooManyRequests()
            ->assertJson([
                'message' => 'Slow down and try again in a moment.',
            ])
            ->assertJsonStructure(['retry_after']);
    }

    public function test_message_sending_is_limited_per_user_across_characters(): void
    {
        config([
            'rate_limits.chat_message_character_max' => 20,
            'rate_limits.chat_message_user_max' => 2,
            'rate_limits.chat_message_user_decay' => 60,
        ]);

        $user = User::factory()->create();
        $firstCharacter = $this->createCharacter($user);
        $secondCharacter = $this->createCharacter($user);
        $room = $this->createRoom($user);

        $this->actingAs($user)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $firstCharacter->id,
            'body' => 'First.',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $secondCharacter->id,
            'body' => 'Second.',
        ])->assertOk();

        $this->actingAs($user)->postJson(route('rooms.messages.store', $room->slug), [
            'character_id' => $firstCharacter->id,
            'body' => 'Third.',
        ])
            ->assertTooManyRequests()
            ->assertJson([
                'message' => 'Slow down and try again in a moment.',
            ]);
    }

    private function createUserCharacterAndRoom(): array
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $room = $this->createRoom($user);

        return [$user, $character, $room];
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
}
