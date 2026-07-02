<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('action="' . route('logout') . '"', false);
        $response->assertSee('Log Out');
        $response->assertSee('DM Notification Sound');
        $response->assertSee('Preview Sound');
        $response->assertSee('dm_notification_volume', false);
        $response->assertSee('Volume');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_dm_notification_sound_preferences_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'dm_notification_sound_enabled' => '1',
                'dm_notification_sound_choice' => User::DM_NOTIFICATION_SOUND_CUSTOM,
                'dm_notification_sound_url' => 'https://cdn.example.com/chime.ogg',
                'dm_notification_volume' => '35',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertTrue($user->dm_notification_sound_enabled);
        $this->assertSame(User::DM_NOTIFICATION_SOUND_CUSTOM, $user->dm_notification_sound_choice);
        $this->assertSame('https://cdn.example.com/chime.ogg', $user->dm_notification_sound_url);
        $this->assertSame(35, $user->dm_notification_volume);
    }

    public function test_dm_notification_sound_off_disables_sound_even_when_enabled_flag_is_present(): void
    {
        $user = User::factory()->create([
            'dm_notification_sound_enabled' => true,
            'dm_notification_sound_choice' => User::DM_NOTIFICATION_SOUND_BELL,
            'dm_notification_volume' => 72,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'dm_notification_sound_enabled' => '1',
                'dm_notification_sound_choice' => User::DM_NOTIFICATION_SOUND_OFF,
                'dm_notification_volume' => '72',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertFalse($user->dm_notification_sound_enabled);
        $this->assertSame(User::DM_NOTIFICATION_SOUND_OFF, $user->dm_notification_sound_choice);
        $this->assertSame(72, $user->dm_notification_volume);
    }

    public function test_custom_dm_notification_sound_url_must_use_supported_audio_extension(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'dm_notification_sound_enabled' => '1',
                'dm_notification_sound_choice' => User::DM_NOTIFICATION_SOUND_CUSTOM,
                'dm_notification_sound_url' => 'https://cdn.example.com/chime.txt',
                'dm_notification_volume' => '60',
            ]);

        $response
            ->assertSessionHasErrors('dm_notification_sound_url')
            ->assertRedirect('/profile');
    }

    public function test_dm_notification_volume_must_be_within_range(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'dm_notification_sound_enabled' => '1',
                'dm_notification_sound_choice' => User::DM_NOTIFICATION_SOUND_BELL,
                'dm_notification_volume' => '101',
            ]);

        $response
            ->assertSessionHasErrors('dm_notification_volume')
            ->assertRedirect('/profile');
    }

    public function test_dm_notification_sound_preferences_clamp_invalid_stored_volume(): void
    {
        $user = User::factory()->create([
            'dm_notification_sound_enabled' => true,
            'dm_notification_sound_choice' => User::DM_NOTIFICATION_SOUND_DEFAULT,
            'dm_notification_volume' => 255,
        ]);

        $preferences = $user->dmNotificationSoundPreferences();

        $this->assertSame(100, $preferences['volume']);
        $this->assertTrue($preferences['enabled']);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
