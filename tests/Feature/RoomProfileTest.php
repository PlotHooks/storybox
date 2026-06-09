<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_profile_shows_friendly_placeholder_when_not_configured(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.profile.show', $room->slug))
            ->assertOk()
            ->assertSee('This room profile has not been configured yet.')
            ->assertDontSee('Summary')
            ->assertDontSee('Joining Information')
            ->assertDontSee('Rules')
            ->assertDontSee('Edit Profile');
    }

    public function test_room_profile_renders_configured_sections_and_hides_empty_ones(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $room->update([
            'profile_banner_url' => 'https://cdn.example.com/room-banner.png',
            'profile_summary' => "A tense political salon for city intrigue.\nBring a character with opinions.",
            'profile_joining_information' => '',
            'profile_rules' => "Stay in tone.\nRespect scene boundaries.",
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.profile.show', $room->slug))
            ->assertOk()
            ->assertSee('src="https://cdn.example.com/room-banner.png"', false)
            ->assertSee($room->name)
            ->assertSee('A tense political salon for city intrigue.')
            ->assertSee('Bring a character with opinions.')
            ->assertSee('Stay in tone.')
            ->assertSee('Respect scene boundaries.')
            ->assertDontSee('Joining Information');
    }

    public function test_room_profile_can_render_advanced_mode_without_standard_wrapper(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $room->update([
            'profile_mode' => Room::PROFILE_MODE_ADVANCED,
            'profile_custom_html' => '<main class="advanced-room-shell"><h1>Advanced Room</h1></main>',
            'profile_custom_css' => '.advanced-room-shell { min-height: 100vh; color: rgb(255, 240, 200); }',
            'profile_custom_js' => 'window.roomProfileLoaded = true;',
            'profile_summary' => 'This should not render around advanced mode.',
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.profile.show', $room->slug))
            ->assertOk()
            ->assertSee(route('rooms.profile.frame', $room->slug))
            ->assertDontSee('Joining Information')
            ->assertDontSee('Rules')
            ->assertSee('Back to Room');

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.profile.frame', $room->slug))
            ->assertOk()
            ->assertSee('<main class="advanced-room-shell"><h1>Advanced Room</h1></main>', false)
            ->assertSee('window.roomProfileLoaded = true;', false)
            ->assertSee('min-height: 100vh', false);
    }

    public function test_room_profile_edit_button_is_only_visible_to_room_managers(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.profile.show', $room->slug))
            ->assertOk()
            ->assertSee('Edit Profile');

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.profile.show', $room->slug))
            ->assertOk()
            ->assertDontSee('Edit Profile');
    }

    public function test_room_profile_edit_page_follows_existing_room_management_permissions(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$adminUser, $adminCharacter] = $this->createUserWithCharacter(admin: true);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->addModerator($room, $moderatorCharacter);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->get(route('rooms.profile.edit', $room->slug))
            ->assertOk()
            ->assertSee('Edit Room Profile')
            ->assertSee('Advanced Profile')
            ->assertSee('Custom HTML');

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->get(route('rooms.profile.edit', $room->slug))
            ->assertOk();

        $this->actingAs($adminUser)
            ->withSession(['active_character_id' => $adminCharacter->id])
            ->get(route('rooms.profile.edit', $room->slug))
            ->assertOk();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.profile.edit', $room->slug))
            ->assertForbidden();
    }

    public function test_room_profile_update_follows_existing_room_management_permissions_and_redirects_to_profile_view(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter();
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter();
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter();
        [$adminUser, $adminCharacter] = $this->createUserWithCharacter(admin: true);
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->addModerator($room, $moderatorCharacter);

        $payload = [
            'profile_banner_url' => 'https://cdn.example.com/profile-banner.png',
            'profile_summary' => 'Owner summary',
            'profile_joining_information' => 'Join with a courtly character.',
            'profile_rules' => 'No godmodding.',
            'profile_mode' => Room::PROFILE_MODE_ADVANCED,
            'profile_custom_html' => '<section>Custom room profile</section>',
            'profile_custom_css' => 'body { color: #fff; }',
            'profile_custom_js' => 'window.roomProfile = true;',
        ];

        $this->actingAs($ownerUser)
            ->patch(route('rooms.profile.update', $room->slug), array_merge($payload, ['character_id' => $ownerCharacter->id]))
            ->assertRedirect(route('rooms.profile.show', $room->slug));

        $this->actingAs($moderatorUser)
            ->patchJson(route('rooms.profile.update', $room->slug), array_merge($payload, [
                'character_id' => $moderatorCharacter->id,
                'profile_summary' => 'Moderator summary',
            ]))
            ->assertOk();

        $this->actingAs($adminUser)
            ->patchJson(route('rooms.profile.update', $room->slug), array_merge($payload, [
                'character_id' => $adminCharacter->id,
                'profile_summary' => 'Admin summary',
            ]))
            ->assertOk();

        $this->actingAs($viewerUser)
            ->patchJson(route('rooms.profile.update', $room->slug), array_merge($payload, [
                'character_id' => $viewerCharacter->id,
                'profile_summary' => 'Viewer summary',
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'profile_banner_url' => 'https://cdn.example.com/profile-banner.png',
            'profile_summary' => 'Admin summary',
            'profile_joining_information' => 'Join with a courtly character.',
            'profile_rules' => 'No godmodding.',
            'profile_mode' => Room::PROFILE_MODE_ADVANCED,
            'profile_custom_html' => '<section>Custom room profile</section>',
            'profile_custom_css' => 'body { color: #fff; }',
            'profile_custom_js' => 'window.roomProfile = true;',
        ]);
    }

    private function createUserWithCharacter(string $name = 'Character', bool $admin = false): array
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

    private function createRoom(User $user, Character $ownerCharacter): Room
    {
        return Room::create([
            'name' => 'Profile Room',
            'slug' => 'profile-room-' . uniqid(),
            'description' => 'A room for profile testing.',
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $ownerCharacter->id,
        ]);
    }

    private function addModerator(Room $room, Character $character): void
    {
        $room->roomCharacterRoles()->create([
            'character_id' => $character->id,
            'role' => \App\Models\RoomCharacterRole::ROLE_MODERATOR,
        ]);
    }
}
