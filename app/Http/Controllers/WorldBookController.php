<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Room;
use App\Models\WorldBookEntry;
use App\Services\RoomAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        $entries = WorldBookEntry::query()
            ->where('room_id', $room->id)
            ->with(['authorCharacter', 'reviewedByCharacter'])
            ->orderByRaw('COALESCE(category, draft_category) asc')
            ->orderByRaw('COALESCE(sort_order, 2147483647) asc')
            ->orderByRaw('COALESCE(title, draft_title) asc')
            ->get()
            ->filter(fn (WorldBookEntry $entry) => $this->entryVisibleToViewer($entry, $viewerCharacter, $canManage))
            ->values();

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
            'pending_queue' => $pendingQueue,
            'entries' => $serialized,
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
                'published_at' => now(),
                'reviewed_by_character_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_note' => null,
                'rejected_at' => null,
            ]);
            $entry->sort_order = $this->nextSortOrderForCategory($room->id, $payload['category']);
        } else {
            $entry->fill([
                'status' => WorldBookEntry::STATUS_PENDING,
                'draft_title' => $payload['title'],
                'draft_category' => $payload['category'],
                'draft_image_url' => $payload['image_url'],
                'draft_body' => $payload['body'],
                'draft_tags' => $payload['tags'],
                'rejection_note' => null,
                'rejected_at' => null,
            ]);
        }

        $entry->save();

        return response()->json([
            'ok' => true,
            'entry' => $this->serializeEntry($entry->load(['authorCharacter', 'reviewedByCharacter']), $actor, $canManage),
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
                    'draft_title' => null,
                    'draft_category' => null,
                    'draft_image_url' => null,
                    'draft_body' => null,
                    'draft_tags' => null,
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
                'rejection_note' => null,
                'rejected_at' => null,
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'entry' => $this->serializeEntry($entry->fresh()->load(['authorCharacter', 'reviewedByCharacter']), $actor, $canManage),
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
            'draft_title' => null,
            'draft_category' => null,
            'draft_image_url' => null,
            'draft_body' => null,
            'draft_tags' => null,
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
            'entry' => $this->serializeEntry($entry->fresh()->load(['authorCharacter', 'reviewedByCharacter']), $actor, true),
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
            'entry' => $this->serializeEntry($entry->fresh()->load(['authorCharacter', 'reviewedByCharacter']), $actor, true),
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
        abort_unless($entry->hasPublishedContent() && $entry->category !== null, 422);

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
            'entry' => $this->serializeEntry($entry->fresh()->load(['authorCharacter', 'reviewedByCharacter']), $actor, true),
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(WorldBookEntry::categories()))],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'body' => ['required', 'string', 'max:20000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:50'],
            'tags_input' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'title' => trim($validated['title']),
            'category' => $validated['category'],
            'image_url' => $this->nullableString($validated['image_url'] ?? null),
            'body' => trim($validated['body']),
            'tags' => WorldBookEntry::normalizeTags($validated['tags'] ?? ($validated['tags_input'] ?? [])),
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
        $listTitle = $entry->effectiveTitle($canSeeDraft);
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
                'title' => $entry->title,
                'category' => $entry->category,
                'category_label' => WorldBookEntry::categoryLabel($entry->category),
                'category_icon' => WorldBookEntry::categoryIcon($entry->category),
                'image_url' => $entry->image_url,
                'body' => $entry->body,
                'tags' => WorldBookEntry::normalizeTags($entry->tags ?? []),
                'sort_order' => $entry->sort_order,
            ] : null,
            'pending' => $canSeeDraft && $entry->hasPendingDraft() ? [
                'title' => $entry->draft_title,
                'category' => $entry->draft_category,
                'category_label' => WorldBookEntry::categoryLabel($entry->draft_category),
                'category_icon' => WorldBookEntry::categoryIcon($entry->draft_category),
                'image_url' => $entry->draft_image_url,
                'body' => $entry->draft_body,
                'tags' => WorldBookEntry::normalizeTags($entry->draft_tags ?? []),
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
        ];

        if ($canSeeDraft) {
            $segments[] = $entry->draft_title;
            $segments[] = $entry->draft_body;
            $segments[] = implode(' ', WorldBookEntry::normalizeTags($entry->draft_tags ?? []));
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
}
