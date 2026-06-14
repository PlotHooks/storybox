<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomPinnedNote;
use App\Services\RoomAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PinnedNotesController extends Controller
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

        $notes = RoomPinnedNote::query()
            ->where('room_id', $room->id)
            ->with('authorCharacter')
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (RoomPinnedNote $note) => $this->serializeNote($note, $canManage))
            ->values();

        return response()->json([
            'room' => [
                'id' => $room->id,
                'slug' => $room->slug,
                'name' => $room->name,
            ],
            'categories' => collect(RoomPinnedNote::categoryMeta())
                ->map(fn (array $meta, string $key) => [
                    'key' => $key,
                    'label' => $meta['label'],
                    'icon' => $meta['icon'],
                ])
                ->values(),
            'statuses' => collect(RoomPinnedNote::statusMeta())
                ->map(fn (array $meta, string $key) => [
                    'key' => $key,
                    'label' => $meta['label'],
                ])
                ->values(),
            'accent_colors' => collect(RoomPinnedNote::accentColorMeta())
                ->map(fn (array $meta, string $key) => [
                    'key' => $key,
                    'label' => $meta['label'],
                ])
                ->values(),
            'permissions' => [
                'can_create' => $canManage,
                'can_manage' => $canManage,
                'active_character_id' => $viewerCharacter?->id,
            ],
            'notes' => $notes,
        ]);
    }

    public function store(Request $request, Room $room): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $payload = $this->validatedPayload($request, false);

        $note = RoomPinnedNote::create([
            'room_id' => $room->id,
            'author_character_id' => $actor->id,
            'title' => $payload['title'],
            'category' => $payload['category'],
            'body' => $payload['body'],
            'expires_at' => $payload['expires_at'],
            'accent_color' => $payload['accent_color'],
            'status' => RoomPinnedNote::STATUS_ACTIVE,
        ]);

        return response()->json([
            'ok' => true,
            'note' => $this->serializeNote($note->load('authorCharacter'), true),
        ]);
    }

    public function update(Request $request, Room $room, RoomPinnedNote $note): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertNoteBelongsToRoom($room, $note);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $payload = $this->validatedPayload($request, true);

        $note->fill($payload)->save();

        return response()->json([
            'ok' => true,
            'note' => $this->serializeNote($note->fresh()->load('authorCharacter'), true),
        ]);
    }

    public function destroy(Request $request, Room $room, RoomPinnedNote $note): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertNoteBelongsToRoom($room, $note);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $note->delete();

        return response()->json(['ok' => true]);
    }

    private function validatedPayload(Request $request, bool $allowStatus): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(RoomPinnedNote::categoryMeta()))],
            'body' => ['required', 'string', 'max:20000'],
            'expires_at' => ['nullable', 'date'],
            'accent_color' => ['nullable', 'string', 'in:default,' . implode(',', array_keys(RoomPinnedNote::accentColorMeta()))],
        ];

        if ($allowStatus) {
            $rules['status'] = ['required', 'string', 'in:' . implode(',', array_keys(RoomPinnedNote::statusMeta()))];
        }

        $validated = $request->validate($rules);

        return [
            'title' => trim($validated['title']),
            'category' => $validated['category'],
            'body' => trim($validated['body']),
            'expires_at' => $this->nullableString($validated['expires_at'] ?? null),
            'accent_color' => $this->normalizedAccentColor($validated['accent_color'] ?? null),
            'status' => $allowStatus ? $validated['status'] : RoomPinnedNote::STATUS_ACTIVE,
        ];
    }

    private function serializeNote(RoomPinnedNote $note, bool $canManage): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'category' => $note->category,
            'category_label' => RoomPinnedNote::categoryLabel($note->category),
            'category_icon' => RoomPinnedNote::categoryIcon($note->category),
            'body' => $note->body,
            'status' => $note->status,
            'status_label' => RoomPinnedNote::statusLabel($note->status),
            'expires_at' => optional($note->expires_at)->toIso8601String(),
            'accent_color' => $note->accent_color,
            'accent_color_label' => RoomPinnedNote::accentColorLabel($note->accent_color),
            'author_character' => [
                'id' => $note->authorCharacter?->id,
                'user_id' => $note->authorCharacter?->user_id,
                'name' => $note->authorCharacter?->name,
                'handle' => $note->authorCharacter?->public_handle,
                'avatar' => $note->authorCharacter?->externalAvatarUrl(),
            ],
            'viewer_can_edit' => $canManage,
            'viewer_can_manage' => $canManage,
            'search_text' => trim(implode(' ', array_filter([
                $note->title,
                $note->body,
                $note->authorCharacter?->name,
            ]))),
            'created_at' => optional($note->created_at)->toIso8601String(),
            'updated_at' => optional($note->updated_at)->toIso8601String(),
        ];
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

    private function assertNoteBelongsToRoom(Room $room, RoomPinnedNote $note): void
    {
        abort_unless((int) $note->room_id === (int) $room->id, 404);
    }

    private function abortIfNotPublicRoom(Room $room): void
    {
        abort_if(! $room->isPublicRoom(), 404);
    }

    private function normalizedAccentColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(mb_strtolower($value));

        if ($value === '' || $value === 'default') {
            return null;
        }

        return array_key_exists($value, RoomPinnedNote::accentColorMeta()) ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
