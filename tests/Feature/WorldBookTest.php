<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use App\Models\WorldBookEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorldBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_participant_can_view_published_entries(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'The Ember Quarter',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'An industrial district defined by forge-light and ash.',
            'tags' => ['industry', 'ash'],
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk()
            ->assertJsonPath('entries.0.id', $entry->id)
            ->assertJsonPath('entries.0.title', 'The Ember Quarter')
            ->assertJsonPath('entries.0.published.title', 'The Ember Quarter')
            ->assertJsonPath('entries.0.published.tags.0', 'industry');
    }

    public function test_regular_participant_can_submit_pending_entry_with_tags(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$participantUser, $participantCharacter] = $this->createUserWithCharacter('Participant');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($participantUser)
            ->withSession(['active_character_id' => $participantCharacter->id])
            ->postJson(route('rooms.world-book.store', $room->slug), [
                'title' => 'The Brass Archive',
                'category' => WorldBookEntry::CATEGORY_LORE,
                'image_url' => 'https://cdn.example.com/archive.png',
                'tags_input' => 'trade, moon, trade',
                'body' => 'A repository of censored city records.',
            ])
            ->assertOk();

        $entry = WorldBookEntry::query()->where('room_id', $room->id)->firstOrFail();

        $this->assertSame(['trade', 'moon'], $entry->draft_tags);
        $this->assertDatabaseHas('world_book_entries', [
            'room_id' => $room->id,
            'author_character_id' => $participantCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'The Brass Archive',
            'draft_category' => WorldBookEntry::CATEGORY_LORE,
            'draft_image_url' => 'https://cdn.example.com/archive.png',
            'title' => null,
        ]);
    }

    public function test_author_can_edit_own_pending_entry(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$participantUser, $participantCharacter] = $this->createUserWithCharacter('Participant');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $participantCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'Old Draft',
            'draft_category' => WorldBookEntry::CATEGORY_FACTION,
            'draft_body' => 'Original pending text.',
            'draft_tags' => ['old'],
        ]);

        $this->actingAs($participantUser)
            ->withSession(['active_character_id' => $participantCharacter->id])
            ->patchJson(route('rooms.world-book.update', [$room->slug, $entry]), [
                'title' => 'Updated Draft',
                'category' => WorldBookEntry::CATEGORY_FACTION,
                'image_url' => 'https://cdn.example.com/faction.png',
                'tags' => ['updated', 'council'],
                'body' => 'Updated pending text.',
            ])
            ->assertOk();

        $entry->refresh();

        $this->assertSame(['updated', 'council'], $entry->draft_tags);
        $this->assertDatabaseHas('world_book_entries', [
            'id' => $entry->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'Updated Draft',
            'draft_image_url' => 'https://cdn.example.com/faction.png',
            'draft_body' => 'Updated pending text.',
        ]);
    }

    public function test_owner_and_moderator_can_approve_and_reject_submissions_with_notes(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter('Moderator');
        [$participantUser, $participantCharacter] = $this->createUserWithCharacter('Participant');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->addModerator($room, $moderatorCharacter);

        $approveEntry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $participantCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'Moon Gate',
            'draft_category' => WorldBookEntry::CATEGORY_LOCATION,
            'draft_body' => 'A silver gate that opens only on solstice nights.',
            'draft_tags' => ['moon', 'gateway'],
        ]);

        $rejectEntry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $participantCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'False Ledger',
            'draft_category' => WorldBookEntry::CATEGORY_LORE,
            'draft_body' => 'A submission that should be rejected.',
            'draft_tags' => ['false'],
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.world-book.approve', [$room->slug, $approveEntry]))
            ->assertOk();

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->postJson(route('rooms.world-book.reject', [$room->slug, $rejectEntry]), [
                'rejection_note' => 'Conflicts with established canon.',
            ])
            ->assertOk();

        $approveEntry->refresh();
        $rejectEntry->refresh();

        $this->assertSame(['moon', 'gateway'], $approveEntry->tags);
        $this->assertDatabaseHas('world_book_entries', [
            'id' => $approveEntry->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Moon Gate',
            'draft_title' => null,
            'rejection_note' => null,
        ]);

        $this->assertSame('Conflicts with established canon.', $rejectEntry->rejection_note);
        $this->assertDatabaseHas('world_book_entries', [
            'id' => $rejectEntry->id,
            'status' => WorldBookEntry::STATUS_REJECTED,
            'draft_title' => 'False Ledger',
            'title' => null,
            'rejection_note' => 'Conflicts with established canon.',
        ]);
    }

    public function test_published_entry_remains_visible_while_a_proposed_edit_is_pending(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$authorUser, $authorCharacter] = $this->createUserWithCharacter('Author');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Old Harbor',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'The published harbor description.',
            'tags' => ['coastal', 'trade'],
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($authorUser)
            ->withSession(['active_character_id' => $authorCharacter->id])
            ->patchJson(route('rooms.world-book.update', [$room->slug, $entry]), [
                'title' => 'Old Harbor',
                'category' => WorldBookEntry::CATEGORY_LOCATION,
                'image_url' => '',
                'tags' => ['coastal', 'pirates'],
                'body' => 'A proposed revision that should wait for approval.',
            ])
            ->assertOk();

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk()
            ->assertJsonPath('entries.0.title', 'Old Harbor')
            ->assertJsonPath('entries.0.published.body', 'The published harbor description.')
            ->assertJsonPath('entries.0.published.tags.1', 'trade')
            ->assertJsonMissing(['body' => 'A proposed revision that should wait for approval.'])
            ->assertJsonMissing(['pirates']);

        $entry->refresh();
        $this->assertSame(['coastal', 'pirates'], $entry->draft_tags);
        $this->assertDatabaseHas('world_book_entries', [
            'id' => $entry->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'title' => 'Old Harbor',
            'body' => 'The published harbor description.',
            'draft_body' => 'A proposed revision that should wait for approval.',
        ]);
    }

    public function test_soft_delete_hides_entry_without_hard_deleting(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Ash Market',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'A market built in the shadow of old furnaces.',
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->deleteJson(route('rooms.world-book.destroy', [$room->slug, $entry]))
            ->assertOk();

        $this->assertSoftDeleted('world_book_entries', [
            'id' => $entry->id,
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug))
            ->assertOk()
            ->assertJsonCount(0, 'entries');
    }

    public function test_world_book_index_exposes_pending_queue_only_to_managers(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$authorUser, $authorCharacter] = $this->createUserWithCharacter('Author');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'Pending Queue Item',
            'draft_category' => WorldBookEntry::CATEGORY_NPC,
            'draft_body' => 'Queue body.',
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug))
            ->assertOk()
            ->assertJsonPath('pending_queue.0.id', $entry->id)
            ->assertJsonPath('pending_queue.0.title', 'Pending Queue Item');

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug))
            ->assertOk()
            ->assertJsonCount(0, 'pending_queue');
    }

    public function test_search_text_includes_tags_and_pending_content_for_managers(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $pending = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'Moonwell Draft',
            'draft_category' => WorldBookEntry::CATEGORY_LORE,
            'draft_body' => 'Secret moon rites.',
            'draft_tags' => ['moon', 'ritual'],
        ]);

        $published = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Red Woods',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'A forest with white bark.',
            'tags' => ['forest', 'coastal'],
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $managerResponse = $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $viewerResponse = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $managerResponse->assertOk();
        $viewerResponse->assertOk();

        $managerEntries = collect($managerResponse->json('entries'))->keyBy('id');
        $viewerEntries = collect($viewerResponse->json('entries'))->keyBy('id');

        $this->assertTrue(str_contains(strtolower((string) $managerEntries[$pending->id]['search_text']), 'moon'));
        $this->assertTrue(str_contains(strtolower((string) $viewerEntries[$published->id]['search_text']), 'coastal'));
    }

    public function test_rejection_note_is_visible_to_submitter_and_staff_but_not_other_users(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$authorUser, $authorCharacter] = $this->createUserWithCharacter('Author');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'status' => WorldBookEntry::STATUS_REJECTED,
            'draft_title' => 'Rejected Draft',
            'draft_category' => WorldBookEntry::CATEGORY_CUSTOM,
            'draft_body' => 'Rejected content.',
            'rejection_note' => 'Conflicts with established canon.',
            'rejected_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug))
            ->assertOk()
            ->assertJsonPath('entries.0.rejection_note', 'Conflicts with established canon.');

        $this->actingAs($authorUser)
            ->withSession(['active_character_id' => $authorCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug))
            ->assertOk()
            ->assertJsonPath('entries.0.rejection_note', 'Conflicts with established canon.');

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug))
            ->assertOk()
            ->assertJsonCount(0, 'entries');
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
