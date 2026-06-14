<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomRule;
use App\Services\RoomAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RulesController extends Controller
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

        $rules = $this->serializedRules($room, $canManage);

        return response()->json([
            'room' => [
                'id' => $room->id,
                'slug' => $room->slug,
                'name' => $room->name,
            ],
            'permissions' => [
                'can_create' => $canManage,
                'can_manage' => $canManage,
                'active_character_id' => $viewerCharacter?->id,
            ],
            'rules' => $rules,
        ]);
    }

    public function store(Request $request, Room $room): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $payload = $this->validatedPayload($request);

        $rule = RoomRule::create([
            'room_id' => $room->id,
            'title' => $payload['title'],
            'body' => $payload['body'],
            'sort_order' => ((int) RoomRule::query()->where('room_id', $room->id)->max('sort_order')) + 1,
        ]);

        $this->normalizeSortOrder($room);

        return response()->json([
            'ok' => true,
            'rule' => $this->serializeRule($rule->fresh(), true, $room),
            'rules' => $this->serializedRules($room->fresh(), true),
        ]);
    }

    public function update(Request $request, Room $room, RoomRule $rule): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertRuleBelongsToRoom($room, $rule);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $payload = $this->validatedPayload($request);

        $rule->fill($payload)->save();

        return response()->json([
            'ok' => true,
            'rule' => $this->serializeRule($rule->fresh(), true, $room),
            'rules' => $this->serializedRules($room->fresh(), true),
        ]);
    }

    public function destroy(Request $request, Room $room, RoomRule $rule): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertRuleBelongsToRoom($room, $rule);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $rule->delete();
        $this->normalizeSortOrder($room);

        return response()->json([
            'ok' => true,
            'rules' => $this->serializedRules($room->fresh(), true),
        ]);
    }

    public function move(Request $request, Room $room, RoomRule $rule): JsonResponse
    {
        $this->abortIfNotPublicRoom($room);
        $this->assertRuleBelongsToRoom($room, $rule);

        $actor = $this->actorCharacterForRoom($request, $room);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:up,down'],
        ]);

        $rules = $room->roomRules()->get()->values();
        $index = $rules->search(fn (RoomRule $candidate) => (int) $candidate->id === (int) $rule->id);
        abort_if($index === false, 404);

        $direction = $validated['direction'];
        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($targetIndex < 0 || $targetIndex >= $rules->count()) {
            return response()->json([
                'ok' => true,
                'rule' => $this->serializeRule($rule->fresh(), true, $room),
                'rules' => $this->serializedRules($room->fresh(), true),
            ]);
        }

        $orderedIds = $rules->pluck('id')->all();
        $movedId = $orderedIds[$index];
        array_splice($orderedIds, $index, 1);
        array_splice($orderedIds, $targetIndex, 0, [$movedId]);
        $this->persistOrderedIds($room, $orderedIds);

        return response()->json([
            'ok' => true,
            'rule' => $this->serializeRule($rule->fresh(), true, $room),
            'rules' => $this->serializedRules($room->fresh(), true),
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $title = trim($validated['title']);
        $body = trim($validated['body']);

        if ($title === '' || $body === '') {
            throw ValidationException::withMessages([
                'title' => $title === '' ? ['The title field is required.'] : [],
                'body' => $body === '' ? ['The body field is required.'] : [],
            ]);
        }

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    private function serializedRules(Room $room, bool $canManage): array
    {
        $rules = $room->roomRules()->get()->values();

        return $rules
            ->map(fn (RoomRule $rule, int $index) => $this->serializeRule($rule, $canManage, $room, $index, $rules->count()))
            ->all();
    }

    private function serializeRule(RoomRule $rule, bool $canManage, Room $room, ?int $index = null, ?int $count = null): array
    {
        if ($index === null || $count === null) {
            $rules = $room->roomRules()->get()->values();
            $index = $rules->search(fn (RoomRule $candidate) => (int) $candidate->id === (int) $rule->id);
            $count = $rules->count();
        }

        $index = $index === false ? 0 : (int) $index;
        $count = (int) $count;

        return [
            'id' => $rule->id,
            'title' => $rule->title,
            'body' => $rule->body,
            'sort_order' => $rule->sort_order,
            'viewer_can_edit' => $canManage,
            'viewer_can_manage' => $canManage,
            'can_move_up' => $canManage && $index > 0,
            'can_move_down' => $canManage && $index < ($count - 1),
            'created_at' => optional($rule->created_at)->toIso8601String(),
            'updated_at' => optional($rule->updated_at)->toIso8601String(),
        ];
    }

    private function normalizeSortOrder(Room $room): void
    {
        $this->persistOrderedIds($room, $room->roomRules()->pluck('id')->all());
    }

    private function persistOrderedIds(Room $room, array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $index => $ruleId) {
            RoomRule::query()
                ->where('room_id', $room->id)
                ->where('id', $ruleId)
                ->update(['sort_order' => $index + 1]);
        }
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

    private function assertRuleBelongsToRoom(Room $room, RoomRule $rule): void
    {
        abort_unless((int) $rule->room_id === (int) $room->id, 404);
    }

    private function abortIfNotPublicRoom(Room $room): void
    {
        abort_if(! $room->isPublicRoom(), 404);
    }
}
