<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomCharacterRole;
use App\Models\RoomNotice;
use App\Models\RoomPinnedNote;
use App\Models\RoomToolRead;
use App\Models\User;
use App\Models\WorldBookEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomToolReadIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_view_includes_initial_tool_update_state_for_visible_content(): void
    {
        Carbon::setTestNow('2026-06-14 12:00:00');

        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Harbor Law',
            'category' => WorldBookEntry::CATEGORY_LORE,
            'body' => 'Published canon.',
            'published_at' => now()->subMinutes(20),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Contract Board',
            'category' => RoomNotice::CATEGORY_JOBS,
            'body' => 'Fresh work posted.',
            'status' => RoomNotice::STATUS_ACTIVE,
            'updated_at' => now()->subMinutes(10),
        ]);

        RoomPinnedNote::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Canon Note',
            'category' => RoomPinnedNote::CATEGORY_ANNOUNCEMENTS,
            'body' => 'Read this first.',
            'status' => RoomPinnedNote::STATUS_ACTIVE,
            'updated_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug));

        $response->assertOk()
            ->assertSee('"world_book":true', false)
            ->assertSee('"notice_board":true', false)
            ->assertSee('"pinned_notes":true', false);
    }

    public function test_mark_seen_endpoint_persists_last_seen_and_is_idempotent(): void
    {
        Carbon::setTestNow('2026-06-14 12:00:00');

        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.tool-reads.store', [$room->slug, RoomToolRead::TOOL_NOTICE_BOARD]))
            ->assertOk()
            ->assertJsonPath('tool', RoomToolRead::TOOL_NOTICE_BOARD);

        $this->assertDatabaseHas('room_tool_reads', [
            'user_id' => $ownerUser->id,
            'room_id' => $room->id,
            'tool' => RoomToolRead::TOOL_NOTICE_BOARD,
        ]);

        Carbon::setTestNow('2026-06-14 12:05:00');

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.tool-reads.store', [$room->slug, RoomToolRead::TOOL_NOTICE_BOARD]))
            ->assertOk();

        $this->assertSame(1, RoomToolRead::query()->count());
        $this->assertTrue(
            optional(RoomToolRead::query()->first()?->last_seen_at)->equalTo(now())
        );
    }

    public function test_regular_viewer_does_not_get_world_book_indicator_for_other_users_pending_drafts(): void
    {
        Carbon::setTestNow('2026-06-14 12:00:00');

        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$authorUser, $authorCharacter] = $this->createUserWithCharacter('Author');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'Secret Draft',
            'draft_category' => WorldBookEntry::CATEGORY_LORE,
            'draft_body' => 'Pending manager review.',
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug));

        $response->assertOk()
            ->assertSee('"world_book":false', false);
    }

    public function test_room_manager_does_get_world_book_indicator_for_pending_drafts_they_can_review(): void
    {
        Carbon::setTestNow('2026-06-14 12:00:00');

        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter('Moderator');
        [$authorUser, $authorCharacter] = $this->createUserWithCharacter('Author');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->addModerator($room, $moderatorCharacter);

        WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'Review Queue',
            'draft_category' => WorldBookEntry::CATEGORY_LORE,
            'draft_body' => 'Needs review.',
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->get(route('rooms.show', $room->slug));

        $response->assertOk()
            ->assertSee('"world_book":true', false);
    }

    public function test_archived_notice_and_archived_pinned_note_do_not_trigger_default_glow(): void
    {
        Carbon::setTestNow('2026-06-14 12:00:00');

        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Old Notice',
            'category' => RoomNotice::CATEGORY_EVENTS,
            'body' => 'Archived notice.',
            'status' => RoomNotice::STATUS_ARCHIVED,
            'updated_at' => now()->subMinutes(2),
        ]);

        RoomPinnedNote::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'title' => 'Old Note',
            'category' => RoomPinnedNote::CATEGORY_EVENTS,
            'body' => 'Archived note.',
            'status' => RoomPinnedNote::STATUS_ARCHIVED,
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->get(route('rooms.show', $room->slug));

        $response->assertOk()
            ->assertSee('"notice_board":false', false)
            ->assertSee('"pinned_notes":false', false);
    }

    private function createUserWithCharacter(string $name): array
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
            'role' => RoomCharacterRole::ROLE_MODERATOR,
        ]);
    }
}
