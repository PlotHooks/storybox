<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CharacterPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_page_renders_characters_panel_trigger(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($user, $character, 'Tavern');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('data-open-characters-panel', false)
            ->assertSee('Characters');
    }

    public function test_characters_panel_does_not_offer_a_separate_switch_action(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter('First');
        $secondCharacter = $this->createCharacter($user, 'Second Character');
        $room = $this->createRoom($user, $firstCharacter, 'Tavern');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $firstCharacter->id])
            ->get(route('rooms.show', ['room' => $room->slug, 'characters' => 1]))
            ->assertOk()
            ->assertDontSee('/characters/'.$secondCharacter->id.'/switch', false);
    }

    public function test_legacy_switch_route_is_unavailable(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter('First');
        $secondCharacter = $this->createCharacter($user, 'Second Character');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $firstCharacter->id])
            ->post('/characters/'.$secondCharacter->id.'/switch')
            ->assertNotFound();
    }

    public function test_non_active_character_can_be_deleted_from_panel_flow(): void
    {
        [$user, $activeCharacter] = $this->createUserWithCharacter('Active');
        $otherCharacter = $this->createCharacter($user, 'Delete Me');
        $room = $this->createRoom($user, $activeCharacter, 'Tavern');
        $returnTo = route('rooms.show', ['room' => $room->slug, 'characters' => 1]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $activeCharacter->id])
            ->delete(route('characters.destroy', $otherCharacter), [
                'return_to' => $returnTo,
            ])
            ->assertRedirect($returnTo);

        $this->assertDatabaseMissing('characters', ['id' => $otherCharacter->id]);
    }

    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => Str::slug($name) . '-' . Str::random(6) . '@example.com',
        ]);

        return [$user, $this->createCharacter($user, $name . ' Character')];
    }

    private function createCharacter(User $user, string $name): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
        ]);
    }

    private function createRoom(User $user, Character $ownerCharacter, string $name): Room
    {
        return Room::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'description' => null,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $ownerCharacter->id,
        ]);
    }
}
