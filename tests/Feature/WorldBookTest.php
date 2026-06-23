<?php

namespace Tests\Feature;

use App\Models\ArchivedWorldBook;
use App\Models\Character;
use App\Models\Room;
use App\Models\User;
use App\Models\WorldBookEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorldBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_category_exists_in_world_book_index(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk();

        $mapCategory = collect($response->json('categories'))->firstWhere('key', WorldBookEntry::CATEGORY_MAP);

        $this->assertNotNull($mapCategory);
        $this->assertSame('Map', $mapCategory['label']);
    }

    public function test_regular_participant_can_submit_pending_map_entry(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$participantUser, $participantCharacter] = $this->createUserWithCharacter('Participant');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($participantUser)
            ->withSession(['active_character_id' => $participantCharacter->id])
            ->postJson(route('rooms.world-book.store', $room->slug), [
                'title' => 'Trade Roads of Ardent Reach',
                'category' => WorldBookEntry::CATEGORY_MAP,
                'image_url' => 'https://cdn.example.com/maps/ardent-reach.png',
                'tags_input' => 'roads, trade, overland',
                'body' => 'Primary caravan paths and river crossings across the Reach.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('world_book_entries', [
            'room_id' => $room->id,
            'author_character_id' => $participantCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'Trade Roads of Ardent Reach',
            'draft_category' => WorldBookEntry::CATEGORY_MAP,
            'draft_image_url' => 'https://cdn.example.com/maps/ardent-reach.png',
        ]);
    }

    public function test_map_entries_render_image_url_and_preserve_normal_approval_workflow(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$participantUser, $participantCharacter] = $this->createUserWithCharacter('Participant');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $participantCharacter->id,
            'status' => WorldBookEntry::STATUS_PENDING,
            'draft_title' => 'Underdeep Survey',
            'draft_category' => WorldBookEntry::CATEGORY_MAP,
            'draft_image_url' => 'https://cdn.example.com/maps/underdeep.jpg',
            'draft_body' => 'A stitched survey of the lower tunnels.',
            'draft_tags' => ['tunnels', 'survey'],
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.world-book.approve', [$room->slug, $entry]))
            ->assertOk();

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk()
            ->assertJsonPath('entries.0.category', WorldBookEntry::CATEGORY_MAP)
            ->assertJsonPath('entries.0.image_url', 'https://cdn.example.com/maps/underdeep.jpg')
            ->assertJsonPath('entries.0.published.image_url', 'https://cdn.example.com/maps/underdeep.jpg')
            ->assertJsonPath('entries.0.published.body', 'A stitched survey of the lower tunnels.');

        $this->assertDatabaseHas('world_book_entries', [
            'id' => $entry->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'category' => WorldBookEntry::CATEGORY_MAP,
            'image_url' => 'https://cdn.example.com/maps/underdeep.jpg',
        ]);
    }

    public function test_map_entries_preserve_manual_ordering_and_character_activity_sorting_stays_isolated(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$activeLinkedUser, $activeLinkedCharacter] = $this->createUserWithCharacter('Active Scout');
        [$quietLinkedUser, $quietLinkedCharacter] = $this->createUserWithCharacter('Quiet Scout');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $firstMap = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Northern Reach',
            'category' => WorldBookEntry::CATEGORY_MAP,
            'image_url' => 'https://cdn.example.com/maps/north.png',
            'body' => 'Northern routes and holdfasts.',
            'sort_order' => 1,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $secondMap = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Southern Reach',
            'category' => WorldBookEntry::CATEGORY_MAP,
            'image_url' => 'https://cdn.example.com/maps/south.png',
            'body' => 'Southern roads and ferries.',
            'sort_order' => 2,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $activeCharacterEntry = $this->createPublishedCharacterWorldBookEntry($room, $ownerCharacter, $activeLinkedCharacter, 'Active character note');
        $quietCharacterEntry = $this->createPublishedCharacterWorldBookEntry($room, $ownerCharacter, $quietLinkedCharacter, 'Quiet character note');

        $this->insertMessage($room, $activeLinkedCharacter, $activeLinkedUser, 'Newest report', '2026-06-15 12:00:00');
        $this->insertMessage($room, $quietLinkedCharacter, $quietLinkedUser, 'Older report', '2026-06-15 10:00:00');

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk();

        $entries = collect($response->json('entries'));
        $mapEntries = $entries->where('category', WorldBookEntry::CATEGORY_MAP)->pluck('id')->values()->all();
        $characterEntries = $entries->where('category', WorldBookEntry::CATEGORY_CHARACTER)->pluck('id')->values()->all();

        $this->assertSame([$firstMap->id, $secondMap->id], $mapEntries);
        $this->assertSame([$activeCharacterEntry->id, $quietCharacterEntry->id], $characterEntries);
    }

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



    public function test_world_book_index_exposes_owned_characters_for_character_entry_dropdown(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$user, $firstOwnedCharacter] = $this->createUserWithCharacter('First Owned');
        $secondOwnedCharacter = $this->createCharacter($user, 'Second Owned', 'https://cdn.example.com/second.png');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Other User');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $response = $this->actingAs($user)
            ->withSession(['active_character_id' => $firstOwnedCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk();

        $ownedCharacters = collect($response->json('owned_characters'));

        $this->assertSame(
            [$firstOwnedCharacter->id, $secondOwnedCharacter->id],
            $ownedCharacters->pluck('id')->all()
        );
        $this->assertFalse($ownedCharacters->pluck('id')->contains($otherCharacter->id));
    }

    public function test_character_submission_rejects_linking_another_users_character_id(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$submitterUser, $submitterCharacter] = $this->createUserWithCharacter('Submitter');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Other Character');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($submitterUser)
            ->withSession(['active_character_id' => $submitterCharacter->id])
            ->postJson(route('rooms.world-book.store', $room->slug), [
                'category' => WorldBookEntry::CATEGORY_CHARACTER,
                'linked_character_id' => $otherCharacter->id,
                'tags_input' => 'watchlist',
                'body' => 'Current status note.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['linked_character_id']);

        $this->assertDatabaseCount('world_book_entries', 0);
    }

    public function test_character_submission_requires_owned_linked_character_id(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$submitterUser, $submitterCharacter] = $this->createUserWithCharacter('Submitter');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $this->actingAs($submitterUser)
            ->withSession(['active_character_id' => $submitterCharacter->id])
            ->postJson(route('rooms.world-book.store', $room->slug), [
                'category' => WorldBookEntry::CATEGORY_CHARACTER,
                'tags_input' => 'watchlist',
                'body' => 'Current status note.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['linked_character_id']);
    }

    public function test_character_entry_renders_linked_character_card_and_profile_data(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$linkedUser, $linkedCharacter] = $this->createUserWithCharacter('Leaf', 'https://cdn.example.com/leaf.png');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'category' => WorldBookEntry::CATEGORY_CHARACTER,
            'body' => 'Current Status: Missing in action.',
            'tags' => ['watchlist'],
            'linked_character_id' => $linkedCharacter->id,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk()
            ->assertJsonPath('entries.0.id', $entry->id)
            ->assertJsonPath('entries.0.category', WorldBookEntry::CATEGORY_CHARACTER)
            ->assertJsonPath('entries.0.title', 'Leaf')
            ->assertJsonPath('entries.0.linked_character.id', $linkedCharacter->id)
            ->assertJsonPath('entries.0.linked_character.avatar_url', 'https://cdn.example.com/leaf.png')
            ->assertJsonPath('entries.0.linked_character.card_url', route('characters.show', $linkedCharacter))
            ->assertJsonPath('entries.0.linked_character.profile_url', route('characters.profile.show', $linkedCharacter))
            ->assertJsonPath('entries.0.published.linked_character.handle', $linkedCharacter->public_handle)
            ->assertJsonPath('entries.0.published.body', 'Current Status: Missing in action.');
    }

    public function test_character_category_orders_entries_by_most_recent_room_activity(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$leafUser, $leafCharacter] = $this->createUserWithCharacter('Leaf');
        [$israelUser, $israelCharacter] = $this->createUserWithCharacter('Israel Beach');
        [$lockhielUser, $lockhielCharacter] = $this->createUserWithCharacter('Lockhiel');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $leafEntry = $this->createPublishedCharacterWorldBookEntry($room, $ownerCharacter, $leafCharacter, 'Plot lead');
        $israelEntry = $this->createPublishedCharacterWorldBookEntry($room, $ownerCharacter, $israelCharacter, 'Harbor contact');
        $lockhielEntry = $this->createPublishedCharacterWorldBookEntry($room, $ownerCharacter, $lockhielCharacter, 'Court observer');

        $this->insertMessage($room, $leafCharacter, $leafUser, 'Most recent post', '2026-06-15 12:00:00');
        $this->insertMessage($room, $israelCharacter, $israelUser, 'Earlier post', '2026-06-15 11:00:00');
        $this->insertMessage($room, $lockhielCharacter, $lockhielUser, 'Oldest post', '2026-06-15 10:00:00');

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk();

        $entries = collect($response->json('entries'));

        $this->assertSame([
            $leafEntry->id,
            $israelEntry->id,
            $lockhielEntry->id,
        ], $entries->pluck('id')->all());

        $this->assertArrayNotHasKey('last_posted_at', $entries->first());
    }

    public function test_character_search_text_includes_linked_name_notes_and_tags(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$linkedUser, $linkedCharacter] = $this->createUserWithCharacter('Lockhiel');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'category' => WorldBookEntry::CATEGORY_CHARACTER,
            'body' => 'Relationship Notes: owes the harbor guild a favor.',
            'tags' => ['plot-relevance', 'debt'],
            'linked_character_id' => $linkedCharacter->id,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk();

        $searchText = collect($response->json('entries'))->firstWhere('id', $entry->id)['search_text'];

        $this->assertStringContainsStringIgnoringCase('lockhiel', $searchText);
        $this->assertStringContainsStringIgnoringCase('harbor guild', $searchText);
        $this->assertStringContainsStringIgnoringCase('plot-relevance', $searchText);
    }

    public function test_non_character_categories_keep_curated_sorting_when_character_entries_exist(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        [$linkedUser, $linkedCharacter] = $this->createUserWithCharacter('Leaf');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $locationSecond = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Wormwood',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'Location body.',
            'sort_order' => 2,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $locationFirst = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'The World',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'Location body.',
            'sort_order' => 1,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $characterEntry = $this->createPublishedCharacterWorldBookEntry($room, $ownerCharacter, $linkedCharacter, 'Major player');

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk();

        $entries = collect($response->json('entries'));
        $locationEntries = $entries->where('category', WorldBookEntry::CATEGORY_LOCATION)->pluck('id')->values()->all();

        $this->assertSame([$locationFirst->id, $locationSecond->id], $locationEntries);
        $this->assertContains($characterEntry->id, $entries->pluck('id')->all());
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

    public function test_index_orders_entries_by_category_then_sort_order_then_title(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $faction = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'White Hat Testers',
            'category' => WorldBookEntry::CATEGORY_FACTION,
            'body' => 'Faction body.',
            'sort_order' => 1,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $locationB = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Wormwood',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'Location body.',
            'sort_order' => 2,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $locationA = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'The World',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'Location body.',
            'sort_order' => 1,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk();
        $this->assertSame([
            $locationA->id,
            $locationB->id,
            $faction->id,
        ], collect($response->json('entries'))->pluck('id')->all());
    }

    public function test_manager_can_move_published_entries_within_the_same_category_only(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $first = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'The World',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'First location.',
            'sort_order' => 1,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $second = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Wormwood',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'Second location.',
            'sort_order' => 2,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $otherCategory = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Captain Valther',
            'category' => WorldBookEntry::CATEGORY_NPC,
            'body' => 'NPC body.',
            'sort_order' => 1,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.world-book.move', [$room->slug, $second]), [
                'direction' => 'up',
            ])
            ->assertOk();

        $first->refresh();
        $second->refresh();
        $otherCategory->refresh();

        $this->assertSame(2, $first->sort_order);
        $this->assertSame(1, $second->sort_order);
        $this->assertSame(1, $otherCategory->sort_order);

        $response = $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug));

        $response->assertOk();
        $this->assertSame([
            $second->id,
            $first->id,
            $otherCategory->id,
        ], collect($response->json('entries'))->pluck('id')->all());
    }

    public function test_non_manager_cannot_move_world_book_entries(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$viewerUser, $viewerCharacter] = $this->createUserWithCharacter('Viewer');
        $room = $this->createRoom($ownerUser, $ownerCharacter);

        $entry = WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'The World',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'Location body.',
            'sort_order' => 1,
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($viewerUser)
            ->withSession(['active_character_id' => $viewerCharacter->id])
            ->postJson(route('rooms.world-book.move', [$room->slug, $entry]), [
                'direction' => 'down',
            ])
            ->assertForbidden();
    }

    public function test_world_book_index_exposes_archive_recovery_only_to_true_room_owner_for_empty_room(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter('Moderator');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Other');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->addModerator($room, $moderatorCharacter);
        $archive = $this->createArchivedWorldBook($ownerUser, [[
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Owner Archive',
            'category' => WorldBookEntry::CATEGORY_LORE,
            'body' => 'Lore body.',
        ]]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug))
            ->assertOk()
            ->assertJsonPath('archive_recovery.can_recover', true)
            ->assertJsonPath('archive_recovery.available_archives.0.recovery_key', $archive->recovery_key);

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug))
            ->assertOk()
            ->assertJsonPath('archive_recovery.can_recover', false)
            ->assertJsonCount(0, 'archive_recovery.available_archives');

        $this->actingAs($otherUser)
            ->withSession(['active_character_id' => $otherCharacter->id])
            ->getJson(route('rooms.world-book.index', $room->slug))
            ->assertOk()
            ->assertJsonPath('archive_recovery.can_recover', false)
            ->assertJsonCount(0, 'archive_recovery.available_archives');
    }

    public function test_owner_can_preview_their_archived_world_book(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $archive = $this->createArchivedWorldBook($ownerUser, [
            [
                'status' => WorldBookEntry::STATUS_PUBLISHED,
                'sort_order' => 1,
                'title' => 'Harbor Ledger',
                'category' => WorldBookEntry::CATEGORY_LOCATION,
                'body' => 'A busy harbor of ferries and ledgers.',
                'tags' => ['harbor', 'trade'],
                'published_at' => now()->subDays(10),
                'reviewed_at' => now()->subDays(9),
            ],
            [
                'status' => WorldBookEntry::STATUS_REJECTED,
                'sort_order' => 2,
                'draft_title' => 'Storm Atlas',
                'draft_category' => WorldBookEntry::CATEGORY_MAP,
                'draft_body' => 'Draft chart of the storm coast.',
                'draft_tags' => ['storm', 'atlas'],
                'rejection_note' => 'Needs another pass.',
                'rejected_at' => now()->subDays(8),
            ],
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.world-book.recovery.preview', $room->slug), [
                'recovery_key' => $archive->recovery_key,
            ])
            ->assertOk()
            ->assertJsonPath('archive.recovery_key', $archive->recovery_key)
            ->assertJsonPath('archive.original_room_name', $archive->original_room_name)
            ->assertJsonCount(2, 'archive.entries')
            ->assertJsonPath('archive.entries.0.title', 'Harbor Ledger')
            ->assertJsonPath('archive.entries.1.pending.title', 'Storm Atlas');
    }

    public function test_non_owner_cannot_preview_or_import_archived_world_book(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        [$moderatorUser, $moderatorCharacter] = $this->createUserWithCharacter('Moderator');
        [$otherUser, $otherCharacter] = $this->createUserWithCharacter('Other');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $this->addModerator($room, $moderatorCharacter);

        $archive = $this->createArchivedWorldBook($ownerUser, [[
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Owner Archive',
            'category' => WorldBookEntry::CATEGORY_LORE,
            'body' => 'Secret lore.',
        ]]);

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->postJson(route('rooms.world-book.recovery.preview', $room->slug), [
                'recovery_key' => $archive->recovery_key,
            ])
            ->assertForbidden();

        $this->actingAs($moderatorUser)
            ->withSession(['active_character_id' => $moderatorCharacter->id])
            ->postJson(route('rooms.world-book.recovery.import', $room->slug), [
                'recovery_key' => $archive->recovery_key,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->withSession(['active_character_id' => $otherCharacter->id])
            ->postJson(route('rooms.world-book.recovery.preview', $room->slug), [
                'recovery_key' => $archive->recovery_key,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->withSession(['active_character_id' => $otherCharacter->id])
            ->postJson(route('rooms.world-book.recovery.import', $room->slug), [
                'recovery_key' => $archive->recovery_key,
            ])
            ->assertForbidden();
    }

    public function test_owner_can_import_archived_world_book_into_empty_room(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $archive = $this->createArchivedWorldBook($ownerUser, [[
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'sort_order' => 1,
            'title' => 'Imported Harbor',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'Imported harbor body.',
            'tags' => ['imported', 'harbor'],
            'published_at' => now()->subDays(5),
            'reviewed_at' => now()->subDays(4),
        ]]);
        $originalArchiveEntryId = $archive->entries->first()->id;

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.world-book.recovery.import', $room->slug), [
                'recovery_key' => $archive->recovery_key,
            ])
            ->assertOk()
            ->assertJsonPath('imported_count', 1);

        $this->assertDatabaseHas('world_book_entries', [
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Imported Harbor',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'Imported harbor body.',
            'linked_character_id' => null,
            'draft_linked_character_id' => null,
        ]);
        $this->assertDatabaseHas('archived_world_books', [
            'id' => $archive->id,
            'owner_user_id' => $ownerUser->id,
            'recovery_key' => $archive->recovery_key,
        ]);
        $this->assertDatabaseHas('archived_world_book_entries', [
            'id' => $originalArchiveEntryId,
            'archived_world_book_id' => $archive->id,
            'title' => 'Imported Harbor',
        ]);
        $this->assertSame(1, ArchivedWorldBook::query()->whereKey($archive->id)->count());
        $this->assertSame(1, ArchivedWorldBook::query()->findOrFail($archive->id)->entries()->count());
    }

    public function test_archive_import_is_blocked_when_destination_room_already_has_world_book_entries(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $archive = $this->createArchivedWorldBook($ownerUser, [[
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Blocked Archive',
            'category' => WorldBookEntry::CATEGORY_LOCATION,
            'body' => 'Blocked body.',
        ]]);

        WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $ownerCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => 'Existing Entry',
            'category' => WorldBookEntry::CATEGORY_LORE,
            'body' => 'Existing room lore.',
            'published_at' => now(),
            'reviewed_by_character_id' => $ownerCharacter->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.world-book.recovery.import', $room->slug), [
                'recovery_key' => $archive->recovery_key,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Recover archived World Book is only available when this room has no existing World Book entries.');

        $this->assertSame(1, WorldBookEntry::query()->where('room_id', $room->id)->count());
    }

    public function test_archive_import_preserves_title_category_body_tags_sort_status_and_draft_fields(): void
    {
        [$ownerUser, $ownerCharacter] = $this->createUserWithCharacter('Owner');
        $room = $this->createRoom($ownerUser, $ownerCharacter);
        $publishedAt = now()->subDays(12)->startOfMinute();
        $reviewedAt = now()->subDays(11)->startOfMinute();
        $rejectedAt = now()->subDays(9)->startOfMinute();

        $archive = $this->createArchivedWorldBook($ownerUser, [
            [
                'status' => WorldBookEntry::STATUS_PUBLISHED,
                'sort_order' => 2,
                'title' => 'Salt Market',
                'category' => WorldBookEntry::CATEGORY_LOCATION,
                'image_url' => 'https://cdn.example.com/salt-market.png',
                'body' => 'A market lined with salt vaults.',
                'tags' => ['salt', 'market'],
                'published_at' => $publishedAt,
                'reviewed_at' => $reviewedAt,
            ],
            [
                'status' => WorldBookEntry::STATUS_REJECTED,
                'sort_order' => 7,
                'draft_title' => 'Storm Atlas',
                'draft_category' => WorldBookEntry::CATEGORY_MAP,
                'draft_image_url' => 'https://cdn.example.com/storm-atlas.png',
                'draft_body' => 'A draft atlas of the storm coast.',
                'draft_tags' => ['storm', 'atlas'],
                'rejection_note' => 'Needs clearer landmarks.',
                'rejected_at' => $rejectedAt,
                'reviewed_at' => $reviewedAt,
            ],
        ]);

        $this->actingAs($ownerUser)
            ->withSession(['active_character_id' => $ownerCharacter->id])
            ->postJson(route('rooms.world-book.recovery.import', $room->slug), [
                'recovery_key' => $archive->recovery_key,
            ])
            ->assertOk();

        $published = WorldBookEntry::query()->where('room_id', $room->id)->where('title', 'Salt Market')->firstOrFail();
        $rejected = WorldBookEntry::query()->where('room_id', $room->id)->where('draft_title', 'Storm Atlas')->firstOrFail();

        $this->assertSame(WorldBookEntry::STATUS_PUBLISHED, $published->status);
        $this->assertSame(WorldBookEntry::CATEGORY_LOCATION, $published->category);
        $this->assertSame('A market lined with salt vaults.', $published->body);
        $this->assertSame(['salt', 'market'], $published->tags);
        $this->assertSame(2, $published->sort_order);
        $this->assertTrue($publishedAt->equalTo($published->published_at));
        $this->assertTrue($reviewedAt->equalTo($published->reviewed_at));
        $this->assertNull($published->linked_character_id);
        $this->assertNull($published->draft_linked_character_id);

        $this->assertSame(WorldBookEntry::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Storm Atlas', $rejected->draft_title);
        $this->assertSame(WorldBookEntry::CATEGORY_MAP, $rejected->draft_category);
        $this->assertSame('A draft atlas of the storm coast.', $rejected->draft_body);
        $this->assertSame(['storm', 'atlas'], $rejected->draft_tags);
        $this->assertSame('Needs clearer landmarks.', $rejected->rejection_note);
        $this->assertSame(7, $rejected->sort_order);
        $this->assertTrue($rejectedAt->equalTo($rejected->rejected_at));
        $this->assertNull($rejected->linked_character_id);
        $this->assertNull($rejected->draft_linked_character_id);
    }

    private function createUserWithCharacter(string $name = 'Character', ?string $avatar = null): array
    {
        $user = User::factory()->create();

        return [$user, $this->createCharacter($user, $name, $avatar)];
    }

    private function createCharacter(User $user, string $name, ?string $avatar = null): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => 'character-' . Str::random(16),
            'avatar' => $avatar,
        ]);
    }

    private function createPublishedCharacterWorldBookEntry(Room $room, Character $authorCharacter, Character $linkedCharacter, ?string $notes = null): WorldBookEntry
    {
        return WorldBookEntry::create([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'category' => WorldBookEntry::CATEGORY_CHARACTER,
            'body' => $notes,
            'linked_character_id' => $linkedCharacter->id,
            'published_at' => now(),
            'reviewed_by_character_id' => $authorCharacter->id,
            'reviewed_at' => now(),
        ]);
    }

    private function createArchivedWorldBook(User $ownerUser, array $entries, array $overrides = []): ArchivedWorldBook
    {
        $archive = ArchivedWorldBook::create(array_merge([
            'owner_user_id' => $ownerUser->id,
            'original_room_id' => (int) (microtime(true) * 1000000) + random_int(1, 999),
            'original_room_name' => 'Archived Room ' . Str::random(8),
            'room_deleted_at' => now()->subDays(40),
            'entry_count' => count($entries),
            'recovery_key' => 'recovery-' . Str::random(24),
            'archived_at' => now()->subDays(39),
        ], $overrides));

        foreach (array_values($entries) as $index => $entry) {
            $archive->entries()->create(array_merge([
                'source_world_book_entry_id' => ($archive->id * 1000) + $index + 1,
                'status' => WorldBookEntry::STATUS_PENDING,
                'sort_order' => $index + 1,
                'title' => null,
                'category' => null,
                'image_url' => null,
                'body' => null,
                'tags' => [],
                'draft_title' => null,
                'draft_category' => null,
                'draft_image_url' => null,
                'draft_body' => null,
                'draft_tags' => [],
                'published_at' => null,
                'reviewed_at' => null,
                'rejection_note' => null,
                'rejected_at' => null,
            ], $entry));
        }

        return $archive->fresh('entries');
    }

    private function insertMessage(Room $room, Character $character, User $user, string $body, string $createdAt): void
    {
        DB::table('messages')->insert([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => $body,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
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
