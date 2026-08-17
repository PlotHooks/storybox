<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterPresence;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileCharacterLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_character_landing_requires_authentication(): void
    {
        $this->get(route('rooms.mobile-characters'))
            ->assertRedirect(route('login'));
    }

    public function test_it_shows_each_active_character_independently_and_excludes_inactive_characters(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter('First');
        $secondCharacter = $this->createCharacter($user, 'Second');
        $inactiveCharacter = $this->createCharacter($user, 'Inactive', false);
        $room = $this->createRoom($user, $firstCharacter, 'Shared Tavern');

        CharacterPresence::create(['character_id' => $firstCharacter->id, 'room_id' => $room->id, 'last_seen_at' => now()]);
        CharacterPresence::create(['character_id' => $secondCharacter->id, 'room_id' => $room->id, 'last_seen_at' => now()]);

        $this->actingAs($user)
            ->get(route('rooms.mobile-characters'))
            ->assertOk()
            ->assertSee($firstCharacter->name)
            ->assertSee($secondCharacter->name)
            ->assertSee('Shared Tavern', false)
            ->assertDontSee($inactiveCharacter->name)
            ->assertSee('data-character-id="'.$firstCharacter->id.'"', false)
            ->assertSee('data-character-id="'.$secondCharacter->id.'"', false);
    }

    public function test_it_only_shows_access_valid_current_rooms_and_marks_roomless_characters(): void
    {
        [$owner, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$user, $character] = $this->createUserWithCharacter('Viewer');
        $hiddenRoom = $this->createRoom($owner, $ownerCharacter, 'Private Sanctum', Room::VISIBILITY_HIDDEN);

        CharacterPresence::create(['character_id' => $character->id, 'room_id' => $hiddenRoom->id, 'last_seen_at' => now()]);

        $this->actingAs($user)
            ->get(route('rooms.mobile-characters'))
            ->assertOk()
            ->assertSee('Not currently in a room')
            ->assertDontSee('Private Sanctum');
    }

    public function test_it_shows_block_aware_unread_dm_totals_per_character(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter('First');
        $secondCharacter = $this->createCharacter($user, 'Second');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Other');
        $firstDm = $this->createDm($user, $firstCharacter, $otherUser, $otherCharacter);
        $secondDm = $this->createDm($user, $secondCharacter, $otherUser, $otherCharacter);

        $this->createMessage($firstDm, $otherUser, $otherCharacter, 'One');
        $this->createMessage($firstDm, $otherUser, $otherCharacter, 'Two');
        $this->createMessage($secondDm, $otherUser, $otherCharacter, 'Three');

        $html = $this->actingAs($user)->get(route('rooms.mobile-characters'))->assertOk()->getContent();

        $this->assertStringContainsString($firstCharacter->name, $html);
        $this->assertStringContainsString('2 unread DM', $html);
        $this->assertStringContainsString('1 unread DM', $html);
    }

    public function test_zero_active_characters_show_onboarding_actions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('rooms.mobile-characters'))
            ->assertOk()
            ->assertSee('Create Character')
            ->assertSee('Browse Rooms')
            ->assertSee('Site Rules')
            ->assertDontSee('data-character-id=', false);
    }

    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create(['email' => Str::slug($name).Str::random(6).'@example.com']);

        return [$user, $this->createCharacter($user, $name.' Character')];
    }

    private function createCharacter(User $user, string $name, bool $isActive = true): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => $isActive,
        ]);
    }

    private function createRoom(User $user, Character $owner, string $name, string $visibility = Room::VISIBILITY_PUBLIC): Room
    {
        return Room::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => $visibility,
            'owner_character_id' => $owner->id,
        ]);
    }

    private function createDm(User $firstUser, Character $firstCharacter, User $secondUser, Character $secondCharacter): Room
    {
        $room = Room::create([
            'name' => 'DM',
            'slug' => 'dm-'.Str::lower(Str::random(10)),
            'user_id' => $firstUser->id,
            'created_by' => $firstUser->id,
            'type' => Room::TYPE_DM,
            'visibility' => Room::VISIBILITY_HIDDEN,
            'dm_key' => Room::normalizedDmKey($firstCharacter->id, $secondCharacter->id),
        ]);

        foreach ([[$firstUser, $firstCharacter], [$secondUser, $secondCharacter]] as [$user, $character]) {
            DB::table('dm_participants')->insert([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'character_id' => $character->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $room;
    }

    private function createMessage(Room $room, User $user, Character $character, string $body): Message
    {
        return Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => $body,
            'type' => Message::TYPE_NORMAL,
        ]);
    }
}
