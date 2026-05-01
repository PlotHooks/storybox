<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageSeekPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_room_load_returns_latest_messages_in_chronological_order(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);
        $messages = $this->createMessages($room, $user, $character, 150);

        $ids = $this->actingAs($user)
            ->getJson(route('rooms.messages.index', $room->slug))
            ->assertOk()
            ->collect()
            ->pluck('id')
            ->all();

        $this->assertSame($messages->pluck('id')->slice(100)->values()->all(), $ids);
        $this->assertSame($ids, collect($ids)->sort()->values()->all());
    }

    public function test_before_id_returns_older_messages_without_duplicates(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);
        $this->createMessages($room, $user, $character, 150);

        $initialIds = $this->actingAs($user)
            ->getJson(route('rooms.messages.index', $room->slug))
            ->assertOk()
            ->collect()
            ->pluck('id');

        $olderIds = $this->actingAs($user)
            ->getJson(route('rooms.messages.index', [
                'room' => $room->slug,
                'before_id' => $initialIds->first(),
            ]))
            ->assertOk()
            ->collect()
            ->pluck('id');

        $this->assertCount(50, $olderIds);
        $this->assertTrue($olderIds->every(fn (int $id): bool => $id < $initialIds->first()));
        $this->assertSame($olderIds->all(), $olderIds->sort()->values()->all());
        $this->assertEmpty($initialIds->intersect($olderIds)->all());
    }

    public function test_after_id_returns_newer_messages_only(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);
        $messages = $this->createMessages($room, $user, $character, 150);
        $cursor = $messages[99]->id;

        $ids = $this->actingAs($user)
            ->getJson(route('rooms.messages.index', [
                'room' => $room->slug,
                'after_id' => $cursor,
            ]))
            ->assertOk()
            ->collect()
            ->pluck('id');

        $this->assertCount(50, $ids);
        $this->assertTrue($ids->every(fn (int $id): bool => $id > $cursor));
        $this->assertSame($ids->all(), $ids->sort()->values()->all());
    }

    public function test_after_alias_still_works(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);
        $messages = $this->createMessages($room, $user, $character, 10);
        $cursor = $messages[6]->id;

        $ids = $this->actingAs($user)
            ->getJson(route('rooms.messages.index', [
                'room' => $room->slug,
                'after' => $cursor,
            ]))
            ->assertOk()
            ->collect()
            ->pluck('id')
            ->all();

        $this->assertSame($messages->pluck('id')->slice(7)->values()->all(), $ids);
    }

    public function test_since_sync_does_not_escape_room_scope(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);
        $otherRoom = $this->createRoom($user);

        $oldSameRoom = $this->createMessage($room, $user, $character, 'Edited in same room.');
        $cursorMessage = $this->createMessage($room, $user, $character, 'Cursor.');
        $otherRoomMessage = $this->createMessage($otherRoom, $user, $character, 'Edited in another room.');

        $since = now()->subMinute();
        $oldSameRoom->forceFill(['updated_at' => now()])->save();
        $otherRoomMessage->forceFill(['updated_at' => now()])->save();

        $ids = $this->actingAs($user)
            ->getJson(route('rooms.messages.index', [
                'room' => $room->slug,
                'after_id' => $cursorMessage->id,
                'since' => $since->toDateTimeString(),
            ]))
            ->assertOk()
            ->collect()
            ->pluck('id')
            ->all();

        $this->assertContains($oldSameRoom->id, $ids);
        $this->assertNotContains($otherRoomMessage->id, $ids);
    }

    public function test_soft_deleted_messages_do_not_break_seek_pagination(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);
        $messages = $this->createMessages($room, $user, $character, 60);
        $messages[55]->delete();

        $ids = $this->actingAs($user)
            ->getJson(route('rooms.messages.index', $room->slug))
            ->assertOk()
            ->collect()
            ->pluck('id');

        $this->assertCount(50, $ids);
        $this->assertTrue($ids->contains($messages[55]->id));
        $this->assertSame($ids->all(), $ids->sort()->values()->all());
    }

    public function test_before_id_and_after_id_together_return_validation_error(): void
    {
        [$user, $character] = $this->createUserWithCharacter();
        $room = $this->createRoom($user);
        $messages = $this->createMessages($room, $user, $character, 3);

        $this->actingAs($user)
            ->getJson(route('rooms.messages.index', [
                'room' => $room->slug,
                'before_id' => $messages[2]->id,
                'after_id' => $messages[0]->id,
            ]))
            ->assertUnprocessable();
    }

    public function test_dm_initial_load_returns_latest_messages_in_chronological_order(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter();
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter();
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);
        $messages = $this->createMessages($room, $secondUser, $secondCharacter, 150);

        $ids = collect($this->actingAs($firstUser)
            ->getJson(route('dms.messages.index', [
                'room' => $room->slug,
                'after' => 0,
            ]))
            ->assertOk()
            ->json('messages'))
            ->pluck('id')
            ->all();

        $this->assertSame($messages->pluck('id')->slice(100)->values()->all(), $ids);
        $this->assertSame($ids, collect($ids)->sort()->values()->all());
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
            'type' => 'dm',
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

    private function createMessages(Room $room, User $user, Character $character, int $count): Collection
    {
        return collect(range(1, $count))
            ->map(fn (int $number): Message => $this->createMessage($room, $user, $character, "Message {$number}"));
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
