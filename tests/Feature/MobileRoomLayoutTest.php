<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileRoomLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_page_keeps_desktop_panels_and_renders_mobile_chat_markup(): void
    {
        $user = User::factory()->create();
        $character = Character::create([
            'user_id' => $user->id,
            'name' => 'Mobile Writer',
            'slug' => 'mobile-writer-'.Str::lower(Str::random(6)),
        ]);
        $room = Room::create([
            'name' => 'Mobile Tavern',
            'slug' => 'mobile-tavern-'.Str::lower(Str::random(6)),
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $character->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('Context Dock')
            ->assertSee('Nexus')
            ->assertSee('id="left-panel" class="hidden', false)
            ->assertSee('sm:flex lg:w-72', false)
            ->assertSee('id="right-panel" class="hidden', false)
            ->assertSee('sm:flex lg:w-80', false)
            ->assertSee('data-mobile-room-header', false)
            ->assertSee($character->name)
            ->assertSee($room->name)
            ->assertSee('id="message-container"', false)
            ->assertSee('id="message-form"', false)
            ->assertSee('data-mobile-composer', false)
            ->assertSee('hidden sm:block', false);
    }
}
