<?php

namespace Tests\Feature;

use App\Events\MessageCreated;
use App\Events\ModerationMessageCreated;
use App\Events\RoomDisplayCleared;
use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomCharacterRole;
use App\Models\User;
use App\Services\ChatInputParser;
use App\Services\RoomParticipationStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomClearScreenCommandTest extends TestCase
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
            MessageCreated::class,
            ModerationMessageCreated::class,
            RoomDisplayCleared::class,
        ]);
    }

    public function test_parser_recognizes_cls_command(): void
    {
        $parsed = app(ChatInputParser::class)->parse('/cls');

        $this->assertSame('cls', $parsed['command']);
        $this->assertSame(Message::TYPE_NORMAL, $parsed['type']);
        $this->assertSame('', $parsed['body']);
    }

    public function test_room_owner_can_issue_cls_without_persisting_a_message(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        Message::create([
            'room_id' => $room->id,
            'user_id' => $ownerUser->id,
            'character_id' => $ownerCharacter->id,
            'type' => Message::TYPE_NORMAL,
            'body' => 'Existing message.',
        ]);

        $response = $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $ownerCharacter),
                'body' => '/cls',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('command', 'cls')
            ->assertJsonPath('room_id', $room->id);

        $this->assertSame(1, Message::query()->where('room_id', $room->id)->count());

        Event::assertDispatched(RoomDisplayCleared::class, function (RoomDisplayCleared $event) use ($room, $ownerCharacter) {
            $payload = $event->broadcastWith();

            return (int) $payload['room_id'] === (int) $room->id
                && (int) $payload['actor_character_id'] === (int) $ownerCharacter->id
                && $event->broadcastOn()->name === "private-conversation.{$room->id}";
        });

        Event::assertNotDispatched(MessageCreated::class);
    }

    public function test_room_moderator_can_issue_cls(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->addModerator($room, $moderatorCharacter);

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $moderatorCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $moderatorCharacter),
                'body' => '/cls',
            ])
            ->assertOk()
            ->assertJsonPath('command', 'cls');

        Event::assertDispatched(RoomDisplayCleared::class, 1);
    }

    public function test_admin_can_issue_cls(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $adminUser = User::factory()->create(['is_admin' => true]);
        $adminCharacter = $this->createCharacter($adminUser);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($adminUser)
            ->withSession(['active_character_id' => $adminCharacter->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $adminCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $adminCharacter),
                'body' => '/cls',
            ])
            ->assertOk()
            ->assertJsonPath('command', 'cls');

        Event::assertDispatched(RoomDisplayCleared::class, 1);
    }

    public function test_regular_room_member_cannot_issue_cls(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $viewerCharacter),
                'body' => '/cls',
            ])
            ->assertForbidden();

        $this->assertSame(0, Message::query()->where('room_id', $room->id)->count());
        Event::assertNotDispatched(RoomDisplayCleared::class);
    }

    public function test_cls_does_not_delete_existing_database_messages(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter();
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $firstMessage = Message::create([
            'room_id' => $room->id,
            'user_id' => $ownerUser->id,
            'character_id' => $ownerCharacter->id,
            'type' => Message::TYPE_NORMAL,
            'body' => 'First message.',
        ]);

        $secondMessage = Message::create([
            'room_id' => $room->id,
            'user_id' => $otherUser->id,
            'character_id' => $otherCharacter->id,
            'type' => Message::TYPE_NORMAL,
            'body' => 'Second message.',
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($room, $ownerCharacter),
                'body' => '/cls',
            ])
            ->assertOk();

        $this->assertDatabaseHas('messages', ['id' => $firstMessage->id, 'room_id' => $room->id]);
        $this->assertDatabaseHas('messages', ['id' => $secondMessage->id, 'room_id' => $room->id]);
        $this->assertSame(2, Message::query()->where('room_id', $room->id)->count());
    }

    public function test_cls_broadcast_is_only_for_the_target_room(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        $targetRoom = $this->createRoom($ownerUser, $ownerCharacter);
        $otherRoom = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.messages.store', $targetRoom->slug), [
                'character_id' => $ownerCharacter->id,
                'room_participation_token' => $this->issueParticipationToken($targetRoom, $ownerCharacter),
                'body' => '/cls',
            ])
            ->assertOk();

        Event::assertDispatched(RoomDisplayCleared::class, function (RoomDisplayCleared $event) use ($targetRoom) {
            return (int) $event->broadcastWith()['room_id'] === (int) $targetRoom->id;
        });

        Event::assertNotDispatched(RoomDisplayCleared::class, function (RoomDisplayCleared $event) use ($otherRoom) {
            return (int) $event->broadcastWith()['room_id'] === (int) $otherRoom->id;
        });
    }

    public function test_cls_is_rejected_in_dms(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->actingAs($firstUser)
            ->postJson(route('dms.messages.store', $room->slug), [
                'body' => '/cls',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.body.0', 'The /cls command is only available in rooms.');

        $this->assertSame(0, Message::query()->where('room_id', $room->id)->count());
        Event::assertNotDispatched(RoomDisplayCleared::class);
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

    private function createDmRoom(User $firstUser, Character $firstCharacter, User $secondUser, Character $secondCharacter): Room
    {
        $room = Room::create([
            'name' => 'DM',
            'slug' => 'dm-' . Str::random(16),
            'user_id' => $firstUser->id,
            'created_by' => $firstUser->id,
            'type' => Room::TYPE_DM,
            'visibility' => Room::VISIBILITY_PUBLIC,
        ]);

        DB::table('dm_participants')->insert([
            [
                'room_id' => $room->id,
                'user_id' => $firstUser->id,
                'character_id' => $firstCharacter->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => $room->id,
                'user_id' => $secondUser->id,
                'character_id' => $secondCharacter->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return $room;
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
