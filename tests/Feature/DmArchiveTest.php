<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DmArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_index_treats_existing_dms_as_active_when_archived_column_is_missing(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        Schema::table('dm_participants', function ($table) {
            $table->dropColumn('archived_at');
        });

        $rooms = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertCount(1, $rooms);
        $this->assertSame($room->slug, $rooms[0]['slug']);
        $this->assertNull($rooms[0]['archived_at']);
    }

    public function test_user_can_archive_a_dm_without_affecting_the_other_participant(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);
        $message = $this->createMessage($room, $secondUser, $secondCharacter, 'Keep this message.');

        $this->actingAs($firstUser)
            ->postJson(route('dms.archive', $room->slug))
            ->assertOk();

        $this->assertDatabaseHas('dm_participants', [
            'room_id' => $room->id,
            'user_id' => $firstUser->id,
        ]);
        $this->assertNotNull(DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', $firstUser->id)
            ->value('archived_at'));

        $firstList = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $secondList = $this->actingAs($secondUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertNotNull(collect($firstList)->firstWhere('slug', $room->slug)['archived_at']);
        $this->assertNull(collect($secondList)->firstWhere('slug', $room->slug)['archived_at']);
        $this->assertSame(1, Message::where('room_id', $room->id)->count());
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'body' => 'Keep this message.',
        ]);
    }

    public function test_dm_index_response_includes_archive_state_for_active_and_archived_rows(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $beforeArchive = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $activeRow = collect($beforeArchive)->firstWhere('slug', $room->slug);
        $this->assertIsArray($activeRow);
        $this->assertArrayHasKey('archived_at', $activeRow);
        $this->assertNull($activeRow['archived_at']);

        $this->actingAs($firstUser)
            ->postJson(route('dms.archive', $room->slug))
            ->assertOk();

        $afterArchive = $this->actingAs($firstUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $archivedRow = collect($afterArchive)->firstWhere('slug', $room->slug);
        $this->assertIsArray($archivedRow);
        $this->assertArrayHasKey('archived_at', $archivedRow);
        $this->assertNotNull($archivedRow['archived_at']);
    }

    public function test_archived_dm_messages_remain_available_and_can_be_restored(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);
        $this->createMessage($room, $secondUser, $secondCharacter, 'Still here.');

        $this->actingAs($firstUser)
            ->postJson(route('dms.archive', $room->slug))
            ->assertOk();

        $this->actingAs($firstUser)
            ->getJson(route('dms.messages.index', $room->slug))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Still here.']);

        $this->actingAs($firstUser)
            ->postJson(route('dms.restore', $room->slug))
            ->assertOk();

        $this->assertNull(DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', $firstUser->id)
            ->value('archived_at'));
    }

    public function test_new_incoming_message_restores_archived_dm_for_the_recipient(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->actingAs($secondUser)
            ->postJson(route('dms.archive', $room->slug))
            ->assertOk();

        $this->actingAs($firstUser)
            ->postJson(route('dms.messages.store', $room->slug), [
                'body' => 'Wake this DM back up.',
            ])
            ->assertOk();

        $this->assertNull(DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', $secondUser->id)
            ->value('archived_at'));

        $secondList = $this->actingAs($secondUser)
            ->getJson(route('dms.index'))
            ->assertOk()
            ->json('rooms');

        $this->assertNull(collect($secondList)->firstWhere('slug', $room->slug)['archived_at']);
    }

    public function test_sending_in_an_archived_dm_restores_it_for_the_sender(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->actingAs($firstUser)
            ->postJson(route('dms.archive', $room->slug))
            ->assertOk();

        $this->actingAs($firstUser)
            ->postJson(route('dms.messages.store', $room->slug), [
                'body' => 'Reopen on send.',
            ])
            ->assertOk();

        $this->assertNull(DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', $firstUser->id)
            ->value('archived_at'));
    }

    public function test_starting_a_dm_with_the_same_character_pair_reuses_and_restores_the_archived_room(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->actingAs($firstUser)
            ->postJson(route('dms.archive', $room->slug))
            ->assertOk();

        $response = $this->actingAs($firstUser)
            ->postJson(route('dms.start'), [
                'my_character_id' => $firstCharacter->id,
                'other_character_id' => $secondCharacter->id,
            ])
            ->assertOk();

        $this->assertSame($room->slug, $response->json('slug'));
        $this->assertSame(1, Room::where('type', Room::TYPE_DM)->count());
        $this->assertNull(DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', $firstUser->id)
            ->value('archived_at'));
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
            'dm_key' => Room::normalizedDmKey($firstCharacter->id, $secondCharacter->id),
        ]);

        $now = now();

        DB::table('dm_participants')->insert([
            [
                'room_id' => $room->id,
                'user_id' => $firstUser->id,
                'character_id' => $firstCharacter->id,
                'archived_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'room_id' => $room->id,
                'user_id' => $secondUser->id,
                'character_id' => $secondCharacter->id,
                'archived_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $room;
    }

    private function createMessage(Room $room, User $user, Character $character, string $body): Message
    {
        return Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => $body,
        ]);
    }
}
