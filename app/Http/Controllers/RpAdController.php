<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Room;
use App\Models\RpAd;
use App\Services\RoomAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RpAdController extends Controller
{
    private const AD_LIFETIME_DAYS = 7;

    public function __construct(
        private readonly RoomAccessService $roomAccess,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownedCharacters = $this->ownedCharacters();
        $viewerCharacter = $this->preferredOwnedCharacter() ?? $ownedCharacters->first();
        $publicAds = RpAd::query()
            ->with(['character', 'room'])
            ->where('expires_at', '>', now())
            ->orderByDesc('refreshed_at')
            ->orderByDesc('updated_at')
            ->get();

        $roomAds = [];
        $dmAds = [];

        foreach ($publicAds as $ad) {
            if (! $this->adIsVisibleToViewer($ad, $user, $viewerCharacter)) {
                continue;
            }

            $serialized = $this->serializeAd($ad, false, $viewerCharacter);

            if ($ad->type === RpAd::TYPE_ROOM) {
                $roomAds[] = $serialized;
            } else {
                $dmAds[] = $serialized;
            }
        }

        $myAds = RpAd::query()
            ->with(['character', 'room'])
            ->whereIn('character_id', $ownedCharacters->pluck('id'))
            ->orderByDesc('refreshed_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (RpAd $ad) => $this->serializeAd($ad, true, $viewerCharacter))
            ->values();

        return response()->json([
            'types' => collect(RpAd::types())
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
            'viewer' => [
                'active_character_id' => $viewerCharacter?->id,
                'default_dm_character_id' => $viewerCharacter?->id,
                'has_characters' => $ownedCharacters->isNotEmpty(),
            ],
            'permissions' => [
                'can_create' => $ownedCharacters->isNotEmpty(),
            ],
            'owned_characters' => $ownedCharacters
                ->map(fn (Character $character) => [
                    'id' => $character->id,
                    'name' => $character->name,
                    'handle' => $character->public_handle,
                    'avatar' => $character->externalAvatarUrl(),
                ])
                ->values(),
            'rooms_by_character' => $ownedCharacters
                ->mapWithKeys(fn (Character $character) => [
                    (string) $character->id => $this->accessibleRoomsForCharacter($request->user(), $character)
                        ->map(fn (Room $room) => [
                            'id' => $room->id,
                            'name' => $room->name,
                            'slug' => $room->slug,
                        ])
                        ->values(),
                ]),
            'room_ads' => $roomAds,
            'dm_ads' => $dmAds,
            'my_ads' => $myAds,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatedPayload($request);
        $character = $this->ownedCharacterOrFail((int) $payload['character_id']);
        $room = $this->roomForPayload($request->user(), $character, $payload);

        $this->ensureNoOtherActiveAd($character->id);

        $ad = RpAd::create([
            'character_id' => $character->id,
            'room_id' => $room?->id,
            'type' => $payload['type'],
            'title' => trim($payload['title']),
            'body' => trim($payload['body']),
            'tags' => RpAd::normalizeTags($payload['tags'] ?? null),
            'is_nsfw' => (bool) ($payload['is_nsfw'] ?? false),
            'refreshed_at' => now(),
            'expires_at' => now()->addDays(self::AD_LIFETIME_DAYS),
        ]);

        return response()->json([
            'ok' => true,
            'ad' => $this->serializeAd($ad->load(['character', 'room']), true, $character),
        ]);
    }

    public function update(Request $request, RpAd $rpAd): JsonResponse
    {
        $this->assertOwnedAd($rpAd);

        $payload = $this->validatedPayload($request);
        $character = $this->ownedCharacterOrFail((int) $payload['character_id']);
        $room = $this->roomForPayload($request->user(), $character, $payload);

        if ($rpAd->isActive()) {
            $this->ensureNoOtherActiveAd($character->id, $rpAd->id);
        }

        $rpAd->fill([
            'character_id' => $character->id,
            'room_id' => $room?->id,
            'type' => $payload['type'],
            'title' => trim($payload['title']),
            'body' => trim($payload['body']),
            'tags' => RpAd::normalizeTags($payload['tags'] ?? null),
            'is_nsfw' => (bool) ($payload['is_nsfw'] ?? false),
        ])->save();

        return response()->json([
            'ok' => true,
            'ad' => $this->serializeAd($rpAd->fresh()->load(['character', 'room']), true, $character),
        ]);
    }

    public function refresh(Request $request, RpAd $rpAd): JsonResponse
    {
        $this->assertOwnedAd($rpAd);
        $character = $rpAd->character;

        abort_unless($character !== null, 404);

        $roomPayload = [
            'type' => $rpAd->type,
            'room_id' => $rpAd->room_id,
        ];

        $this->roomForPayload($request->user(), $character, $roomPayload);
        $this->ensureNoOtherActiveAd($character->id, $rpAd->id);

        $rpAd->forceFill([
            'refreshed_at' => now(),
            'expires_at' => now()->addDays(self::AD_LIFETIME_DAYS),
        ])->save();

        return response()->json([
            'ok' => true,
            'ad' => $this->serializeAd($rpAd->fresh()->load(['character', 'room']), true, $character),
        ]);
    }

    public function destroy(RpAd $rpAd): JsonResponse
    {
        $this->assertOwnedAd($rpAd);
        $rpAd->delete();

        return response()->json(['ok' => true]);
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'character_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(RpAd::types()))],
            'room_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'tags' => ['nullable'],
            'is_nsfw' => ['nullable', 'boolean'],
        ]);

        if (($validated['type'] ?? null) === RpAd::TYPE_ROOM && empty($validated['room_id'])) {
            throw ValidationException::withMessages([
                'room_id' => 'A room ad requires a linked room.',
            ]);
        }

        if (($validated['type'] ?? null) === RpAd::TYPE_DM) {
            $validated['room_id'] = null;
        }

        return $validated;
    }

    private function ownedCharacters(): Collection
    {
        return Character::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();
    }

    private function ownedCharacterOrFail(int $characterId): Character
    {
        $character = Character::query()
            ->where('id', $characterId)
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($character, 403);

        return $character;
    }

    private function roomForPayload($user, Character $character, array $payload): ?Room
    {
        if (($payload['type'] ?? null) !== RpAd::TYPE_ROOM) {
            return null;
        }

        $roomId = (int) ($payload['room_id'] ?? 0);
        abort_if($roomId <= 0, 422);

        $room = Room::query()->find($roomId);
        abort_unless($room !== null, 422);
        abort_unless($room->isPublicRoom(), 422);
        abort_unless($this->roomAccess->canJoinRoom($user, $room, $character), 403);

        return $room;
    }

    private function ensureNoOtherActiveAd(int $characterId, ?int $ignoreAdId = null): void
    {
        $query = RpAd::query()
            ->where('character_id', $characterId)
            ->where('expires_at', '>', now());

        if ($ignoreAdId !== null) {
            $query->where('id', '!=', $ignoreAdId);
        }

        if (! $query->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'character_id' => 'This character already has an active RP ad.',
        ]);
    }

    private function assertOwnedAd(RpAd $rpAd): void
    {
        $rpAd->loadMissing('character');

        abort_unless(
            $rpAd->character !== null
                && (int) $rpAd->character->user_id === (int) Auth::id(),
            403
        );
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

    private function accessibleRoomsForCharacter($user, Character $character): Collection
    {
        return $this->roomAccess
            ->applyVisiblePublicRoomScope(
                Room::query()->orderBy('name'),
                $user,
                $character
            )
            ->get();
    }

    private function adIsVisibleToViewer(RpAd $ad, $user, ?Character $viewerCharacter): bool
    {
        if ($ad->type === RpAd::TYPE_DM) {
            return $ad->character !== null;
        }

        if ($ad->room === null || ! $ad->room->isPublicRoom()) {
            return false;
        }

        return $this->roomAccess->canViewRoom($user, $ad->room, $viewerCharacter);
    }

    private function serializeAd(RpAd $ad, bool $forOwner, ?Character $viewerCharacter): array
    {
        $tags = collect($ad->tags ?? [])
            ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
            ->values()
            ->all();

        $isExpired = ! $ad->expires_at?->isFuture();
        $belongsToViewer = $viewerCharacter !== null
            && (int) $ad->character_id === (int) $viewerCharacter->id;
        $sameUser = $ad->character !== null
            && (int) $ad->character->user_id === (int) Auth::id();

        return [
            'id' => $ad->id,
            'type' => $ad->type,
            'type_label' => RpAd::typeLabel($ad->type),
            'title' => $ad->title,
            'body' => $ad->body,
            'body_obscured' => ! $forOwner && (bool) $ad->is_nsfw,
            'is_nsfw' => (bool) $ad->is_nsfw,
            'tags' => $tags,
            'character' => [
                'id' => $ad->character?->id,
                'user_id' => $ad->character?->user_id,
                'name' => $ad->character?->name,
                'handle' => $ad->character?->public_handle,
                'avatar' => $ad->character?->externalAvatarUrl(),
            ],
            'room' => $ad->room ? [
                'id' => $ad->room->id,
                'name' => $ad->room->name,
                'slug' => $ad->room->slug,
                'url' => route('rooms.show', $ad->room->slug),
            ] : null,
            'action' => $ad->type === RpAd::TYPE_ROOM
                ? [
                    'kind' => 'enter_room',
                    'label' => 'Enter Room',
                    'url' => $ad->room ? route('rooms.show', $ad->room->slug) : null,
                ]
                : [
                    'kind' => 'start_dm',
                    'label' => 'Start DM',
                    'url' => route('dms.start'),
                    'other_character_id' => $ad->character_id,
                    'disabled' => $sameUser,
                ],
            'is_active' => ! $isExpired,
            'is_expired' => $isExpired,
            'viewer_can_edit' => $forOwner,
            'viewer_can_refresh' => $forOwner,
            'viewer_can_delete' => $forOwner,
            'viewer_owns_character' => $sameUser,
            'belongs_to_active_character' => $belongsToViewer,
            'search_text' => trim(implode(' ', array_filter([
                $ad->title,
                $ad->body,
                implode(' ', $tags),
                $ad->character?->name,
                $ad->room?->name,
                RpAd::typeLabel($ad->type),
            ]))),
            'created_at' => optional($ad->created_at)->toIso8601String(),
            'updated_at' => optional($ad->updated_at)->toIso8601String(),
            'refreshed_at' => optional($ad->refreshed_at)->toIso8601String(),
            'expires_at' => optional($ad->expires_at)->toIso8601String(),
        ];
    }
}
