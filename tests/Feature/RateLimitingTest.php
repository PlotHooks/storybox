<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomParticipationStateService;
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
        $token = $this->issueParticipationToken($room, $character);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $token,
                'body' => 'First.',
            ])->assertOk();

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $token,
                'body' => 'Second.',
            ])->assertOk();

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $token,
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
        $firstToken = $this->issueParticipationToken($room, $firstCharacter);
        $secondToken = $this->issueParticipationToken($room, $secondCharacter);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $firstCharacter->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $firstCharacter->id,
                'room_participation_token' => $firstToken,
                'body' => 'First.',
            ])->assertOk();

        $this->actingAs($user)
            ->withSession(['active_character_id' => $secondCharacter->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $secondCharacter->id,
                'room_participation_token' => $secondToken,
                'body' => 'Second.',
            ])->assertOk();

        $this->actingAs($user)
            ->withSession(['active_character_id' => $firstCharacter->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $firstCharacter->id,
                'room_participation_token' => $firstToken,
                'body' => 'Third.',
            ])
            ->assertTooManyRequests()
            ->assertJson([
                'message' => 'Slow down and try again in a moment.',
            ]);
    }


    private function issueParticipationToken(Room $room, Character $character): string
    {
        return app(RoomParticipationStateService::class)->issueToken($room, $character);
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
