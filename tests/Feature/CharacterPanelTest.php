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

    public function test_character_cards_link_directly_to_public_profiles_while_preserving_management_link(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Link Owner');

        $response = $this->actingAs($user)
            ->get(route('characters.index'));

        $response->assertOk()
            ->assertSee(route('characters.profile.show', $character), false)
            ->assertSee(route('characters.manage', $character), false)
            ->assertSee('Manage character');
    }


    public function test_character_panel_groups_active_and_inactive_characters(): void
    {
        [$user, $activeCharacter] = $this->createUserWithCharacter('Active');
        $inactiveCharacter = $this->createCharacter($user, 'Inactive Character', false);

        $response = $this->actingAs($user)
            ->get(route('characters.index'));

        $response->assertOk()
            ->assertSee('Active Characters')
            ->assertSee('Inactive Characters')
            ->assertSeeInOrder([
                'Active Characters',
                $activeCharacter->name,
                'Inactive Characters',
                $inactiveCharacter->name,
            ], false);
    }

    public function test_character_panel_toggle_updates_character_active_state(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Toggle Owner');

        $this->actingAs($user)
            ->from(route('characters.index'))
            ->patch(route('characters.toggle-active', $character), [
                'is_active' => '0',
                'return_to' => route('characters.index'),
            ])
            ->assertRedirect(route('characters.index'));

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_active' => 0,
        ]);
    }

    public function test_room_posting_dropdown_only_shows_active_characters(): void
    {
        [$user, $activeCharacter] = $this->createUserWithCharacter('Active');
        $inactiveCharacter = $this->createCharacter($user, 'Inactive Character', false);
        $room = $this->createRoom($user, $activeCharacter, 'Tavern');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $activeCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('value="'.$activeCharacter->id.'" selected', false)
            ->assertDontSee('value="'.$inactiveCharacter->id.'"', false);
    }



    public function test_characters_index_keeps_character_settings_collapsed_by_default(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Collapsed');

        $response = $this->actingAs($user)
            ->get(route('characters.index'));

        $response->assertOk()
            ->assertSee('<details class="mt-3 rounded-lg border border-gray-800 bg-gray-950/40">', false)
            ->assertSee('Character details and style', false)
            ->assertDontSee('<details open', false);
    }

    public function test_character_show_route_redirects_to_public_profile(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Redirect Owner');

        $this->get(route('characters.show', $character))
            ->assertRedirect(route('characters.profile.show', $character));
    }

    public function test_owner_management_route_still_works(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Manager');

        $this->actingAs($user)
            ->get(route('characters.manage', $character))
            ->assertOk()
            ->assertSee('Character Page')
            ->assertSee(route('characters.profile.show', $character), false);
    }

    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => Str::slug($name) . '-' . Str::random(6) . '@example.com',
        ]);

        return [$user, $this->createCharacter($user, $name . ' Character')];
    }

    private function createCharacter(User $user, string $name, bool $isActive = true): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'is_active' => $isActive,
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
