<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DmHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dm_participant_can_access_dm_history(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter('First');
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter('Second');
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $message = $this->createMessage($room, $secondUser, $secondCharacter, 'Retained DM history line.');
        $message->forceFill([
            'created_at' => now()->startOfDay()->addHour(),
            'updated_at' => now()->startOfDay()->addHour(),
        ])->save();

        $this->actingAs($firstUser)
            ->get(route('dms.history.show', $room->slug))
            ->assertOk()
            ->assertSee('DM History')
            ->assertSee('Retained DM history line.');
    }

    public function test_dm_non_participant_cannot_access_dm_history(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter('First');
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter('Second');
        [$thirdUser] = $this->createUserWithCharacter('Third');
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $this->actingAs($thirdUser)
            ->get(route('dms.history.show', $room->slug))
            ->assertForbidden();
    }

    public function test_dm_history_only_shows_last_thirty_days_and_orders_messages_by_created_at_then_id(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter('First');
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter('Second');
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $selectedDay = now()->startOfDay()->subDays(4);
        $olderThanWindow = now()->startOfDay()->subDays(31)->addHour();

        $oldMessage = $this->createMessage($room, $secondUser, $secondCharacter, 'Expired DM history message.');
        $oldMessage->forceFill([
            'created_at' => $olderThanWindow,
            'updated_at' => $olderThanWindow,
        ])->save();

        $firstMessage = $this->createMessage($room, $secondUser, $secondCharacter, 'First DM transcript line.');
        $secondMessage = $this->createMessage($room, $firstUser, $firstCharacter, 'Second DM transcript line.');
        $thirdMessage = $this->createMessage($room, $secondUser, $secondCharacter, 'Third DM transcript line.');

        $firstMessage->forceFill([
            'created_at' => $selectedDay->copy()->addHour(),
            'updated_at' => $selectedDay->copy()->addHour(),
        ])->save();
        $secondMessage->forceFill([
            'created_at' => $selectedDay->copy()->addHours(2),
            'updated_at' => $selectedDay->copy()->addHours(2),
        ])->save();
        $thirdMessage->forceFill([
            'created_at' => $selectedDay->copy()->addHours(2),
            'updated_at' => $selectedDay->copy()->addHours(2),
        ])->save();

        $response = $this->actingAs($firstUser)
            ->get(route('dms.history.show', [
                'room' => $room->slug,
                'day' => $selectedDay->toDateString(),
            ]));

        $response->assertOk()
            ->assertDontSee('Expired DM history message.')
            ->assertViewHas('messages', fn ($messages) => $messages->pluck('id')->all() === [
                $firstMessage->id,
                $secondMessage->id,
                $thirdMessage->id,
            ]);
    }

    public function test_dm_history_defaults_to_today_when_today_has_messages_otherwise_most_recent_active_day(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter('First');
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter('Second');
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $recentDay = now()->startOfDay()->subDays(2)->addHours(3);
        $recentMessage = $this->createMessage($room, $secondUser, $secondCharacter, 'Recent active day DM.');
        $recentMessage->forceFill([
            'created_at' => $recentDay,
            'updated_at' => $recentDay,
        ])->save();

        $this->actingAs($firstUser)
            ->get(route('dms.history.show', $room->slug))
            ->assertOk()
            ->assertViewHas('selectedDayString', $recentDay->toDateString())
            ->assertSee('Recent active day DM.');

        $todayMessage = $this->createMessage($room, $firstUser, $firstCharacter, 'Today DM line.');
        $todayMessage->forceFill([
            'created_at' => now()->startOfDay()->addHours(2),
            'updated_at' => now()->startOfDay()->addHours(2),
        ])->save();

        $this->actingAs($firstUser)
            ->get(route('dms.history.show', $room->slug))
            ->assertOk()
            ->assertViewHas('selectedDayString', now()->toDateString())
            ->assertSee('Today DM line.');
    }

    public function test_dm_history_previous_and_next_active_day_skip_empty_days(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter('First');
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter('Second');
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter);

        $olderDay = now()->startOfDay()->subDays(6)->addHour();
        $selectedDay = now()->startOfDay()->subDays(3)->addHour();
        $newerDay = now()->startOfDay()->subDay()->addHour();

        foreach ([$olderDay, $selectedDay, $newerDay] as $index => $createdAt) {
            $message = $this->createMessage($room, $secondUser, $secondCharacter, 'DM day ' . $index);
            $message->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        $this->actingAs($firstUser)
            ->get(route('dms.history.show', [
                'room' => $room->slug,
                'day' => $selectedDay->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('previousActiveDayUrl', route('dms.history.show', [
                'room' => $room->slug,
                'day' => $olderDay->toDateString(),
            ]))
            ->assertViewHas('nextActiveDayUrl', route('dms.history.show', [
                'room' => $room->slug,
                'day' => $newerDay->toDateString(),
            ]));
    }

    public function test_dm_window_includes_history_link_control(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Viewer');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('id="dm-history-link"', false)
            ->assertSee('History');
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

    private function createMessage(Room $room, User $user, Character $character, string $body): Message
    {
        return Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => $body,
        ]);
    }

    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create(['name' => $name]);

        return [$user, Character::create([
            'user_id' => $user->id,
            'name' => $name . ' Character',
            'slug' => 'character-' . Str::random(16),
        ])];
    }
}
