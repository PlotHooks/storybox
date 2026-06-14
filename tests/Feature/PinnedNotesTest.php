<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomPinnedNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PinnedNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_viewer_can_read_pinned_notes_but_not_manage_them(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $activeNote = RoomPinnedNote::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Chapter Status',
            'category' => RoomPinnedNote::CATEGORY_CURRENT_PLOT,
            'body' => 'The council has sealed the north gate.',
            'accent_color' => RoomPinnedNote::ACCENT_BLUE,
            'status' => RoomPinnedNote::STATUS_ACTIVE,
        ]);

        $archivedNote = RoomPinnedNote::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Old Briefing',
            'category' => RoomPinnedNote::CATEGORY_SESSION_RECAPS,
            'body' => 'Archive reference.',
            'status' => RoomPinnedNote::STATUS_ARCHIVED,
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.pinned-notes.index', $room->slug));

        $response->assertOk()
            ->assertJsonPath('permissions.can_create', false)
            ->assertJsonPath('permissions.can_manage', false)
            ->assertJsonPath('notes.0.id', $activeNote->id)
            ->assertJsonPath('notes.0.author_character.name', 'Owner')
            ->assertJsonPath('notes.0.accent_color', RoomPinnedNote::ACCENT_BLUE)
            ->assertJsonPath('notes.0.accent_color_label', 'Blue')
            ->assertJsonCount(8, 'accent_colors');

        $notes = collect($response->json('notes'))->keyBy('id');
        $this->assertSame(RoomPinnedNote::STATUS_ARCHIVED, $notes[$archivedNote->id]['status']);
    }

    public function test_owner_can_create_pinned_note(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.pinned-notes.store', $room->slug), [
                'title' => 'Opening Post',
                'category' => RoomPinnedNote::CATEGORY_ANNOUNCEMENTS,
                'body' => 'Treat this room as canon unless superseded here.',
                'expires_at' => '2026-07-01',
                'accent_color' => RoomPinnedNote::ACCENT_GREEN,
            ])
            ->assertOk()
            ->assertJsonPath('note.status', RoomPinnedNote::STATUS_ACTIVE)
            ->assertJsonPath('note.author_character.name', 'Owner')
            ->assertJsonPath('note.accent_color', RoomPinnedNote::ACCENT_GREEN);

        $this->assertDatabaseHas('room_pinned_notes', [
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Opening Post',
            'category' => RoomPinnedNote::CATEGORY_ANNOUNCEMENTS,
            'accent_color' => RoomPinnedNote::ACCENT_GREEN,
            'status' => RoomPinnedNote::STATUS_ACTIVE,
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.pinned-notes.store', $room->slug), [
                'title' => 'Unauthorized',
                'category' => RoomPinnedNote::CATEGORY_OTHER,
                'body' => 'Players should not be able to post this.',
            ])
            ->assertForbidden();
    }

    public function test_moderator_can_edit_and_archive_any_pinned_note(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter('Moderator');
        [$authorUser, $authorCharacter] = $this->createUserWithCharacter('Author');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->addModerator($room, $moderatorCharacter);

        $note = RoomPinnedNote::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'title' => 'Scene Recap',
            'category' => RoomPinnedNote::CATEGORY_SESSION_RECAPS,
            'body' => 'Original recap text.',
            'accent_color' => RoomPinnedNote::ACCENT_ORANGE,
            'status' => RoomPinnedNote::STATUS_ACTIVE,
        ]);

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->patchJson(route('rooms.pinned-notes.update', [$room->slug, $note]), [
                'title' => 'Scene Recap',
                'category' => RoomPinnedNote::CATEGORY_SESSION_RECAPS,
                'body' => 'Moderator archived this recap.',
                'expires_at' => '',
                'accent_color' => 'default',
                'status' => RoomPinnedNote::STATUS_ARCHIVED,
            ])
            ->assertOk()
            ->assertJsonPath('note.status', RoomPinnedNote::STATUS_ARCHIVED)
            ->assertJsonPath('note.accent_color', null);

        $this->assertDatabaseHas('room_pinned_notes', [
            'id' => $note->id,
            'body' => 'Moderator archived this recap.',
            'accent_color' => null,
            'status' => RoomPinnedNote::STATUS_ARCHIVED,
        ]);
    }

    public function test_regular_participant_cannot_edit_or_delete_pinned_notes(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $note = RoomPinnedNote::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Standing Order',
            'category' => RoomPinnedNote::CATEGORY_ANNOUNCEMENTS,
            'body' => 'Original standing order.',
            'status' => RoomPinnedNote::STATUS_ACTIVE,
        ]);

        $payload = [
            'title' => 'Changed',
            'category' => RoomPinnedNote::CATEGORY_ANNOUNCEMENTS,
            'body' => 'Changed body.',
            'expires_at' => '',
            'accent_color' => RoomPinnedNote::ACCENT_PURPLE,
            'status' => RoomPinnedNote::STATUS_ARCHIVED,
        ];

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->patchJson(route('rooms.pinned-notes.update', [$room->slug, $note]), $payload)
            ->assertForbidden();

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->deleteJson(route('rooms.pinned-notes.destroy', [$room->slug, $note]))
            ->assertForbidden();
    }

    public function test_soft_delete_is_used_for_pinned_note_removal(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $note = RoomPinnedNote::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Temporary Bulletin',
            'category' => RoomPinnedNote::CATEGORY_EVENTS,
            'body' => 'Soon to be removed.',
            'status' => RoomPinnedNote::STATUS_ACTIVE,
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->deleteJson(route('rooms.pinned-notes.destroy', [$room->slug, $note]))
            ->assertOk();

        $this->assertSoftDeleted('room_pinned_notes', [
            'id' => $note->id,
        ]);
    }

    private function createUserWithCharacter(string $name = 'Character'): array
    {
        $user = User::factory()->create();

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
            'name' => 'Room ' . Str::random(8),
            'slug' => 'room-' . Str::random(16),
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
            'role' => 'moderator',
        ]);
    }
}
