<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomNotice;
use App\Services\RoomAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoticeBoardController extends Controller
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

        $notices = RoomNotice::query()
            ->where('room_id', $room->id)
            ->with('authorCharacter')
            ->orderByRaw("case when status = 'active' then 0 when status = 'closed' then 1 else 2 end")
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (RoomNotice $notice) => $this->serializeNotice($notice, $viewerCharacter, $canManage))
            ->values();

        return response()->json([
            'room' => [
                'id' => $room->id,
                'slug' => $room->slug,
                'name' => $room->name,
            ],
            'categories' => collect(RoomNotice::categoryMeta())
                ->map(fn (array $meta, string $key) => [
                    'key' => $key,
                    'label' => $meta['label'],
                    'icon' => $meta['icon'],
                ])
                ->values(),
            'statuses' => collect(RoomNotice::statusMeta())
                ->map(fn (array $meta, string $key) => [
                    'key' => $key,
                    'label' => $meta['label'],
                ])
                ->values(),
            'accent_colors' => collect(RoomNotice::accentColorMeta())
                ->map(fn (array $meta, string $key) => [
                    'key' => $key,
                    'label' => $meta['label'],
                ])
                ->values(),
            'permissions' => [
                'can_create' => $viewerCharacter !== null,
                'can_manage' => $canManage,
                'active_character_id' => $viewerCharacter?->id,
            ],
            'notices' => $notices,
        ]);
    }

    public function store(Request $request, Room $room): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);

        $actor = $this->actorCharacterForRoom($request, $room);
        $payload = $this->validatedPayload($request, false);

        $notice = RoomNotice::create([
            'room_id' => $room->id,
            'author_character_id' => $actor->id,
            'title' => $payload['title'],
            'category' => $payload['category'],
            'body' => $payload['body'],
            'reward' => $payload['reward'],
            'location' => $payload['location'],
            'expires_at' => $payload['expires_at'],
            'accent_color' => $payload['accent_color'],
            'status' => RoomNotice::STATUS_ACTIVE,
        ]);

        return response()->json([
            'ok' => true,
            'notice' => $this->serializeNotice(
                $notice->load('authorCharacter'),
                $actor,
                $this->roomAccess->canManageRoom($request->user(), $room, $actor)
            ),
        ]);
    }

    public function update(Request $request, Room $room, RoomNotice $notice): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertNoticeBelongsToRoom($room, $notice);

        $actor = $this->actorCharacterForRoom($request, $room);
        $canManage = $this->roomAccess->canManageRoom($request->user(), $room, $actor);
        abort_unless($canManage || (int) $notice->author_character_id === (int) $actor->id, 403);

        $payload = $this->validatedPayload($request, true);

        $notice->fill($payload)->save();

        return response()->json([
            'ok' => true,
            'notice' => $this->serializeNotice($notice->fresh()->load('authorCharacter'), $actor, $canManage),
        ]);
    }

    public function destroy(Request $request, Room $room, RoomNotice $notice): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertNoticeBelongsToRoom($room, $notice);

        $actor = $this->actorCharacterForRoom($request, $room);
        $canManage = $this->roomAccess->canManageRoom($request->user(), $room, $actor);
        abort_unless($canManage || (int) $notice->author_character_id === (int) $actor->id, 403);

        $notice->delete();

        return response()->json(['ok' => true]);
    }

    private function validatedPayload(Request $request, bool $allowStatus): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(RoomNotice::categoryMeta()))],
            'body' => ['required', 'string', 'max:20000'],
            'reward' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'accent_color' => ['nullable', 'string', 'in:default,' . implode(',', array_keys(RoomNotice::accentColorMeta()))],
        ];

        if ($allowStatus) {
            $rules['status'] = ['required', 'string', 'in:' . implode(',', array_keys(RoomNotice::statusMeta()))];
        }

        $validated = $request->validate($rules);

        return [
            'title' => trim($validated['title']),
            'category' => $validated['category'],
            'body' => trim($validated['body']),
            'reward' => $this->nullableString($validated['reward'] ?? null),
            'location' => $this->nullableString($validated['location'] ?? null),
            'expires_at' => $this->nullableString($validated['expires_at'] ?? null),
            'accent_color' => $this->normalizedAccentColor($validated['accent_color'] ?? null),
            'status' => $allowStatus ? $validated['status'] : RoomNotice::STATUS_ACTIVE,
        ];
    }

    private function serializeNotice(RoomNotice $notice, ?Character $viewerCharacter, bool $canManage): array
    {
        $isAuthor = $viewerCharacter !== null
            && (int) $notice->author_character_id === (int) $viewerCharacter->id;

        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'category' => $notice->category,
            'category_label' => RoomNotice::categoryLabel($notice->category),
            'category_icon' => RoomNotice::categoryIcon($notice->category),
            'body' => $notice->body,
            'reward' => $notice->reward,
            'location' => $notice->location,
            'status' => $notice->status,
            'status_label' => RoomNotice::statusLabel($notice->status),
            'expires_at' => optional($notice->expires_at)->toIso8601String(),
            'accent_color' => $notice->accent_color,
            'accent_color_label' => RoomNotice::accentColorLabel($notice->accent_color),
            'author_character' => [
                'id' => $notice->authorCharacter?->id,
                'user_id' => $notice->authorCharacter?->user_id,
                'name' => $notice->authorCharacter?->name,
                'handle' => $notice->authorCharacter?->public_handle,
                'avatar' => $notice->authorCharacter?->externalAvatarUrl(),
            ],
            'viewer_can_edit' => $canManage || $isAuthor,
            'viewer_can_manage' => $canManage,
            'search_text' => trim(implode(' ', array_filter([
                $notice->title,
                $notice->body,
                $notice->reward,
                $notice->location,
                $notice->authorCharacter?->name,
            ]))),
            'created_at' => optional($notice->created_at)->toIso8601String(),
            'updated_at' => optional($notice->updated_at)->toIso8601String(),
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

    private function assertNoticeBelongsToRoom(Room $room, RoomNotice $notice): void
    {
        abort_unless((int) $notice->room_id === (int) $room->id, 404);
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

        return array_key_exists($value, RoomNotice::accentColorMeta()) ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
