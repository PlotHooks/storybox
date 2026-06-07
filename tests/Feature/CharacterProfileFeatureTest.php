<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterProfile;
use App\Models\CharacterProfileRevision;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CharacterProfileFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_view_is_accessible(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Public');

        $character->ensureProfile()->update([
            'tagline' => 'Wanted for high fantasy intrigue.',
            'biography' => 'Known across the city for impossible bargains.',
        ]);

        $this->get(route('characters.profile.show', $character))
            ->assertOk()
            ->assertSee($character->name)
            ->assertSee('Wanted for high fantasy intrigue.')
            ->assertSee('Known across the city for impossible bargains.');
    }

    public function test_user_can_view_another_users_public_character_profile(): void
    {
        [$owner, $character] = $this->createUserWithCharacter('Owner');
        [$viewer] = $this->createUserWithCharacter('Viewer');

        $character->ensureProfile()->update([
            'tagline' => 'Open to cross-city intrigue.',
        ]);

        $this->actingAs($viewer)
            ->get(route('characters.profile.show', $character))
            ->assertOk()
            ->assertSee($character->name)
            ->assertSee('Open to cross-city intrigue.');
    }

    public function test_user_sees_view_profile_link_for_another_users_character(): void
    {
        [$owner, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewer, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createPublicRoom($owner, $ownerCharacter);
        $this->addCharacterPresence($room, $owner, $ownerCharacter);

        $this->actingAs($viewer)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('View Profile')
            ->assertSee('/characters/${characterId}/profile', false)
            ->assertSee((string) $ownerCharacter->id, false);
    }

    public function test_non_owner_does_not_see_edit_profile_for_another_users_character(): void
    {
        [$owner, $character] = $this->createUserWithCharacter('Owner');
        [$viewer] = $this->createUserWithCharacter('Viewer');

        $this->actingAs($viewer)
            ->get(route('characters.profile.show', $character))
            ->assertOk()
            ->assertDontSee('Edit Profile');
    }

    public function test_owner_still_sees_edit_profile_for_their_own_character(): void
    {
        [$owner, $character] = $this->createUserWithCharacter('Owner');

        $this->actingAs($owner)
            ->get(route('characters.profile.show', $character))
            ->assertOk()
            ->assertSee('Edit Profile');
    }

    public function test_normal_basic_profiles_still_use_regular_storybox_layout(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Basic');

        $character->ensureProfile()->update([
            'tagline' => 'Basic profile tagline.',
            'custom_profile_enabled' => false,
        ]);

        $this->get(route('characters.profile.show', $character))
            ->assertOk()
            ->assertSee('Character Profile')
            ->assertSee('max-w-5xl', false)
            ->assertDontSee('data-advanced-profile-viewport', false);
    }

    public function test_owner_can_edit_profile(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Owner');

        $this->actingAs($user)
            ->get(route('characters.profile.edit', $character))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('characters.profile.update', $character), [
                'template_type' => CharacterProfile::TEMPLATE_STORYBOX,
                'tagline' => 'A careful schemer.',
                'biography' => 'Biography text',
                'hooks' => 'Hooks text',
                'custom_profile_enabled' => '0',
            ])
            ->assertRedirect(route('characters.profile.edit', $character));

        $this->assertDatabaseHas('character_profiles', [
            'character_id' => $character->id,
            'tagline' => 'A careful schemer.',
            'biography' => 'Biography text',
            'hooks' => 'Hooks text',
        ]);
    }

    public function test_non_owner_cannot_edit_profile(): void
    {
        [$owner, $character] = $this->createUserWithCharacter('Owner');
        [$intruder] = $this->createUserWithCharacter('Intruder');

        $this->actingAs($intruder)
            ->get(route('characters.profile.edit', $character))
            ->assertForbidden();
    }

    public function test_admin_can_edit_and_moderate_profile(): void
    {
        [$owner, $character] = $this->createUserWithCharacter('Owner');
        $admin = User::factory()->create(['is_admin' => true]);

        $character->ensureProfile()->update([
            'custom_profile_enabled' => true,
            'custom_html' => '<div>custom</div>',
        ]);

        $this->actingAs($admin)
            ->get(route('characters.profile.edit', $character))
            ->assertOk();

        $this->actingAs($admin)
            ->from(route('characters.profile.edit', $character))
            ->post(route('characters.profile.disable-custom', $character))
            ->assertRedirect(route('characters.profile.edit', $character));

        $this->assertDatabaseHas('character_profiles', [
            'character_id' => $character->id,
            'custom_profile_disabled_by_admin' => true,
        ]);
    }

    public function test_revision_snapshot_is_created_before_custom_code_update(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Revision');

        $profile = $character->ensureProfile();
        $profile->update([
            'custom_profile_enabled' => true,
            'custom_html' => '<div>old html</div>',
            'custom_css' => 'body { color: red; }',
            'custom_js' => 'console.log("old");',
        ]);

        $this->actingAs($user)
            ->post(route('characters.profile.update', $character), [
                'template_type' => CharacterProfile::TEMPLATE_STORYBOX,
                'tagline' => 'Updated',
                'custom_profile_enabled' => '1',
                'custom_html' => '<div>new html</div>',
                'custom_css' => 'body { color: blue; }',
                'custom_js' => 'console.log("new");',
            ])
            ->assertRedirect(route('characters.profile.edit', $character));

        $this->assertDatabaseHas('character_profile_revisions', [
            'character_profile_id' => $profile->id,
            'custom_html' => '<div>old html</div>',
            'custom_css' => 'body { color: red; }',
            'custom_js' => 'console.log("old");',
        ]);

        $this->assertSame(1, CharacterProfileRevision::count());
        $this->assertSame('<div>new html</div>', $profile->fresh()->custom_html);
    }

    public function test_advanced_profile_public_route_does_not_contain_character_profile_label(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Advanced Label');

        $character->ensureProfile()->update([
            'custom_profile_enabled' => true,
            'custom_html' => '<div>Sandboxed</div>',
        ]);

        $this->get(route('characters.profile.show', $character))
            ->assertOk()
            ->assertDontSee('>Character Profile<', false);
    }

    public function test_advanced_profile_public_route_does_not_show_edit_profile_chrome(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Advanced Edit');

        $character->ensureProfile()->update([
            'custom_profile_enabled' => true,
            'custom_html' => '<div>Sandboxed</div>',
        ]);

        $this->actingAs($user)
            ->get(route('characters.profile.show', $character))
            ->assertOk()
            ->assertDontSee('Edit Profile');
    }

    public function test_advanced_profile_public_route_uses_fullscreen_wrapper_not_normal_card_layout(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Advanced Fullscreen');

        $character->ensureProfile()->update([
            'custom_profile_enabled' => true,
            'custom_html' => '<div>Sandboxed</div>',
        ]);

        $this->get(route('characters.profile.show', $character))
            ->assertOk()
            ->assertSee('data-advanced-profile-viewport', false)
            ->assertSee('width: 100vw;', false)
            ->assertSee('height: 100vh;', false)
            ->assertDontSee('max-w-6xl', false)
            ->assertDontSee('rounded-3xl', false);
    }

    public function test_sandbox_iframe_does_not_include_allow_same_origin(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Sandbox');

        $character->ensureProfile()->update([
            'custom_profile_enabled' => true,
            'custom_html' => '<div>Sandboxed</div>',
        ]);

        $this->get(route('characters.profile.show', $character))
            ->assertOk()
            ->assertSee('sandbox="allow-scripts"', false)
            ->assertDontSee('allow-same-origin', false);
    }

    public function test_advanced_profile_frame_loads_saved_custom_css_and_js(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Frame Assets');

        $character->ensureProfile()->update([
            'custom_profile_enabled' => true,
            'custom_html' => '<div id="profile-root">Sandboxed</div>',
            'custom_css' => 'body { background: rgb(1, 2, 3); } #profile-root { width: 100vw; }',
            'custom_js' => 'window.__storyboxProfileLoaded = true;',
        ]);

        $this->get(route('characters.profile.frame', $character))
            ->assertOk()
            ->assertSee('body { background: rgb(1, 2, 3); }', false)
            ->assertSee('#profile-root { width: 100vw; }', false)
            ->assertSee('window.__storyboxProfileLoaded = true;', false)
            ->assertSee('<div id="profile-root">Sandboxed</div>', false);
    }

    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::random(6).'@example.com',
        ]);

        return [$user, $this->createCharacter($user, $name.' Character')];
    }

    private function createCharacter(User $user, string $name): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ]);
    }

    private function createPublicRoom(User $user, Character $ownerCharacter): Room
    {
        return Room::create([
            'name' => 'Room '.Str::random(8),
            'slug' => 'room-'.Str::random(16),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $ownerCharacter->id,
        ]);
    }

    private function addCharacterPresence(Room $room, User $user, Character $character): void
    {
        DB::table('character_presences')->insert([
            'room_id' => $room->id,
            'character_id' => $character->id,
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
