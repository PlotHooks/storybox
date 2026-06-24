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

class DiceMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_roll_without_argument_defaults_to_1d20(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Leaf');
        $room = $this->createPublicRoom($user);

        $response = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $this->issueParticipationToken($room, $character),
                'body' => '/roll',
            ])
            ->assertOk()
            ->assertJsonPath('type', Message::TYPE_DICE)
            ->assertJsonPath('body', fn (string $body): bool => str_starts_with($body, 'Leaf rolled 1d20: ['))
            ->assertJsonPath('structured_data.expression', '1d20')
            ->assertJsonPath('structured_data.dice_count', 1)
            ->assertJsonPath('structured_data.die_size', 20);

        $message = Message::firstOrFail();

        $this->assertSame(Message::TYPE_DICE, $message->type);
        $this->assertSame('Leaf rolled 1d20: [' . $message->structured_data['rolls'][0] . ']', $message->body);
        $this->assertSame('1d20', $message->structured_data['expression']);
        $this->assertCount(1, $message->structured_data['rolls']);
        $this->assertGreaterThanOrEqual(1, $message->structured_data['rolls'][0]);
        $this->assertLessThanOrEqual(20, $message->structured_data['rolls'][0]);
    }

    public function test_roll_d20_is_normalized_to_1d20(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Leaf');
        $room = $this->createPublicRoom($user);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $this->issueParticipationToken($room, $character),
                'body' => '/roll d20',
            ])
            ->assertOk()
            ->assertJsonPath('type', Message::TYPE_DICE)
            ->assertJsonPath('body', fn (string $body): bool => str_starts_with($body, 'Leaf rolled 1d20: ['))
            ->assertJsonPath('structured_data.expression', '1d20');
    }

    public function test_roll_supports_standard_expressions(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Leaf');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Mina');
        $room = $this->createDmRoom($user, $character, $otherUser, $otherCharacter);

        foreach (['/roll 1d20', '/roll 4d6', '/roll 2d8+3', '/roll 2d8-3'] as $command) {
            $response = $this->actingAs($user)
                ->postJson(route('dms.messages.store', $room->slug), [
                    'body' => $command,
                ])
                ->assertOk();

            $normalized = trim(substr($command, strlen('/roll')));

            $response->assertJsonPath('message.type', Message::TYPE_DICE)
                ->assertJsonPath('message.body', fn (string $body): bool => str_starts_with($body, 'Leaf rolled ' . $normalized . ':'))
                ->assertJsonPath('message.structured_data.expression', $normalized);
        }
    }

    public function test_invalid_roll_expressions_get_friendly_validation_messages(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Leaf');
        $room = $this->createPublicRoom($user);

        $cases = [
            '/roll nope' => 'Enter a valid roll like /roll 2d8+3.',
            '/roll 0d6' => 'Roll at least one die.',
            '/roll 2d0' => 'Dice must have at least one side.',
        ];

        foreach ($cases as $command => $message) {
            $this->actingAs($user)
                ->withSession(['active_character_id' => $character->id])
                ->postJson(route('rooms.messages.store', $room->slug), [
                    'character_id' => $character->id,
                    'room_participation_token' => $this->issueParticipationToken($room, $character),
                    'body' => $command,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['body'])
                ->assertJsonPath('errors.body.0', $message);
        }
    }

    public function test_roll_validation_limits_are_enforced(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Leaf');
        $room = $this->createPublicRoom($user);

        $cases = [
            '/roll 101d6' => 'You can roll up to 100 dice at once.',
            '/roll 1d1001' => 'Dice can have up to 1000 sides.',
            '/roll ' . str_repeat('1', 51) => 'Roll expressions must be 50 characters or fewer.',
        ];

        foreach ($cases as $command => $message) {
            $this->actingAs($user)
                ->withSession(['active_character_id' => $character->id])
                ->postJson(route('rooms.messages.store', $room->slug), [
                    'character_id' => $character->id,
                    'room_participation_token' => $this->issueParticipationToken($room, $character),
                    'body' => $command,
                ])
                ->assertStatus(422)
                ->assertJsonPath('errors.body.0', $message);
        }
    }

    public function test_dice_messages_cannot_be_edited(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Leaf');
        $room = $this->createPublicRoom($user);
        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'type' => Message::TYPE_DICE,
            'body' => 'Leaf rolled 2d8+3: [5] [7] +3 = 15',
            'structured_data' => [
                'expression' => '2d8+3',
                'dice_count' => 2,
                'die_size' => 8,
                'modifier' => 3,
                'rolls' => [5, 7],
                'total' => 15,
            ],
        ]);

        $this->actingAs($user)
            ->patchJson(route('messages.update', $message), [
                'body' => '/roll 1d20',
            ])
            ->assertForbidden();
    }

    public function test_dice_messages_cannot_be_deleted_through_normal_user_controls(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Leaf');
        $room = $this->createPublicRoom($user);
        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'type' => Message::TYPE_DICE,
            'body' => 'Leaf rolled 2d8+3: [5] [7] +3 = 15',
            'structured_data' => [
                'expression' => '2d8+3',
                'dice_count' => 2,
                'die_size' => 8,
                'modifier' => 3,
                'rolls' => [5, 7],
                'total' => 15,
            ],
        ]);

        $this->actingAs($user)
            ->deleteJson(route('messages.delete', $message))
            ->assertForbidden();

        $this->assertFalse($message->fresh()->trashed());
    }

    public function test_dice_messages_are_reportable(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Leaf');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Mina');
        $room = $this->createPublicRoom($otherUser);

        $message = Message::create([
            'room_id' => $room->id,
            'user_id' => $otherUser->id,
            'character_id' => $otherCharacter->id,
            'type' => Message::TYPE_DICE,
            'body' => 'Mina rolled 4d6: [3] [4] [6] [1] = 14',
            'structured_data' => [
                'expression' => '4d6',
                'dice_count' => 4,
                'die_size' => 6,
                'modifier' => 0,
                'rolls' => [3, 4, 6, 1],
                'total' => 14,
            ],
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('messages.report', $message), [
                'reason' => 'Testing dice reports.',
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true);
    }

    public function test_dice_messages_render_consistently_in_rooms_and_dms(): void
    {
        [$roomUser, $roomCharacter] = $this->createUserWithCharacter('Leaf');
        $room = $this->createPublicRoom($roomUser);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $roomUser->id,
            'character_id' => $roomCharacter->id,
            'type' => Message::TYPE_DICE,
            'body' => 'Leaf rolled 2d8+3: [5] [7] +3 = 15',
            'structured_data' => [
                'expression' => '2d8+3',
                'dice_count' => 2,
                'die_size' => 8,
                'modifier' => 3,
                'rolls' => [5, 7],
                'total' => 15,
            ],
        ]);

        $roomContent = $this->actingAs($roomUser)
            ->withSession(['active_character_id' => $roomCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-message-type="dice"', $roomContent);
        $this->assertStringContainsString('data-can-edit="0"', $roomContent);
        $this->assertStringContainsString('>Leaf</span>&nbsp;<span class="msg-body', preg_replace('/\s+/', ' ', $roomContent));
        $this->assertStringContainsString('rolled 2d8+3: [5] [7] +3 = 15', $roomContent);

        [$dmUser, $dmCharacter] = $this->createUserWithCharacter('Leaf');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Mina');
        $dm = $this->createDmRoom($dmUser, $dmCharacter, $otherUser, $otherCharacter);

        Message::create([
            'room_id' => $dm->id,
            'user_id' => $dmUser->id,
            'character_id' => $dmCharacter->id,
            'type' => Message::TYPE_DICE,
            'body' => 'Leaf rolled 4d6: [3] [4] [6] [1] = 14',
            'structured_data' => [
                'expression' => '4d6',
                'dice_count' => 4,
                'die_size' => 6,
                'modifier' => 0,
                'rolls' => [3, 4, 6, 1],
                'total' => 14,
            ],
        ]);

        $this->actingAs($dmUser)
            ->getJson(route('dms.messages.index', $dm->slug))
            ->assertOk()
            ->assertJsonPath('messages.0.type', Message::TYPE_DICE)
            ->assertJsonPath('messages.0.rendered_body_html', 'rolled 4d6: [3] [4] [6] [1] = 14')
            ->assertJsonPath('messages.0.structured_data.expression', '4d6');
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
