<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurrentCharacterSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_as_changes_update_current_character_state(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter('First');
        $secondCharacter = $this->createCharacter($user, 'Second Character');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $firstCharacter->id])
            ->postJson(route('rooms.current-character'), [
                'character_id' => $secondCharacter->id,
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'character_id' => $secondCharacter->id,
            ]);

        $this->assertSame($secondCharacter->id, session('active_character_id'));
    }

    public function test_changing_rooms_preserves_current_posting_character_when_valid(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter('First');
        $secondCharacter = $this->createCharacter($user, 'Second Character');
        $firstRoom = $this->createRoom($user, $firstCharacter, 'Tavern');
        $secondRoom = $this->createRoom($user, $firstCharacter, 'Garden');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $secondCharacter->id])
            ->get(route('rooms.show', $firstRoom->slug))
            ->assertOk()
            ->assertSee('value="'.$secondCharacter->id.'" selected', false);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $secondCharacter->id])
            ->get(route('rooms.show', $secondRoom->slug))
            ->assertOk()
            ->assertSee('value="'.$secondCharacter->id.'" selected', false);
    }

    public function test_invalid_current_character_falls_back_to_first_available_character_for_room(): void
    {
        [$owner, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewer, $firstCharacter] = $this->createUserWithCharacter('Viewer');
        $secondCharacter = $this->createCharacter($viewer, 'Second Character');
        $hiddenRoom = $this->createRoom($owner, $ownerCharacter, 'Sanctum', Room::VISIBILITY_HIDDEN);

        RoomAccessEntry::create([
            'room_id' => $hiddenRoom->id,
            'character_id' => $firstCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
            'created_by_character_id' => $ownerCharacter->id,
        ]);

        $this->actingAs($viewer)
            ->withSession(['active_character_id' => $secondCharacter->id])
            ->get(route('rooms.show', $hiddenRoom->slug))
            ->assertOk()
            ->assertSee('value="'.$firstCharacter->id.'" selected', false)
            ->assertSee('Posting as reset to '.$firstCharacter->name.' for this room.');
    }

    public function test_inactive_current_character_falls_back_to_first_active_available_character_for_room(): void
    {
        [$owner, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewer, $firstCharacter] = $this->createUserWithCharacter('Viewer');
        $inactiveCharacter = $this->createCharacter($viewer, 'Inactive Character', false);
        $room = $this->createRoom($owner, $ownerCharacter, 'Sanctum');

        $this->actingAs($viewer)
            ->withSession(['active_character_id' => $inactiveCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('value="'.$firstCharacter->id.'" selected', false)
            ->assertDontSee('value="'.$inactiveCharacter->id.'"', false)
            ->assertSee('Posting as reset to '.$firstCharacter->name.' for this room.');
    }

    public function test_room_switch_commits_only_after_confirmation_and_disables_composer_while_pending(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Switcher');
        $room = $this->createRoom($user, $character, 'Tavern');

        $html = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->getContent();

        $this->assertStringContainsString('let confirmedCharacterId = 0;', $html);
        $this->assertStringContainsString('let pendingCharacterId = null;', $html);
        $this->assertStringContainsString('pendingCharacterId !== null', $html);
        $this->assertStringNotContainsString('syncCurrentCharacter(preferred)', $html);
    }


    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::random(6).'@example.com',
        ]);

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

    private function createRoom(
        User $user,
        Character $ownerCharacter,
        string $name,
        string $visibility = Room::VISIBILITY_PUBLIC
    ): Room {
        return Room::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => null,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => $visibility,
            'owner_character_id' => $ownerCharacter->id,
        ]);
    }
}
