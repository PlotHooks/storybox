<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterPresence;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurrentCharacterSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_as_changes_update_current_character_state(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter('First');
        $secondCharacter = $this->createCharacter($user, 'Second Character');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $firstCharacter->id])
            ->postJson(route('rooms.current-character'), [
                'character_id' => $secondCharacter->id,
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'character_id' => $secondCharacter->id,
            ]);

        $this->assertSame($secondCharacter->id, session('active_character_id'));
    }

    public function test_switching_to_a_character_uses_that_characters_validated_current_room_url(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter('First');
        $secondCharacter = $this->createCharacter($user, 'Second Character');
        $room = $this->createRoom($user, $firstCharacter, 'Garden');

        \App\Models\CharacterPresence::create([
            'character_id' => $secondCharacter->id,
            'room_id' => $room->id,
            'last_seen_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $firstCharacter->id])
            ->postJson(route('rooms.current-character'), ['character_id' => $secondCharacter->id])
            ->assertOk()
            ->assertJsonPath('room_url', route('rooms.show', $room->slug));
    }


    public function test_changing_rooms_preserves_current_posting_character_when_valid(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter('First');
        $secondCharacter = $this->createCharacter($user, 'Second Character');
        $firstRoom = $this->createRoom($user, $firstCharacter, 'Tavern');
        $secondRoom = $this->createRoom($user, $firstCharacter, 'Garden');

        $this->actingAs($user)
            ->withSession(['active_character_id' => $secondCharacter->id])
            ->get(route('rooms.show', $firstRoom->slug))
            ->assertOk()
            ->assertSee('value="'.$secondCharacter->id.'" selected', false);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $secondCharacter->id])
            ->get(route('rooms.show', $secondRoom->slug))
            ->assertOk()
            ->assertSee('value="'.$secondCharacter->id.'" selected', false);
    }

    public function test_invalid_current_character_falls_back_to_first_available_character_for_room(): void
    {
        [$owner, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewer, $firstCharacter] = $this->createUserWithCharacter('Viewer');
        $secondCharacter = $this->createCharacter($viewer, 'Second Character');
        $hiddenRoom = $this->createRoom($owner, $ownerCharacter, 'Sanctum', Room::VISIBILITY_HIDDEN);

        RoomAccessEntry::create([
            'room_id' => $hiddenRoom->id,
            'character_id' => $firstCharacter->id,
            'type' => RoomAccessEntry::TYPE_WHITELIST,
            'created_by_character_id' => $ownerCharacter->id,
        ]);

        $this->actingAs($viewer)
            ->withSession(['active_character_id' => $secondCharacter->id])
            ->get(route('rooms.show', $hiddenRoom->slug))
            ->assertOk()
            ->assertSee('value="'.$firstCharacter->id.'" selected', false)
            ->assertSee('Posting as reset to '.$firstCharacter->name.' for this room.');
    }

    public function test_inactive_current_character_falls_back_to_first_active_available_character_for_room(): void
    {
        [$owner, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewer, $firstCharacter] = $this->createUserWithCharacter('Viewer');
        $inactiveCharacter = $this->createCharacter($viewer, 'Inactive Character', false);
        $room = $this->createRoom($owner, $ownerCharacter, 'Sanctum');

        $this->actingAs($viewer)
            ->withSession(['active_character_id' => $inactiveCharacter->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk()
            ->assertSee('value="'.$firstCharacter->id.'" selected', false)
            ->assertDontSee('value="'.$inactiveCharacter->id.'"', false)
            ->assertSee('Posting as reset to '.$firstCharacter->name.' for this room.');
    }

    public function test_room_switch_commits_only_after_confirmation_and_disables_composer_while_pending(): void
    {
        [$user, $character] = $this->createUserWithCharacter('Switcher');
        $room = $this->createRoom($user, $character, 'Tavern');

        $html = $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->getContent();

        $this->assertStringContainsString('let confirmedCharacterId = 0;', $html);
        $this->assertStringContainsString('let pendingCharacterId = null;', $html);
        $this->assertStringContainsString('pendingCharacterId !== null', $html);
        $this->assertStringNotContainsString('syncCurrentCharacter(preferred)', $html);
        $this->assertStringContainsString('id="posting-character-trigger"', $html);
        $this->assertStringContainsString('aria-haspopup="listbox"', $html);
        $this->assertStringContainsString('You are posting as '.$character->name, $html);
        $this->assertStringContainsString('Enter to send. Shift + Enter for a new line.', $html);
        $this->assertStringNotContainsString('<span class="text-xs text-[#8f8675]">Posting as</span>', $html);
    }


    public function test_room_page_uses_its_server_character_for_heartbeats_and_switches_by_navigation(): void
    {
        [$user, $character] = $this->createUserWithCharacter("Switcher");
        $room = $this->createRoom($user, $character, "Tavern");

        $html = $this->actingAs($user)
            ->withSession(["active_character_id" => $character->id])
            ->get(route("rooms.show", $room->slug))
            ->getContent();

        $this->assertStringContainsString("const pagePresenceCharacterId = serverActiveCharacterId;", $html);
        $this->assertStringContainsString("const preferred = serverActiveCharacterId", $html);
        $this->assertStringContainsString("const characterId = pagePresenceCharacterId;", $html);
        $this->assertStringContainsString("currentRoomParticipationToken(characterId)", $html);
        $this->assertStringContainsString("window.location.assign(result.roomUrl || \"/chat\");", $html);
        $this->assertStringContainsString("preserveComposerDraftForNavigation(oldId);", $html);
        $this->assertStringContainsString("restoreComposerDraftForPage();", $html);
        $this->assertStringContainsString("clearComposerDraft(id);", $html);
        $this->assertStringContainsString("switchRequestId !== characterSwitchRequestSequence", $html);
    }

    public function test_character_switch_request_does_not_mutate_room_presence(): void
    {
        [$user, $firstCharacter] = $this->createUserWithCharacter("First");
        $secondCharacter = $this->createCharacter($user, "Second Character");
        $firstRoom = $this->createRoom($user, $firstCharacter, "Tavern");
        $secondRoom = $this->createRoom($user, $firstCharacter, "Garden");

        CharacterPresence::create([
            "character_id" => $firstCharacter->id,
            "room_id" => $firstRoom->id,
            "last_seen_at" => now(),
        ]);
        CharacterPresence::create([
            "character_id" => $secondCharacter->id,
            "room_id" => $secondRoom->id,
            "last_seen_at" => now(),
        ]);

        $this->actingAs($user)
            ->withSession(["active_character_id" => $firstCharacter->id])
            ->postJson(route("rooms.current-character"), ["character_id" => $secondCharacter->id])
            ->assertOk()
            ->assertJsonPath("room_url", route("rooms.show", $secondRoom->slug));

        $this->assertDatabaseCount("character_presences", 2);
        $this->assertDatabaseHas("character_presences", [
            "character_id" => $secondCharacter->id,
            "room_id" => $secondRoom->id,
        ]);
        $this->assertDatabaseMissing("character_presences", [
            "character_id" => $secondCharacter->id,
            "room_id" => $firstRoom->id,
        ]);
    }

    public function test_source_heartbeat_does_not_change_target_character_current_room(): void
    {
        [$user, $sourceCharacter] = $this->createUserWithCharacter("Source");
        $targetCharacter = $this->createCharacter($user, "Target Character");
        $displayedRoom = $this->createRoom($user, $sourceCharacter, "Displayed Room");
        $targetRoom = $this->createRoom($user, $sourceCharacter, "Target Room");
        $staleTime = now()->subMinutes(20);
        $targetRoomTime = now()->subMinute();

        CharacterPresence::create([
            "character_id" => $sourceCharacter->id,
            "room_id" => $displayedRoom->id,
            "last_seen_at" => $staleTime,
        ]);
        CharacterPresence::create([
            "character_id" => $targetCharacter->id,
            "room_id" => $displayedRoom->id,
            "last_seen_at" => $staleTime,
        ]);
        CharacterPresence::create([
            "character_id" => $targetCharacter->id,
            "room_id" => $targetRoom->id,
            "last_seen_at" => $targetRoomTime,
        ]);

        $this->actingAs($user)
            ->withSession(["active_character_id" => $sourceCharacter->id])
            ->postJson(route("rooms.presence", $displayedRoom->slug), [
                "character_id" => $sourceCharacter->id,
                "room_participation_token" => app(\App\Services\RoomParticipationStateService::class)->issueToken($displayedRoom, $sourceCharacter),
            ])
            ->assertOk()
            ->assertJsonPath("refreshed_character_ids", [$sourceCharacter->id]);

        $this->assertDatabaseHas("character_presences", [
            "room_id" => $displayedRoom->id,
            "character_id" => $targetCharacter->id,
            "last_seen_at" => $staleTime->toDateTimeString(),
        ]);

        $this->actingAs($user)
            ->withSession(["active_character_id" => $sourceCharacter->id])
            ->postJson(route("rooms.current-character"), ["character_id" => $targetCharacter->id])
            ->assertOk()
            ->assertJsonPath("room_url", route("rooms.show", $targetRoom->slug));
    }


    private function createUserWithCharacter(string $name): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::random(6).'@example.com',
        ]);

        return [$user, $this->createCharacter($user, $name.' Character')];
    }

    private function createCharacter(User $user, string $name, bool $isActive = true): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => $isActive,
        ]);
    }

    private function createRoom(
        User $user,
        Character $ownerCharacter,
        string $name,
        string $visibility = Room::VISIBILITY_PUBLIC
    ): Room {
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
