<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannedUserMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_banned_user_is_logged_out_on_authenticated_request(): void
    {
        $user = User::factory()->create([
            'is_banned' => true,
            'banned_reason' => 'Test ban',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHasErrors([
                'email' => 'This account has been banned.',
            ]);

        $this->assertGuest();
    }

    public function test_expired_ban_is_cleared_on_authenticated_request(): void
    {
        $user = User::factory()->create([
            'is_banned' => true,
            'banned_until' => now()->subMinute(),
            'banned_reason' => 'Expired test ban',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $this->assertAuthenticatedAs($user);

        $user->refresh();

        $this->assertFalse($user->is_banned);
        $this->assertNull($user->banned_until);
        $this->assertNull($user->banned_reason);
    }
}
