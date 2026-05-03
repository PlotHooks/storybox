<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_can_store_external_http_avatar_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('characters.store'), [
                'name' => 'Echo',
                'avatar' => 'https://cdn.example.test/echo.png',
            ])
            ->assertRedirect(route('characters.index'));

        $this->assertDatabaseHas('characters', [
            'user_id' => $user->id,
            'name' => 'Echo',
            'avatar' => 'https://cdn.example.test/echo.png',
        ]);
    }

    public function test_character_avatar_rejects_non_http_urls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('characters.index'))
            ->post(route('characters.store'), [
                'name' => 'Echo',
                'avatar' => 'javascript:alert(1)',
            ])
            ->assertRedirect(route('characters.index'))
            ->assertSessionHasErrors('avatar');

        $this->assertDatabaseMissing('characters', [
            'user_id' => $user->id,
            'name' => 'Echo',
        ]);
    }

    public function test_character_style_update_can_set_external_avatar_url(): void
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Echo',
            'slug' => 'echo-test',
        ]);

        $this->actingAs($user)
            ->post(route('characters.style', $character), [
                'avatar' => 'http://images.example.test/echo.jpg',
                'text_color_1' => '#D8F3FF',
            ])
            ->assertRedirect(route('characters.index'));

        $this->assertSame('http://images.example.test/echo.jpg', $character->fresh()->avatar);
    }
}
