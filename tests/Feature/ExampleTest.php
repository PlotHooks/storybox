<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_public_landing_page_at_root(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Collaborative roleplaying, built for writers.');
        $response->assertSee(route('register', absolute: false));
        $response->assertSee(route('login', absolute: false));
    }

    public function test_authenticated_users_are_redirected_from_root_to_chat(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('rooms.landing', absolute: false));
    }
}
