<?php

namespace Tests\Feature;

use App\Models\ArchivedWorldBook;
use App\Models\Character;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomParticipationStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoomRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_account_cannot_exceed_active_room_cap(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(5));
        $this->createRoom($user, $character);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->from(route('rooms.index'))
            ->post(route('rooms.store'), [
                'name' => 'Second Room',
                'description' => 'Should fail.',
            ])
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasErrors('room_limit');
    }

    public function test_mature_account_gets_higher_room_cap(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(31));

        for ($i = 0; $i < 9; $i++) {
            $this->createRoom($user, $character, ['name' => 'Existing Room ' . $i]);
        }

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->post(route('rooms.store'), [
                'name' => 'Tenth Room',
                'description' => 'Allowed for mature users.',
            ])
            ->assertRedirect();

        $this->assertSame(10, Room::query()->where('created_by', $user->id)->where('type', Room::TYPE_PUBLIC)->count());

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->from(route('rooms.index'))
            ->post(route('rooms.store'), [
                'name' => 'Eleventh Room',
                'description' => 'Should fail.',
            ])
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasErrors('room_limit');
    }

    public function test_inactive_new_account_room_expires_after_24_hours(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(5));
        $room = $this->createRoom($user, $character, [
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);

        $this->artisan('retention:expire-inactive-rooms --limit=500')->assertSuccessful();

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
    }

    public function test_inactive_mature_account_room_expires_after_3_days(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(31));
        $room = $this->createRoom($user, $character, [
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        $this->artisan('retention:expire-inactive-rooms --limit=500')->assertSuccessful();

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
    }

    public function test_posting_resets_room_activity_timer(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(31));
        $room = $this->createRoom($user, $character, [
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.messages.store', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $this->issueParticipationToken($room, $character),
                'body' => 'Fresh activity.',
            ])
            ->assertOk();

        $room = $room->fresh();
        $this->assertNotNull($room->last_posted_at);

        $this->artisan('retention:expire-inactive-rooms --limit=500')->assertSuccessful();

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'deleted_at' => null,
        ]);
    }

    public function test_presence_view_and_join_do_not_reset_timer(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(31));
        $room = $this->createRoom($user, $character, [
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->get(route('rooms.show', $room->slug))
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['active_character_id' => $character->id])
            ->postJson(route('rooms.presence', $room->slug), [
                'character_id' => $character->id,
                'room_participation_token' => $this->issueParticipationToken($room, $character),
            ])
            ->assertOk();

        $room = $room->fresh();
        $this->assertNull($room->last_posted_at);

        $this->artisan('retention:expire-inactive-rooms --limit=500')->assertSuccessful();

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
    }

    public function test_dms_are_ignored_by_room_cleanup(): void
    {
        [$firstUser, $firstCharacter] = $this->createUserWithCharacter(now()->subDays(31));
        [$secondUser, $secondCharacter] = $this->createUserWithCharacter(now()->subDays(31));
        $room = $this->createDmRoom($firstUser, $firstCharacter, $secondUser, $secondCharacter, [
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $room->delete();
        $room->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

        $this->artisan('retention:expire-inactive-rooms --limit=500')->assertSuccessful();
        $this->artisan('retention:hard-delete-expired-rooms --limit=500')->assertSuccessful();

        $this->assertNotNull(Room::withTrashed()->find($room->id));
    }

    public function test_soft_deleted_rooms_remain_recoverable_for_30_days(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(31));
        $room = $this->createRoom($user, $character);

        $room->delete();
        $room->forceFill(['deleted_at' => now()->subDays(29)])->saveQuietly();

        $this->artisan('retention:hard-delete-expired-rooms --limit=500')->assertSuccessful();

        $this->assertNotNull(Room::withTrashed()->find($room->id));
    }

    public function test_rooms_past_recovery_window_are_hard_deleted(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(31));
        $room = $this->createRoom($user, $character);
        $message = $this->createMessage($room, $user, $character, 'Cascade me.');

        $room->delete();
        $room->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

        $this->artisan('retention:hard-delete-expired-rooms --limit=500')->assertSuccessful();

        $this->assertNull(Room::withTrashed()->find($room->id));
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_hard_delete_archives_world_book_entries_before_room_force_delete(): void
    {
        [$user, $ownerCharacter] = $this->createUserWithCharacter(now()->subDays(31));
        [, $authorCharacter] = $this->createUserWithCharacter(now()->subDays(31));
        [, $linkedCharacter] = $this->createUserWithCharacter(now()->subDays(31));
        $room = $this->createRoom($user, $ownerCharacter);

        $publishedEntryId = DB::table('world_book_entries')->insertGetId([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'reviewed_by_character_id' => $ownerCharacter->id,
            'linked_character_id' => $linkedCharacter->id,
            'draft_linked_character_id' => null,
            'status' => 'published',
            'sort_order' => 2,
            'title' => 'Glass Harbor',
            'category' => 'location',
            'image_url' => 'https://cdn.example.com/glass-harbor.png',
            'body' => 'A harbor lined with mirrored breakwaters.',
            'tags' => json_encode(['harbor', 'trade']),
            'draft_title' => null,
            'draft_category' => null,
            'draft_image_url' => null,
            'draft_body' => null,
            'draft_tags' => null,
            'published_at' => now()->subDays(10),
            'reviewed_at' => now()->subDays(9),
            'rejection_note' => null,
            'rejected_at' => null,
            'created_at' => now()->subDays(12),
            'updated_at' => now()->subDays(8),
            'deleted_at' => null,
        ]);

        $pendingEntryId = DB::table('world_book_entries')->insertGetId([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'reviewed_by_character_id' => null,
            'linked_character_id' => null,
            'draft_linked_character_id' => $linkedCharacter->id,
            'status' => 'pending',
            'sort_order' => 5,
            'title' => null,
            'category' => null,
            'image_url' => null,
            'body' => null,
            'tags' => null,
            'draft_title' => 'Unmapped Shrine',
            'draft_category' => 'map',
            'draft_image_url' => 'https://cdn.example.com/unmapped-shrine.png',
            'draft_body' => 'A draft map of the ruined shrine.',
            'draft_tags' => json_encode(['shrine', 'draft']),
            'published_at' => null,
            'reviewed_at' => null,
            'rejection_note' => null,
            'rejected_at' => null,
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subDays(6),
            'deleted_at' => null,
        ]);

        $room->delete();
        $room->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

        $this->artisan('retention:hard-delete-expired-rooms --limit=500')->assertSuccessful();

        $archive = ArchivedWorldBook::query()->where('original_room_id', $room->id)->first();

        $this->assertNotNull($archive);
        $this->assertSame($user->id, $archive->owner_user_id);
        $this->assertSame($room->name, $archive->original_room_name);
        $this->assertSame(2, $archive->entry_count);
        $this->assertNotEmpty($archive->recovery_key);
        $this->assertNull(Room::withTrashed()->find($room->id));

        $this->assertDatabaseHas('archived_world_book_entries', [
            'archived_world_book_id' => $archive->id,
            'source_world_book_entry_id' => $publishedEntryId,
            'status' => 'published',
            'title' => 'Glass Harbor',
            'category' => 'location',
            'body' => 'A harbor lined with mirrored breakwaters.',
            'sort_order' => 2,
        ]);

        $this->assertDatabaseHas('archived_world_book_entries', [
            'archived_world_book_id' => $archive->id,
            'source_world_book_entry_id' => $pendingEntryId,
            'status' => 'pending',
            'draft_title' => 'Unmapped Shrine',
            'draft_category' => 'map',
            'draft_body' => 'A draft map of the ruined shrine.',
            'sort_order' => 5,
        ]);

        $archivedPublished = DB::table('archived_world_book_entries')
            ->where('archived_world_book_id', $archive->id)
            ->where('source_world_book_entry_id', $publishedEntryId)
            ->first();

        $archivedPending = DB::table('archived_world_book_entries')
            ->where('archived_world_book_id', $archive->id)
            ->where('source_world_book_entry_id', $pendingEntryId)
            ->first();

        $this->assertSame(['harbor', 'trade'], json_decode((string) $archivedPublished->tags, true));
        $this->assertSame(['shrine', 'draft'], json_decode((string) $archivedPending->draft_tags, true));
    }

    public function test_hard_delete_archive_is_idempotent_when_archive_row_already_exists(): void
    {
        [$user, $ownerCharacter] = $this->createUserWithCharacter(now()->subDays(31));
        [, $authorCharacter] = $this->createUserWithCharacter(now()->subDays(31));
        $room = $this->createRoom($user, $ownerCharacter);

        $entryId = DB::table('world_book_entries')->insertGetId([
            'room_id' => $room->id,
            'author_character_id' => $authorCharacter->id,
            'reviewed_by_character_id' => $ownerCharacter->id,
            'linked_character_id' => null,
            'draft_linked_character_id' => null,
            'status' => 'published',
            'sort_order' => 1,
            'title' => 'First Archive',
            'category' => 'lore',
            'image_url' => null,
            'body' => 'Original archived text.',
            'tags' => json_encode(['original']),
            'draft_title' => null,
            'draft_category' => null,
            'draft_image_url' => null,
            'draft_body' => null,
            'draft_tags' => null,
            'published_at' => now()->subDays(4),
            'reviewed_at' => now()->subDays(4),
            'rejection_note' => null,
            'rejected_at' => null,
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
            'deleted_at' => null,
        ]);

        $existingArchive = ArchivedWorldBook::create([
            'owner_user_id' => $user->id,
            'original_room_id' => $room->id,
            'original_room_name' => 'Old Name',
            'room_deleted_at' => now()->subDays(60),
            'entry_count' => 99,
            'recovery_key' => 'existing-recovery-key',
            'archived_at' => now()->subDays(60),
        ]);

        $room->delete();
        $room->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

        $this->artisan('retention:hard-delete-expired-rooms --limit=500')->assertSuccessful();

        $existingArchive->refresh();

        $this->assertSame('existing-recovery-key', $existingArchive->recovery_key);
        $this->assertSame($room->name, $existingArchive->original_room_name);
        $this->assertSame(1, $existingArchive->entry_count);
        $this->assertSame(1, ArchivedWorldBook::query()->where('original_room_id', $room->id)->count());
        $this->assertSame(1, DB::table('archived_world_book_entries')->where('archived_world_book_id', $existingArchive->id)->count());
        $this->assertDatabaseHas('archived_world_book_entries', [
            'archived_world_book_id' => $existingArchive->id,
            'source_world_book_entry_id' => $entryId,
            'title' => 'First Archive',
            'body' => 'Original archived text.',
        ]);
    }

    public function test_dry_run_does_not_delete_anything(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(31));
        $activeRoom = $this->createRoom($user, $character, [
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);
        $deletedRoom = $this->createRoom($user, $character);
        $deletedRoom->delete();
        $deletedRoom->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

        $this->artisan('retention:expire-inactive-rooms --dry-run --limit=500')->assertSuccessful();
        $this->artisan('retention:hard-delete-expired-rooms --dry-run --limit=500')->assertSuccessful();

        $this->assertDatabaseHas('rooms', [
            'id' => $activeRoom->id,
            'deleted_at' => null,
        ]);
        $this->assertNotNull(Room::withTrashed()->find($deletedRoom->id));
    }

    public function test_limit_option_caps_processed_rooms(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(31));
        $this->createRoom($user, $character, ['created_at' => now()->subDays(4), 'updated_at' => now()->subDays(4)]);
        $this->createRoom($user, $character, ['created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)]);

        $this->artisan('retention:expire-inactive-rooms --limit=1')->assertSuccessful();

        $softDeletedCount = Room::withTrashed()->whereNotNull('deleted_at')->count();
        $this->assertSame(1, $softDeletedCount);
    }

    public function test_repeated_cleanup_runs_are_safe(): void
    {
        [$user, $character] = $this->createUserWithCharacter(now()->subDays(31));
        $room = $this->createRoom($user, $character, [
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        $this->artisan('retention:expire-inactive-rooms --limit=500')->assertSuccessful();
        $firstDeletedAt = Room::withTrashed()->findOrFail($room->id)->deleted_at;

        $this->artisan('retention:expire-inactive-rooms --limit=500')->assertSuccessful();

        $room = Room::withTrashed()->findOrFail($room->id);
        $this->assertTrue($room->trashed());
        $this->assertTrue($room->deleted_at->equalTo($firstDeletedAt));

        $room->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

        $this->artisan('retention:hard-delete-expired-rooms --limit=500')->assertSuccessful();
        $this->artisan('retention:hard-delete-expired-rooms --limit=500')->assertSuccessful();

        $this->assertNull(Room::withTrashed()->find($room->id));
    }

    private function issueParticipationToken(Room $room, Character $character): string
    {
        return app(RoomParticipationStateService::class)->issueToken($room, $character);
    }

    private function createUserWithCharacter(?Carbon $createdAt = null): array
    {
        $user = User::factory()->create([
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);

        return [$user, $this->createCharacter($user)];
    }

    private function createCharacter(User $user, ?string $name = null): Character
    {
        return Character::create([
            'user_id' => $user->id,
            'name' => $name ?? 'Character ' . Str::random(8),
            'slug' => 'character-' . Str::random(16),
        ]);
    }

    private function createRoom(User $user, ?Character $ownerCharacter = null, array $overrides = []): Room
    {
        $timestamps = [
            'created_at' => $overrides['created_at'] ?? null,
            'updated_at' => $overrides['updated_at'] ?? null,
            'last_posted_at' => $overrides['last_posted_at'] ?? null,
        ];

        unset($overrides['created_at'], $overrides['updated_at'], $overrides['last_posted_at']);

        $room = Room::create(array_merge([
            'name' => 'Room ' . Str::random(8),
            'slug' => 'room-' . Str::random(16),
            'description' => 'Test room',
            'user_id' => $user->id,
            'created_by' => $user->id,
            'type' => Room::TYPE_PUBLIC,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $ownerCharacter?->id,
        ], $overrides));

        $updates = array_filter($timestamps, fn ($value) => $value !== null);
        if ($updates !== []) {
            $room->forceFill($updates)->saveQuietly();
        }

        return $room->fresh();
    }

    private function createDmRoom(User $firstUser, Character $firstCharacter, User $secondUser, Character $secondCharacter, array $overrides = []): Room
    {
        $timestamps = [
            'created_at' => $overrides['created_at'] ?? null,
            'updated_at' => $overrides['updated_at'] ?? null,
        ];

        unset($overrides['created_at'], $overrides['updated_at']);

        $room = Room::create(array_merge([
            'name' => 'DM',
            'slug' => 'dm-' . Str::random(16),
            'user_id' => $firstUser->id,
            'created_by' => $firstUser->id,
            'type' => Room::TYPE_DM,
            'visibility' => Room::VISIBILITY_PUBLIC,
            'dm_key' => Room::normalizedDmKey($firstCharacter->id, $secondCharacter->id),
        ], $overrides));

        $updates = array_filter($timestamps, fn ($value) => $value !== null);
        if ($updates !== []) {
            $room->forceFill($updates)->saveQuietly();
        }

        $createdAt = $timestamps['created_at'] ?? now();
        $updatedAt = $timestamps['updated_at'] ?? $createdAt;

        DB::table('dm_participants')->insert([
            [
                'room_id' => $room->id,
                'user_id' => $firstUser->id,
                'character_id' => $firstCharacter->id,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ],
            [
                'room_id' => $room->id,
                'user_id' => $secondUser->id,
                'character_id' => $secondCharacter->id,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ],
        ]);

        return $room->fresh();
    }

    private function createMessage(Room $room, User $user, Character $character, string $body): Message
    {
        return Message::create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'body' => $body,
        ]);
    }
}
