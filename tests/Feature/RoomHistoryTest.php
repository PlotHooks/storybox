<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_show_replaces_follow_tool_button_with_history_and_keeps_follow_card(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee(route('rooms.history.show', $room->slug))
            ->assertSee('History')
            ->assertSee('Follow this room')
            ->assertDontSee('data-context-tool="follow"', false);
    }

    public function test_room_history_route_requires_existing_room_access_rules(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter, visibility: Room::VISIBILITY_HIDDEN);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.history.show', $room->slug))
            ->assertForbidden();

        $this->whitelist($room, $viewerCharacter, $ownerCharacter);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.history.show', $room->slug))
            ->assertOk();
    }

    public function test_room_history_respects_blacklist_and_admin_override(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$adminUser, $adminCharacter] = $this->createUserWithCharacter('Admin', admin: true);
        $room = $this->createRoom($ownerUser, $ownerCharacter, visibility: Room::VISIBILITY_HIDDEN);

        $this->whitelist($room, $viewerCharacter, $ownerCharacter);
        $this->blacklist($room, $viewerCharacter, $ownerCharacter);
        $this->blacklist($room, $adminCharacter, $ownerCharacter);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.history.show', $room->slug))
            ->assertForbidden();

        $this->actingAs($adminUser)
            ->withSession(['active_character_id' => $adminCharacter->id])
            ->get(route('rooms.history.show', $room->slug))
            ->assertOk();
    }

    public function test_room_history_only_shows_last_thirty_days_and_orders_messages_by_created_at_then_id(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $selectedDay = now()->startOfDay()->subDays(5);
        $olderThanWindow = now()->startOfDay()->subDays(31)->addHour();

        $oldMessage = $this->createMessage($room, $ownerUser, $ownerCharacter, 'Expired room history message.');
        $oldMessage->forceFill([
            'created_at' => $olderThanWindow,
            'updated_at' => $olderThanWindow,
        ])->save();

        $firstMessage = $this->createMessage($room, $ownerUser, $ownerCharacter, 'First in transcript order.');
        $secondMessage = $this->createMessage($room, $ownerUser, $ownerCharacter, 'Second in transcript order.');
        $thirdMessage = $this->createMessage($room, $ownerUser, $ownerCharacter, 'Third in transcript order.');

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

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.history.show', [
                'room' => $room->slug,
                'day' => $selectedDay->toDateString(),
            ]));

        $response->assertOk()
            ->assertDontSee('Expired room history message.')
            ->assertViewHas('messages', fn ($messages) => $messages->pluck('id')->all() === [
                $firstMessage->id,
                $secondMessage->id,
                $thirdMessage->id,
            ]);
    }

    public function test_room_history_defaults_to_most_recent_active_day_when_today_has_no_messages(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $recentDay = now()->startOfDay()->subDays(2)->addHours(3);
        $message = $this->createMessage($room, $ownerUser, $ownerCharacter, 'Recent active day message.');
        $message->forceFill([
            'created_at' => $recentDay,
            'updated_at' => $recentDay,
        ])->save();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.history.show', $room->slug))
            ->assertOk()
            ->assertViewHas('selectedDayString', $recentDay->toDateString())
            ->assertSee('Recent active day message.');
    }

    private function createUserWithCharacter(string $name, bool $admin = false): array
    {
        $user = User::factory()->create(['is_admin' => $admin]);

        return [$user, $this->createCharacter($user, $name)];
    }

    private function createCharacter(User $user, string $name): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createRoom(User $user, Character $ownerCharacter, string $visibility = Room::VISIBILITY_PUBLIC): Room
    {
        return Room::create([
            'name' => 'History Room',
            'slug' => 'history-room-' . Str::random(16),
            'description' => 'A room for history testing.',
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => $visibility,
            'owner_character_id' => $ownerCharacter->id,
        ]);
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

    private function whitelist(Room $room, Character $targetCharacter, Character $createdBy): void
    {
        RoomAccessEntry::create([
            'room_id' => $room->id,
            'character_id' => $targetCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
            'scope' => RoomAccessEntry::SCOPE_CHARACTER,
            'created_by_character_id' => $createdBy->id,
        ]);
    }

    private function blacklist(Room $room, Character $targetCharacter, Character $createdBy): void
    {
        RoomAccessEntry::updateOrCreate(
            [
                'room_id' => $room->id,
                'character_id' => $targetCharacter->id,
                'type' => RoomAccessEntry::TYPE_BLACKLIST,
            ],
            [
                'scope' => RoomAccessEntry::SCOPE_CHARACTER,
                'created_by_character_id' => $createdBy->id,
            ],
        );
    }
}
