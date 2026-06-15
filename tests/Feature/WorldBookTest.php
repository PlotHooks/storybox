<?php

namespace Tests\Feature;

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
