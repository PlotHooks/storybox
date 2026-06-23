<?php

namespace App\Http\Controllers;

use App\Models\ArchivedWorldBook;
use App\Models\Character;
use App\Models\Room;
use App\Models\WorldBookEntry;
use App\Services\RoomAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorldBookController extends Controller
{
    public function __construct(
        private readonly RoomAccessService $roomAccess,
    ) {
    }

    public function index(Request $request, Room $room): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);

        $viewerCharacter = $this->viewerCharacterForRoom($room);
        abort_unless($this->roomAccess->canViewRoom($request->user(), $room, $viewerCharacter), 403);

        $canManage = $viewerCharacter !== null
            && $this->roomAccess->canManageRoom($request->user(), $room, $viewerCharacter);
        $roomIsEmpty = ! $this->roomHasActiveWorldBookEntries($room);
        $canRecoverArchive = $this->userOwnsRoom((int) ($request->user()?->id ?? 0), $room) && $roomIsEmpty;

        $entries = WorldBookEntry::query()
            ->where('room_id', $room->id)
            ->with(['authorCharacter', 'reviewedByCharacter', 'linkedCharacter.profile', 'draftLinkedCharacter.profile'])
            ->get()
            ->filter(fn (WorldBookEntry $entry) => $this->entryVisibleToViewer($entry, $viewerCharacter, $canManage))
            ->values();

        $activityByCharacter = $this->latestRoomActivityByCharacter($room->id, $entries);

        $entries = $this->sortEntriesForIndex($entries, $activityByCharacter, $viewerCharacter, $canManage);

        $serialized = $entries
            ->map(fn (WorldBookEntry $entry) => $this->serializeEntry($entry, $viewerCharacter, $canManage))
            ->values();

        $pendingQueue = $canManage
            ? $serialized
                ->filter(fn (array $entry) => $entry['has_pending_draft'])
                ->values()
                ->map(fn (array $entry) => [
                    'id' => $entry['id'],
                    'title' => $entry['pending']['title'] ?? $entry['title'],
                    'category' => $entry['pending']['category'] ?? $entry['category'],
                    'category_label' => $entry['pending']['category_label'] ?? $entry['category_label'],
                    'category_icon' => $entry['pending']['category_icon'] ?? $entry['category_icon'],
                    'author_character' => $entry['author_character'],
                    'linked_character' => $entry['pending']['linked_character'] ?? $entry['linked_character'],
                    'updated_at' => $entry['updated_at'],
                    'status' => $entry['status'],
                ])
            : [];

        return response()->json([
            'room' => [
                'id' => $room->id,
                'slug' => $room->slug,
                'name' => $room->name,
            ],
            'categories' => collect(WorldBookEntry::categoryMeta())
                ->map(fn (array $meta, string $key) => [
                    'key' => $key,
                    'label' => $meta['label'],
                    'icon' => $meta['icon'],
                ])
                ->values(),
            'all_entries_category' => [
                'key' => null,
                'label' => 'All Entries',
                'icon' => '📚',
            ],
            'permissions' => [
                'can_submit' => $viewerCharacter !== null,
                'can_manage' => $canManage,
                'active_character_id' => $viewerCharacter?->id,
            ],
            'archive_recovery' => [
                'can_recover' => $canRecoverArchive,
                'room_is_empty' => $roomIsEmpty,
                'available_archives' => $canRecoverArchive
                    ? $this->availableArchivedWorldBooksForUser((int) ($request->user()?->id ?? 0))
                    : [],
            ],
            'owned_characters' => $this->ownedCharactersForUser($request->user()?->id),
            'pending_queue' => $pendingQueue,
            'entries' => $serialized,
        ]);
    }

    public function previewArchive(Request $request, Room $room): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertRoomOwnerAccount($request, $room);

        $archive = $this->archivedWorldBookForRequestUser($request);

        return response()->json([
            'ok' => true,
            'archive' => $this->serializeArchivedWorldBook($archive),
        ]);
    }

    public function importArchive(Request $request, Room $room): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertRoomOwnerAccount($request, $room);

        abort_if(
            $this->roomHasActiveWorldBookEntries($room),
            422,
            'Recover archived World Book is only available when this room has no existing World Book entries.'
        );

        $archive = $this->archivedWorldBookForRequestUser($request);
        $archivedEntries = $archive->entries()
            ->orderByRaw('COALESCE(sort_order, 2147483647) asc')
            ->orderBy('id')
            ->get();

        abort_if($archivedEntries->isEmpty(), 422, 'This archived World Book has no entries to import.');

        $actor = $this->actorCharacterForRoom($request, $room);

        DB::transaction(function () use ($archivedEntries, $room, $actor) {
            foreach ($archivedEntries as $archivedEntry) {
                $entry = new WorldBookEntry([
                    'room_id' => $room->id,
                    'author_character_id' => $actor->id,
                    'reviewed_by_character_id' => $archivedEntry->reviewed_at !== null ? $actor->id : null,
                    'linked_character_id' => null,
                    'draft_linked_character_id' => null,
                    'status' => $archivedEntry->status,
                    'sort_order' => $archivedEntry->sort_order,
                    'title' => $archivedEntry->title,
                    'category' => $archivedEntry->category,
                    'image_url' => $archivedEntry->image_url,
                    'body' => $archivedEntry->body,
                    'tags' => WorldBookEntry::normalizeTags($archivedEntry->tags ?? []),
                    'draft_title' => $archivedEntry->draft_title,
                    'draft_category' => $archivedEntry->draft_category,
                    'draft_image_url' => $archivedEntry->draft_image_url,
                    'draft_body' => $archivedEntry->draft_body,
                    'draft_tags' => WorldBookEntry::normalizeTags($archivedEntry->draft_tags ?? []),
                    'published_at' => $archivedEntry->published_at,
                    'reviewed_at' => $archivedEntry->reviewed_at,
                    'rejection_note' => $archivedEntry->rejection_note,
                    'rejected_at' => $archivedEntry->rejected_at,
                ]);
                $entry->save();
            }
        });

        Log::info('Imported archived World Book into room.', [
            'room_id' => $room->id,
            'owner_user_id' => (int) $request->user()->id,
            'archive_id' => $archive->id,
            'imported_entry_count' => $archivedEntries->count(),
            'recovery_key' => $archive->recovery_key,
            'occurred_at' => now()->toISOString(),
        ]);

        return response()->json([
            'ok' => true,
            'imported_count' => $archivedEntries->count(),
        ]);
    }

    public function store(Request $request, Room $room): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);

        $actor = $this->actorCharacterForRoom($request, $room);
        $canManage = $this->roomAccess->canManageRoom($request->user(), $room, $actor);
        $payload = $this->validatedPayload($request);

        $entry = new WorldBookEntry([
            'room_id' => $room->id,
            'author_character_id' => $actor->id,
        ]);

        if ($canManage && $request->boolean('publish')) {
            $entry->fill([
                'status' => WorldBookEntry::STATUS_PUBLISHED,
                'title' => $payload['title'],
                'category' => $payload['category'],
                'image_url' => $payload['image_url'],
                'body' => $payload['body'],
                'tags' => $payload['tags'],
                'linked_character_id' => $payload['linked_character_id'],
                'draft_linked_character_id' => null,
                'published_at' => now(),
                'reviewed_by_character_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_note' => null,
                'rejected_at' => null,
            ]);
            $entry->sort_order = WorldBookEntry::isCharacterCategory($payload['category'])
                ? null
                : $this->nextSortOrderForCategory($room->id, $payload['category']);
        } else {
            $entry->fill([
                'status' => WorldBookEntry::STATUS_PENDING,
                'draft_title' => $payload['title'],
                'draft_category' => $payload['category'],
                'draft_image_url' => $payload['image_url'],
                'draft_body' => $payload['body'],
                'draft_tags' => $payload['tags'],
                'draft_linked_character_id' => $payload['linked_character_id'],
                'rejection_note' => null,
                'rejected_at' => null,
            ]);
        }

        $entry->save();

        return response()->json([
            'ok' => true,
            'entry' => $this->serializeEntry($entry->load(['authorCharacter', 'reviewedByCharacter', 'linkedCharacter.profile', 'draftLinkedCharacter.profile']), $actor, $canManage),
        ]);
    }

    public function update(Request $request, Room $room, WorldBookEntry $entry): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertEntryBelongsToRoom($room, $entry);

        $actor = $this->actorCharacterForRoom($request, $room);
        $canManage = $this->roomAccess->canManageRoom($request->user(), $room, $actor);
        $payload = $this->validatedPayload($request);
        $previousPublishedCategory = $entry->category;
        $hadPublishedContent = $entry->hasPublishedContent();

        if ($canManage) {
            if ($request->boolean('publish')) {
                $entry->fill([
                    'status' => WorldBookEntry::STATUS_PUBLISHED,
                    'title' => $payload['title'],
                    'category' => $payload['category'],
                    'image_url' => $payload['image_url'],
                    'body' => $payload['body'],
                    'tags' => $payload['tags'],
                    'linked_character_id' => $payload['linked_character_id'],
                    'draft_title' => null,
                    'draft_category' => null,
                    'draft_image_url' => null,
                    'draft_body' => null,
                    'draft_tags' => null,
                    'draft_linked_character_id' => null,
                    'published_at' => $entry->published_at ?? now(),
                    'reviewed_by_character_id' => $actor->id,
                    'reviewed_at' => now(),
                    'rejection_note' => null,
                    'rejected_at' => null,
                ]);
                $this->applyPublishedSortOrder($entry, $room->id, $payload['category'], $previousPublishedCategory, $hadPublishedContent);
                $entry->save();
                $this->reindexIfCategoryChanged($room->id, $previousPublishedCategory, $entry->category, $hadPublishedContent);
            } else {
                $entry->fill([
                    'status' => WorldBookEntry::STATUS_PENDING,
                    'draft_title' => $payload['title'],
                    'draft_category' => $payload['category'],
                    'draft_image_url' => $payload['image_url'],
                    'draft_body' => $payload['body'],
                    'draft_tags' => $payload['tags'],
                    'draft_linked_character_id' => $payload['linked_character_id'],
                    'rejection_note' => null,
                    'rejected_at' => null,
                ])->save();
            }
        } else {
            abort_unless((int) $entry->author_character_id === (int) $actor->id, 403);

            if (! $entry->hasPublishedContent()) {
                abort_unless($entry->status === WorldBookEntry::STATUS_PENDING || $entry->status === WorldBookEntry::STATUS_REJECTED, 403);
            }

            $entry->fill([
                'status' => WorldBookEntry::STATUS_PENDING,
                'draft_title' => $payload['title'],
                'draft_category' => $payload['category'],
                'draft_image_url' => $payload['image_url'],
                'draft_body' => $payload['body'],
                'draft_tags' => $payload['tags'],
                'draft_linked_character_id' => $payload['linked_character_id'],
                'rejection_note' => null,
                'rejected_at' => null,
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'entry' => $this->serializeEntry($entry->fresh()->load(['authorCharacter', 'reviewedByCharacter', 'linkedCharacter.profile', 'draftLinkedCharacter.profile']), $actor, $canManage),
        ]);
    }

    public function approve(Request $request, Room $room, WorldBookEntry $entry): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertEntryBelongsToRoom($room, $entry);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);
        abort_unless($entry->hasPendingDraft(), 422);

        $previousPublishedCategory = $entry->category;
        $hadPublishedContent = $entry->hasPublishedContent();
        $approvedCategory = $entry->draft_category;

        $entry->fill([
            'status' => WorldBookEntry::STATUS_PUBLISHED,
            'title' => $entry->draft_title,
            'category' => $approvedCategory,
            'image_url' => $entry->draft_image_url,
            'body' => $entry->draft_body,
            'tags' => WorldBookEntry::normalizeTags($entry->draft_tags ?? []),
            'linked_character_id' => $entry->draft_linked_character_id,
            'draft_title' => null,
            'draft_category' => null,
            'draft_image_url' => null,
            'draft_body' => null,
            'draft_tags' => null,
            'draft_linked_character_id' => null,
            'published_at' => $entry->published_at ?? now(),
            'reviewed_by_character_id' => $actor->id,
            'reviewed_at' => now(),
            'rejection_note' => null,
            'rejected_at' => null,
        ]);
        $this->applyPublishedSortOrder($entry, $room->id, $approvedCategory, $previousPublishedCategory, $hadPublishedContent);
        $entry->save();
        $this->reindexIfCategoryChanged($room->id, $previousPublishedCategory, $entry->category, $hadPublishedContent);

        return response()->json([
            'ok' => true,
            'entry' => $this->serializeEntry($entry->fresh()->load(['authorCharacter', 'reviewedByCharacter', 'linkedCharacter.profile', 'draftLinkedCharacter.profile']), $actor, true),
        ]);
    }

    public function reject(Request $request, Room $room, WorldBookEntry $entry): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertEntryBelongsToRoom($room, $entry);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $validated = $request->validate([
            'rejection_note' => ['nullable', 'string', 'max:4000'],
        ]);

        $rejectionNote = $this->nullableString($validated['rejection_note'] ?? null);

        if ($entry->hasPublishedContent()) {
            $entry->fill([
                'status' => WorldBookEntry::STATUS_PUBLISHED,
                'draft_title' => null,
                'draft_category' => null,
                'draft_image_url' => null,
                'draft_body' => null,
                'draft_tags' => null,
                'draft_linked_character_id' => null,
                'reviewed_by_character_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_note' => $rejectionNote,
                'rejected_at' => now(),
            ])->save();
        } else {
            $entry->fill([
                'status' => WorldBookEntry::STATUS_REJECTED,
                'reviewed_by_character_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_note' => $rejectionNote,
                'rejected_at' => now(),
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'entry' => $this->serializeEntry($entry->fresh()->load(['authorCharacter', 'reviewedByCharacter', 'linkedCharacter.profile', 'draftLinkedCharacter.profile']), $actor, true),
        ]);
    }

    public function destroy(Request $request, Room $room, WorldBookEntry $entry): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertEntryBelongsToRoom($room, $entry);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $publishedCategory = $entry->hasPublishedContent() ? $entry->category : null;

        $entry->delete();

        if ($publishedCategory !== null) {
            $this->reindexCategory($room->id, $publishedCategory);
        }

        return response()->json(['ok' => true]);
    }

    public function move(Request $request, Room $room, WorldBookEntry $entry): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertEntryBelongsToRoom($room, $entry);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);
        abort_unless($entry->hasPublishedContent() && $entry->category !== null && ! WorldBookEntry::isCharacterCategory($entry->category), 422);

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:up,down'],
        ]);

        $category = $entry->category;
        $entries = WorldBookEntry::query()
            ->where('room_id', $room->id)
            ->where('category', $category)
            ->whereNotNull('published_at')
            ->whereNull('deleted_at')
            ->orderByRaw('COALESCE(sort_order, 2147483647) asc')
            ->orderBy('title')
            ->get();

        $currentIndex = $entries->search(fn (WorldBookEntry $item) => (int) $item->id === (int) $entry->id);
        abort_unless($currentIndex !== false, 404);

        $targetIndex = $validated['direction'] === 'up' ? $currentIndex - 1 : $currentIndex + 1;
        abort_if($targetIndex < 0 || $targetIndex >= $entries->count(), 422);

        $ordered = $entries->values()->all();
        $moved = $ordered[$currentIndex];
        array_splice($ordered, $currentIndex, 1);
        array_splice($ordered, $targetIndex, 0, [$moved]);

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $index => $item) {
                WorldBookEntry::query()
                    ->whereKey($item->id)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json([
            'ok' => true,
            'entry' => $this->serializeEntry($entry->fresh()->load(['authorCharacter', 'reviewedByCharacter', 'linkedCharacter.profile', 'draftLinkedCharacter.profile']), $actor, true),
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(WorldBookEntry::categories()))],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'body' => ['nullable', 'string', 'max:20000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:50'],
            'tags_input' => ['nullable', 'string', 'max:1000'],
            'linked_character_id' => ['nullable', 'integer', 'exists:characters,id'],
        ]);

        $category = $validated['category'];
        $linkedCharacterId = isset($validated['linked_character_id']) ? (int) $validated['linked_character_id'] : null;

        if (WorldBookEntry::isCharacterCategory($category)) {
            if ($linkedCharacterId === null || $linkedCharacterId <= 0) {
                throw ValidationException::withMessages([
                    'linked_character_id' => 'Select one of your characters to link.',
                ]);
            }

            $ownerUserId = (int) ($request->user()?->id ?? 0);
            $ownsCharacter = Character::query()
                ->where('id', $linkedCharacterId)
                ->where('user_id', $ownerUserId)
                ->exists();

            if (! $ownsCharacter) {
                throw ValidationException::withMessages([
                    'linked_character_id' => 'You can only link a character you own.',
                ]);
            }

            return [
                'title' => null,
                'category' => $category,
                'image_url' => null,
                'body' => $this->nullableString($validated['body'] ?? null),
                'tags' => WorldBookEntry::normalizeTags($validated['tags'] ?? ($validated['tags_input'] ?? [])),
                'linked_character_id' => $linkedCharacterId,
            ];
        }

        $title = trim((string) ($validated['title'] ?? ''));
        $body = trim((string) ($validated['body'] ?? ''));

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'The title field is required.',
            ]);
        }

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'The body field is required.',
            ]);
        }

        return [
            'title' => $title,
            'category' => $category,
            'image_url' => $this->nullableString($validated['image_url'] ?? null),
            'body' => $body,
            'tags' => WorldBookEntry::normalizeTags($validated['tags'] ?? ($validated['tags_input'] ?? [])),
            'linked_character_id' => null,
        ];
    }

    private function entryVisibleToViewer(WorldBookEntry $entry, ?Character $viewerCharacter, bool $canManage): bool
    {
        if ($canManage) {
            return true;
        }

        if ($entry->hasPublishedContent()) {
            return true;
        }

        return $viewerCharacter !== null
            && (int) $entry->author_character_id === (int) $viewerCharacter->id
            && in_array($entry->status, [WorldBookEntry::STATUS_PENDING, WorldBookEntry::STATUS_REJECTED], true);
    }

    private function serializeEntry(WorldBookEntry $entry, ?Character $viewerCharacter, bool $canManage): array
    {
        $isAuthor = $viewerCharacter !== null
            && (int) $entry->author_character_id === (int) $viewerCharacter->id;
        $canSeeDraft = $canManage || $isAuthor;
        $canSeeRejectionNote = $canManage || $isAuthor;
        $listCategory = $entry->effectiveCategory($canSeeDraft);
        $listLinkedCharacter = $entry->effectiveLinkedCharacter($canSeeDraft);
        $listTitle = $this->displayTitleForCategory($entry->effectiveTitle($canSeeDraft), $listCategory, $listLinkedCharacter);
        $listTags = $entry->hasPublishedContent()
            ? WorldBookEntry::normalizeTags($entry->tags ?? [])
            : ($canSeeDraft ? WorldBookEntry::normalizeTags($entry->draft_tags ?? []) : []);

        return [
            'id' => $entry->id,
            'status' => $entry->status,
            'sort_order' => $entry->sort_order,
            'category' => $listCategory,
            'category_label' => WorldBookEntry::categoryLabel($listCategory),
            'category_icon' => WorldBookEntry::categoryIcon($listCategory),
            'title' => $listTitle,
            'body' => $entry->hasPublishedContent() ? $entry->body : ($canSeeDraft ? $entry->draft_body : null),
            'image_url' => $entry->hasPublishedContent() ? $entry->image_url : ($canSeeDraft ? $entry->draft_image_url : null),
            'tags' => $listTags,
            'linked_character' => $this->serializeLinkedCharacter($listLinkedCharacter),
            'has_published_content' => $entry->hasPublishedContent(),
            'has_pending_draft' => $entry->hasPendingDraft(),
            'author_character' => [
                'id' => $entry->authorCharacter?->id,
                'name' => $entry->authorCharacter?->name,
                'handle' => $entry->authorCharacter?->public_handle,
            ],
            'reviewed_by_character' => [
                'id' => $entry->reviewedByCharacter?->id,
                'name' => $entry->reviewedByCharacter?->name,
            ],
            'published' => $entry->hasPublishedContent() ? [
                'title' => $this->displayTitleForCategory($entry->title, $entry->category, $entry->linkedCharacter),
                'category' => $entry->category,
                'category_label' => WorldBookEntry::categoryLabel($entry->category),
                'category_icon' => WorldBookEntry::categoryIcon($entry->category),
                'image_url' => $entry->image_url,
                'body' => $entry->body,
                'tags' => WorldBookEntry::normalizeTags($entry->tags ?? []),
                'sort_order' => $entry->sort_order,
                'linked_character' => $this->serializeLinkedCharacter($entry->linkedCharacter),
            ] : null,
            'pending' => $canSeeDraft && $entry->hasPendingDraft() ? [
                'title' => $this->displayTitleForCategory($entry->draft_title, $entry->draft_category, $entry->draftLinkedCharacter),
                'category' => $entry->draft_category,
                'category_label' => WorldBookEntry::categoryLabel($entry->draft_category),
                'category_icon' => WorldBookEntry::categoryIcon($entry->draft_category),
                'image_url' => $entry->draft_image_url,
                'body' => $entry->draft_body,
                'tags' => WorldBookEntry::normalizeTags($entry->draft_tags ?? []),
                'linked_character' => $this->serializeLinkedCharacter($entry->draftLinkedCharacter),
            ] : null,
            'rejection_note' => $canSeeRejectionNote ? $entry->rejection_note : null,
            'rejected_at' => $canSeeRejectionNote ? optional($entry->rejected_at)->toIso8601String() : null,
            'viewer_can_edit' => $canManage || $isAuthor,
            'viewer_can_manage' => $canManage,
            'search_text' => $this->searchTextForEntry($entry, $canSeeDraft),
            'created_at' => optional($entry->created_at)->toIso8601String(),
            'updated_at' => optional($entry->updated_at)->toIso8601String(),
            'published_at' => optional($entry->published_at)->toIso8601String(),
            'reviewed_at' => optional($entry->reviewed_at)->toIso8601String(),
        ];
    }

    private function searchTextForEntry(WorldBookEntry $entry, bool $canSeeDraft): string
    {
        $segments = [
            $entry->title,
            $entry->body,
            implode(' ', WorldBookEntry::normalizeTags($entry->tags ?? [])),
            $entry->linkedCharacter?->name,
            $entry->linkedCharacter?->public_handle,
        ];

        if ($canSeeDraft) {
            $segments[] = $entry->draft_title;
            $segments[] = $entry->draft_body;
            $segments[] = implode(' ', WorldBookEntry::normalizeTags($entry->draft_tags ?? []));
            $segments[] = $entry->draftLinkedCharacter?->name;
            $segments[] = $entry->draftLinkedCharacter?->public_handle;
        }

        return trim(implode(' ', array_filter($segments, fn ($value) => is_string($value) && trim($value) !== '')));
    }

    private function nextSortOrderForCategory(int $roomId, string $category): int
    {
        return ((int) WorldBookEntry::query()
            ->where('room_id', $roomId)
            ->where('category', $category)
            ->whereNotNull('published_at')
            ->max('sort_order')) + 1;
    }

    private function applyPublishedSortOrder(WorldBookEntry $entry, int $roomId, string $newCategory, ?string $previousPublishedCategory, bool $hadPublishedContent): void
    {
        if (WorldBookEntry::isCharacterCategory($newCategory)) {
            $entry->sort_order = null;
            return;
        }

        if ($hadPublishedContent && $previousPublishedCategory === $newCategory && $entry->sort_order !== null) {
            return;
        }

        $entry->sort_order = $this->nextSortOrderForCategory($roomId, $newCategory);
    }

    private function reindexIfCategoryChanged(int $roomId, ?string $previousPublishedCategory, ?string $newPublishedCategory, bool $hadPublishedContent): void
    {
        if (! $hadPublishedContent || $previousPublishedCategory === null || $previousPublishedCategory === $newPublishedCategory) {
            return;
        }

        $this->reindexCategory($roomId, $previousPublishedCategory);
    }

    private function reindexCategory(int $roomId, string $category): void
    {
        if (WorldBookEntry::isCharacterCategory($category)) {
            return;
        }

        $entries = WorldBookEntry::query()
            ->where('room_id', $roomId)
            ->where('category', $category)
            ->whereNotNull('published_at')
            ->whereNull('deleted_at')
            ->orderByRaw('COALESCE(sort_order, 2147483647) asc')
            ->orderBy('title')
            ->get();

        DB::transaction(function () use ($entries) {
            foreach ($entries as $index => $item) {
                WorldBookEntry::query()
                    ->whereKey($item->id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    private function viewerCharacterForRoom(Room $room): ?Character
    {
        $preferred = $this->preferredOwnedCharacter();

        if ($preferred !== null && $this->roomAccess->canViewRoom(Auth::user(), $room, $preferred)) {
            return $preferred;
        }

        return Character::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get()
            ->first(fn (Character $character) => $this->roomAccess->canViewRoom(Auth::user(), $room, $character));
    }

    private function actorCharacterForRoom(Request $request, Room $room): Character
    {
        $actor = $this->viewerCharacterForRoom($room);

        abort_if($actor === null, 403);
        abort_unless((int) $actor->user_id === (int) $request->user()->id, 403);

        return $actor;
    }

    private function preferredOwnedCharacter(): ?Character
    {
        $sessionCharacterId = (int) session('active_character_id', 0);

        if ($sessionCharacterId <= 0) {
            return null;
        }

        return Character::query()
            ->where('id', $sessionCharacterId)
            ->where('user_id', Auth::id())
            ->first();
    }

    private function assertEntryBelongsToRoom(Room $room, WorldBookEntry $entry): void
    {
        abort_unless((int) $entry->room_id === (int) $room->id, 404);
    }

    private function abortIfNotPublicRoom(Room $room): void
    {
        abort_if(! $room->isPublicRoom(), 404);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function displayTitleForCategory(?string $title, ?string $category, ?Character $linkedCharacter): string
    {
        if (WorldBookEntry::isCharacterCategory($category)) {
            return $linkedCharacter?->name ?? 'Linked Character';
        }

        return $title ?: 'Untitled';
    }

    private function serializeLinkedCharacter(?Character $character): ?array
    {
        if ($character === null) {
            return null;
        }

        return [
            'id' => $character->id,
            'name' => $character->name,
            'handle' => $character->public_handle,
            'avatar_url' => $character->profile?->avatar_url ?: $character->externalAvatarUrl(),
            'card_url' => route('characters.show', $character),
            'profile_url' => route('characters.profile.show', $character),
        ];
    }

    private function latestRoomActivityByCharacter(int $roomId, Collection $entries): array
    {
        $characterIds = $entries
            ->flatMap(fn (WorldBookEntry $entry) => [
                $entry->linked_character_id,
                $entry->draft_linked_character_id,
            ])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($characterIds->isEmpty()) {
            return [];
        }

        return DB::table('messages')
            ->selectRaw('character_id, MAX(created_at) as latest_posted_at')
            ->where('room_id', $roomId)
            ->whereIn('character_id', $characterIds->all())
            ->whereNull('deleted_at')
            ->groupBy('character_id')
            ->pluck('latest_posted_at', 'character_id')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    private function sortEntriesForIndex(Collection $entries, array $activityByCharacter, ?Character $viewerCharacter, bool $canManage): Collection
    {
        $categoryOrder = array_flip(array_keys(WorldBookEntry::categoryMeta()));

        $sorted = $entries->sort(function (WorldBookEntry $left, WorldBookEntry $right) use ($activityByCharacter, $viewerCharacter, $canManage, $categoryOrder) {
            $leftCanSeeDraft = $canManage || ($viewerCharacter !== null && (int) $left->author_character_id === (int) $viewerCharacter->id);
            $rightCanSeeDraft = $canManage || ($viewerCharacter !== null && (int) $right->author_character_id === (int) $viewerCharacter->id);

            $leftCategory = $left->effectiveCategory($leftCanSeeDraft);
            $rightCategory = $right->effectiveCategory($rightCanSeeDraft);
            $leftCategoryOrder = $categoryOrder[$leftCategory] ?? PHP_INT_MAX;
            $rightCategoryOrder = $categoryOrder[$rightCategory] ?? PHP_INT_MAX;

            if ($leftCategoryOrder !== $rightCategoryOrder) {
                return $leftCategoryOrder <=> $rightCategoryOrder;
            }

            if (WorldBookEntry::isCharacterCategory($leftCategory) && WorldBookEntry::isCharacterCategory($rightCategory)) {
                $leftCharacterId = $left->effectiveLinkedCharacter($leftCanSeeDraft)?->id;
                $rightCharacterId = $right->effectiveLinkedCharacter($rightCanSeeDraft)?->id;
                $leftActivity = $leftCharacterId !== null ? ($activityByCharacter[$leftCharacterId] ?? null) : null;
                $rightActivity = $rightCharacterId !== null ? ($activityByCharacter[$rightCharacterId] ?? null) : null;

                if ($leftActivity !== $rightActivity) {
                    if ($leftActivity === null) {
                        return 1;
                    }

                    if ($rightActivity === null) {
                        return -1;
                    }

                    return strcmp($rightActivity, $leftActivity);
                }

                return strcasecmp(
                    $this->displayTitleForCategory($left->effectiveTitle($leftCanSeeDraft), $leftCategory, $left->effectiveLinkedCharacter($leftCanSeeDraft)),
                    $this->displayTitleForCategory($right->effectiveTitle($rightCanSeeDraft), $rightCategory, $right->effectiveLinkedCharacter($rightCanSeeDraft))
                );
            }

            $leftSortOrder = $left->sort_order ?? PHP_INT_MAX;
            $rightSortOrder = $right->sort_order ?? PHP_INT_MAX;

            if ($leftSortOrder !== $rightSortOrder) {
                return $leftSortOrder <=> $rightSortOrder;
            }

            return strcasecmp(
                $this->displayTitleForCategory($left->effectiveTitle($leftCanSeeDraft), $leftCategory, $left->effectiveLinkedCharacter($leftCanSeeDraft)),
                $this->displayTitleForCategory($right->effectiveTitle($rightCanSeeDraft), $rightCategory, $right->effectiveLinkedCharacter($rightCanSeeDraft))
            );
        });

        return $sorted->values();
    }

    private function ownedCharactersForUser(?int $userId): array
    {
        if ((int) $userId <= 0) {
            return [];
        }

        return Character::query()
            ->where('user_id', $userId)
            ->with('profile')
            ->orderBy('name')
            ->get()
            ->map(fn (Character $character) => [
                'id' => $character->id,
                'name' => $character->name,
                'handle' => $character->public_handle,
                'avatar_url' => $character->profile?->avatar_url ?: $character->externalAvatarUrl(),
            ])
            ->all();
    }

    private function roomOwnerUserId(Room $room): ?int
    {
        return $this->roomAccess->ownerUserId($room) ?? ((int) ($room->created_by ?? 0) > 0 ? (int) $room->created_by : null);
    }

    private function userOwnsRoom(int $userId, Room $room): bool
    {
        $ownerUserId = $this->roomOwnerUserId($room);

        return $ownerUserId !== null && $userId > 0 && $userId === $ownerUserId;
    }

    private function assertRoomOwnerAccount(Request $request, Room $room): void
    {
        abort_unless($this->userOwnsRoom((int) ($request->user()?->id ?? 0), $room), 403);
    }

    private function roomHasActiveWorldBookEntries(Room $room): bool
    {
        return WorldBookEntry::query()
            ->where('room_id', $room->id)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function availableArchivedWorldBooksForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return ArchivedWorldBook::query()
            ->where('owner_user_id', $userId)
            ->orderByDesc('archived_at')
            ->get()
            ->map(fn (ArchivedWorldBook $archive) => $this->serializeArchivedWorldBookSummary($archive))
            ->all();
    }

    private function archivedWorldBookForRequestUser(Request $request): ArchivedWorldBook
    {
        $validated = $request->validate([
            'recovery_key' => ['required', 'string', 'max:64'],
        ]);

        $archive = ArchivedWorldBook::query()
            ->with('entries')
            ->where('owner_user_id', (int) $request->user()->id)
            ->where('recovery_key', trim($validated['recovery_key']))
            ->first();

        abort_if($archive === null, 404);

        return $archive;
    }

    private function serializeArchivedWorldBookSummary(ArchivedWorldBook $archive): array
    {
        return [
            'recovery_key' => $archive->recovery_key,
            'original_room_id' => $archive->original_room_id,
            'original_room_name' => $archive->original_room_name,
            'entry_count' => $archive->entry_count,
            'room_deleted_at' => optional($archive->room_deleted_at)->toIso8601String(),
            'archived_at' => optional($archive->archived_at)->toIso8601String(),
        ];
    }

    private function serializeArchivedWorldBook(ArchivedWorldBook $archive): array
    {
        $archive->loadMissing('entries');

        $entries = $archive->entries
            ->sort(function ($left, $right) {
                $leftSortOrder = $left->sort_order ?? PHP_INT_MAX;
                $rightSortOrder = $right->sort_order ?? PHP_INT_MAX;

                if ($leftSortOrder !== $rightSortOrder) {
                    return $leftSortOrder <=> $rightSortOrder;
                }

                return $left->id <=> $right->id;
            })
            ->values()
            ->map(fn ($entry) => $this->serializeArchivedWorldBookEntry($entry))
            ->all();

        return array_merge($this->serializeArchivedWorldBookSummary($archive), [
            'entries' => $entries,
        ]);
    }

    private function serializeArchivedWorldBookEntry($entry): array
    {
        $displayCategory = $entry->category ?? $entry->draft_category;
        $displayTitle = $entry->title ?? $entry->draft_title;
        $displayBody = $entry->body ?? $entry->draft_body;
        $displayTags = $entry->category !== null
            ? WorldBookEntry::normalizeTags($entry->tags ?? [])
            : WorldBookEntry::normalizeTags($entry->draft_tags ?? []);

        return [
            'id' => $entry->id,
            'status' => $entry->status,
            'sort_order' => $entry->sort_order,
            'title' => $displayTitle ?: 'Untitled',
            'category' => $displayCategory,
            'category_label' => WorldBookEntry::categoryLabel($displayCategory),
            'category_icon' => WorldBookEntry::categoryIcon($displayCategory),
            'body' => $displayBody,
            'tags' => $displayTags,
            'image_url' => $entry->image_url ?? $entry->draft_image_url,
            'published' => $entry->category !== null ? [
                'title' => $entry->title ?: 'Untitled',
                'category' => $entry->category,
                'category_label' => WorldBookEntry::categoryLabel($entry->category),
                'category_icon' => WorldBookEntry::categoryIcon($entry->category),
                'body' => $entry->body,
                'image_url' => $entry->image_url,
                'tags' => WorldBookEntry::normalizeTags($entry->tags ?? []),
                'sort_order' => $entry->sort_order,
            ] : null,
            'pending' => $entry->draft_category !== null ? [
                'title' => $entry->draft_title ?: 'Untitled',
                'category' => $entry->draft_category,
                'category_label' => WorldBookEntry::categoryLabel($entry->draft_category),
                'category_icon' => WorldBookEntry::categoryIcon($entry->draft_category),
                'body' => $entry->draft_body,
                'image_url' => $entry->draft_image_url,
                'tags' => WorldBookEntry::normalizeTags($entry->draft_tags ?? []),
            ] : null,
            'rejection_note' => $entry->rejection_note,
            'rejected_at' => optional($entry->rejected_at)->toIso8601String(),
            'published_at' => optional($entry->published_at)->toIso8601String(),
            'reviewed_at' => optional($entry->reviewed_at)->toIso8601String(),
        ];
    }
}
