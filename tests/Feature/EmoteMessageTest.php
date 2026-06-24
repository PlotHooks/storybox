<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomParticipationStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmoteMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_me_command_creates_an_emote_message(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $this->issueParticipationToken($room, $character),
                'body' => '/me   waves',
            ])
            ->assertOk()
            ->assertJsonPath('type', Message::TYPE_EMOTE)
            ->assertJsonPath('body', 'waves');

        $this->assertDatabaseHas('messages', [
            'room_id' => $room->id,
            'character_id' => $character->id,
            'type' => Message::TYPE_EMOTE,
            'body' => 'waves',
        ]);
    }

    public function test_dm_me_command_creates_an_emote_message(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter('Victor');
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter('Mina');
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->actingAs($firstUser)
            ->postJson(route('dms.messages.store', $room->slug), [
                'body' => '/me leans against the doorway.',
            ])
            ->assertOk()
            ->assertJsonPath('message.type', Message::TYPE_EMOTE)
            ->assertJsonPath('message.body', 'leans against the doorway.');

        $this->assertDatabaseHas('messages', [
            'room_id' => $room->id,
            'character_id' => $firstCharacter->id,
            'type' => Message::TYPE_EMOTE,
            'body' => 'leans against the doorway.',
        ]);
    }

    public function test_emote_renders_as_character_name_followed_by_action_text(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'type' => Message::TYPE_EMOTE,
            'body' => 'waves.',
        ]);

        $content = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->getContent();

        $normalized = preg_replace('/\s+/', ' ', $content);

        $this->assertStringContainsString('data-message-type="emote"', $content);
        $this->assertStringContainsString('>Victor</button> <span class="msg-body', $normalized);
        $this->assertStringContainsString('>waves.</span>', $normalized);
        $this->assertStringNotContainsString('Victor: waves.', $content);
        $this->assertStringNotContainsString('*Victor waves.*', $content);
    }

    public function test_normal_messages_still_render_unchanged(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'type' => Message::TYPE_NORMAL,
            'body' => 'Hello there.',
        ]);

        $content = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-message-type="normal"', $content);
        $this->assertStringContainsString('msg-name text-base font-bold leading-none', $content);
        $this->assertStringContainsString('>Hello there.</span>', $content);
    }

    public function test_empty_me_command_is_rejected_with_a_friendly_validation_error(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Victor');
        $room = $this->createPublicRoom($user);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $this->issueParticipationToken($room, $character),
                'body' => '/me   ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body'])
            ->assertJsonPath('errors.body.0', 'Enter an action after /me.');
    }

    private function createUserWithCharacter(string $characterName): array
    {
        $user = User::factory()->create([
            'name' => 'user_' . Str::random(8),
        ]);

        return [$user, $this->createCharacter($user, $characterName)];
    }

    private function createCharacter(User $user, string $name): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createPublicRoom(User $user): Room
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

    private function createDmRoom(User $firstUser, Character $firstCharacter, User $secondUser, Character $secondCharacter): Room
    {
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

    private function issueParticipationToken(Room $room, Character $character): string
    {
        return app(RoomParticipationStateService::class)->issueToken($room, $character);
    }
}
