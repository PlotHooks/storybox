<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomEjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class RoomManagementErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_room_management_request_returns_structured_forbidden_json(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter('Target');
        $room = $this->createRoom($ownerUser, $ownerCharacter, 'Sanctum');

        $this->actingAs($viewerUser)
            ->postJson(route('rooms.whitelist.store', $room->slug), [
                'character_id' => $viewerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'ROOM_MANAGEMENT_FORBIDDEN');
    }

    public function test_protected_target_returns_structured_json_error(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $ownerAltCharacter = $this->createCharacter($ownerUser, 'Owner Alt');
        $room = $this->createRoom($ownerUser, $ownerCharacter, 'Sanctum');

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.blacklist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $ownerAltCharacter->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'OWNER_ACCOUNT_PROTECTED');
    }

    public function test_validation_failures_return_structured_json_errors(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter, 'Sanctum');

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.whitelist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_handle' => 'not-a-real-handle',
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'TARGET_CHARACTER_NOT_FOUND')
            ->assertJsonPath('error.fields.target_character_handle.0', 'Target character not found. Use the full public handle format Name#ABCD.');
    }

    public function test_unexpected_room_management_failures_do_not_expose_internal_details(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter('Target');
        $room = $this->createRoom($ownerUser, $ownerCharacter, 'Sanctum');

        $service = Mockery::mock(RoomEjectionService::class);
        $service->shouldReceive('eject')
            ->once()
            ->andThrow(new \RuntimeException('SQLSTATE secret-path /var/www/storybox'));
        $service->shouldReceive('ejectAccount')->never();
        $this->app->instance(RoomEjectionService::class, $service);

        Log::spy();

        $response = $this->actingAs($ownerUser)
            ->postJson(route('rooms.blacklist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ]);

        $response->assertStatus(500)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error.code', 'ROOM_MANAGEMENT_FAILED');

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('/var/www/storybox', $response->getContent());
        $this->assertMatchesRegularExpression('/RM-[A-Z0-9]{8}/', (string) $response->json('error.reference'));
    }

    public function test_successful_room_management_json_action_still_works(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter('Target');
        $room = $this->createRoom($ownerUser, $ownerCharacter, 'Sanctum');

        $this->actingAs($ownerUser)
            ->postJson(route('rooms.whitelist.store', $room->slug), [
                'character_id' => $ownerCharacter->id,
                'target_character_id' => $targetCharacter->id,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_browser_form_failures_redirect_back_into_storybox_ui(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$targetUser, $targetCharacter] = $this->createUserWithCharacter('Target');
        $room = $this->createRoom($ownerUser, $ownerCharacter, 'Sanctum');

        $response = $this->from(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']))
            ->actingAs($viewerUser)
            ->post(route('rooms.whitelist.store', ['room' => $room->slug, 'tool' => 'settings']), [
                'character_id' => $viewerCharacter->id,
                'context_tool' => 'settings',
                'target_character_id' => $targetCharacter->id,
            ]);

        $response->assertRedirect(route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']));
        $response->assertSessionHasErrors('room_management');
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
            'is_active' => true,
        ]);
    }

    private function createRoom(User $user, Character $ownerCharacter, string $name, string $visibility = Room::VISIBILITY_PUBLIC): Room
    {
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
