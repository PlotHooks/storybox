<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\RoomCharacterRole;
use App\Services\RoomAccessService;
use App\Services\RoomEjectionService;
use App\Services\RoomLandingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomManagementController extends Controller
{
    public function __construct(
        private readonly RoomAccessService $roomAccess,
        private readonly RoomEjectionService $roomEjection,
    ) {
    }

    public function update(Request $request, Room $room)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', 'in:' . Room::VISIBILITY_PUBLIC . ',' . Room::VISIBILITY_HIDDEN],
        ]);

        $room->fill($validated)->save();

        if (! $request->expectsJson()) {
            return $this->managementResponse($request, $room, 'Room settings updated.');
        }

        return response()->json([
            'ok' => true,
            'room' => $room->fresh(),
        ]);
    }

    public function editProfile(Request $request, Room $room)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->activeOwnedCharacterFromSession($request);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        return view('rooms.profile-edit', [
            'room' => $room,
            'activeCharacterId' => $actor->id,
        ]);
    }

    public function updateProfile(Request $request, Room $room)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $validated = $request->validate([
            'profile_banner_url' => ['nullable', 'url:http,https', 'max:2048'],
            'profile_summary' => ['nullable', 'string', 'max:4000'],
            'profile_joining_information' => ['nullable', 'string', 'max:4000'],
            'profile_mode' => ['nullable', 'string', 'in:' . Room::PROFILE_MODE_STANDARD . ',' . Room::PROFILE_MODE_ADVANCED],
            'profile_custom_html' => ['nullable', 'string'],
            'profile_custom_css' => ['nullable', 'string'],
            'profile_custom_js' => ['nullable', 'string'],
        ]);

        $room->fill([
            'profile_banner_url' => $this->nullableString($validated['profile_banner_url'] ?? null),
            'profile_summary' => $this->nullableString($validated['profile_summary'] ?? null),
            'profile_joining_information' => $this->nullableString($validated['profile_joining_information'] ?? null),
            'profile_mode' => $validated['profile_mode'] ?? Room::PROFILE_MODE_STANDARD,
            'profile_custom_html' => $this->nullableString($validated['profile_custom_html'] ?? null),
            'profile_custom_css' => $this->nullableString($validated['profile_custom_css'] ?? null),
            'profile_custom_js' => $this->nullableString($validated['profile_custom_js'] ?? null),
        ])->save();

        if (! $request->expectsJson()) {
            return redirect()
                ->route('rooms.profile.show', $room->slug)
                ->with('status', 'Room profile updated.');
        }

        return response()->json([
            'ok' => true,
            'room' => $room->fresh(),
        ]);
    }

    public function addWhitelist(Request $request, Room $room)
    {
        return $this->upsertCharacterAccessEntry($request, $room, RoomAccessEntry::TYPE_WHITELIST);
    }

    public function removeWhitelist(Request $request, Room $room, Character $character)
    {
        return $this->deleteCharacterAccessEntry($request, $room, $character, RoomAccessEntry::TYPE_WHITELIST);
    }

    public function addBlacklist(Request $request, Room $room)
    {
        return $this->upsertCharacterAccessEntry($request, $room, RoomAccessEntry::TYPE_BLACKLIST);
    }

    public function removeBlacklist(Request $request, Room $room, Character $character)
    {
        return $this->deleteCharacterAccessEntry($request, $room, $character, RoomAccessEntry::TYPE_BLACKLIST);
    }

    public function addAccountBlacklist(Request $request, Room $room)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->canManageRoomAccess($request->user(), $room, $actor), 403);

        $target = $this->targetCharacterFromRequest($request);
        $this->assertAccountAccessTargetCanBeManaged($request, $room, $actor, $target);

        RoomAccessEntry::query()->updateOrCreate(
            [
                'room_id' => $room->id,
                'user_id' => $target->user_id,
                'type' => RoomAccessEntry::TYPE_BLACKLIST,
                'scope' => RoomAccessEntry::SCOPE_ACCOUNT,
            ],
            [
                'character_id' => null,
                'created_by_character_id' => $actor->id,
            ],
        );

        $this->roomEjection->ejectAccount($room, (int) $target->user_id, $actor);

        return $this->managementResponse($request, $room, 'Account ban updated.');
    }

    public function removeAccountBlacklist(Request $request, Room $room, Character $character)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->canManageRoomAccess($request->user(), $room, $actor), 403);
        $this->assertAccountAccessTargetCanBeManaged($request, $room, $actor, $character);

        RoomAccessEntry::query()
            ->where('room_id', $room->id)
            ->where('user_id', $character->user_id)
            ->where('type', RoomAccessEntry::TYPE_BLACKLIST)
            ->where('scope', RoomAccessEntry::SCOPE_ACCOUNT)
            ->delete();

        return $this->managementResponse($request, $room, 'Account ban removed.');
    }

    public function kick(Request $request, Room $room)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = $this->targetCharacterFromRequest($request);
        $this->assertKickTargetCanBeManaged($request, $room, $actor, $target);

        $reason = $this->nullableString($validated['reason'] ?? null);

        $this->roomEjection->eject($room, $target, $actor, $reason);

        return $this->managementResponse($request, $room, 'Character kicked from room.');
    }

    public function moderationState(Request $request, Room $room, Character $character)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->activeOwnedCharacterFromSession($request);
        abort_unless($this->roomAccess->canManageRoom($request->user(), $room, $actor), 403);

        $targetIsOwner = (int) $room->owner_character_id === (int) $character->id;
        $targetIsModerator = $this->roomAccess->isModerator($room, $character);
        $viewerIsAdmin = $this->roomAccess->isAdmin($request->user());
        $viewerCanManageModeratorRole = $this->roomAccess->isOwner($room, $actor) || $viewerIsAdmin;
        $isCharacterBanned = $this->roomAccess->isCharacterBlacklisted($room, $character);
        $isAccountBanned = $this->roomAccess->isAccountBlacklisted($room, $character);

        return response()->json([
            'target' => [
                'id' => $character->id,
                'public_handle' => $character->public_handle,
                'is_owner' => $targetIsOwner,
                'is_moderator' => $targetIsModerator,
                'is_whitelisted' => $this->roomAccess->isWhitelisted($room, $character),
                'is_character_banned' => $isCharacterBanned,
                'is_account_banned' => $isAccountBanned,
                'is_banned' => $isCharacterBanned || $isAccountBanned,
            ],
            'actions' => [
                'can_kick' => $this->canKickTarget($request, $room, $actor, $character),
                'can_ban_character' => $this->canManageCharacterAccessTarget($request, $room, $actor, $character),
                'can_ban_account' => $this->canManageAccountAccessTarget($request, $room, $actor, $character),
                'can_manage_moderator_role' => $viewerCanManageModeratorRole
                    && (! $targetIsOwner || $viewerIsAdmin)
                    && (int) $actor->id !== (int) $character->id,
            ],
        ]);
    }

    public function addModerator(Request $request, Room $room)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->isOwner($room, $actor) || $this->roomAccess->isAdmin($request->user()), 403);
        $target = $this->targetCharacterFromRequest($request);

        abort_if((int) $room->owner_character_id === (int) $target->id, 422, 'The room owner is implicit and cannot be stored as a moderator.');

        RoomCharacterRole::query()->updateOrCreate(
            [
                'room_id' => $room->id,
                'character_id' => $target->id,
            ],
            [
                'role' => RoomCharacterRole::ROLE_MODERATOR,
            ],
        );

        return $this->managementResponse($request, $room, 'Moderator added.');
    }

    public function removeModerator(Request $request, Room $room, Character $character)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->isOwner($room, $actor) || $this->roomAccess->isAdmin($request->user()), 403);
        abort_if((int) $room->owner_character_id === (int) $character->id, 403);

        RoomCharacterRole::query()
            ->where('room_id', $room->id)
            ->where('character_id', $character->id)
            ->where('role', RoomCharacterRole::ROLE_MODERATOR)
            ->delete();

        return $this->managementResponse($request, $room, 'Moderator removed.');
    }

    public function destroy(Request $request, Room $room)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless((int) session('active_character_id', 0) === (int) $actor->id, 403);
        abort_unless($this->roomAccess->canDeleteRoom($request->user(), $room, $actor), 403);

        $request->validate([
            'delete_confirmation' => ['required', 'in:DELETE'],
        ], [
            'delete_confirmation.in' => 'Type DELETE exactly to confirm room deletion.',
        ]);

        DB::transaction(function () use ($room) {
            $room->characterPresences()->delete();
            DB::table('room_user_presence')->where('room_id', $room->id)->delete();
            DB::table('room_presences')->where('room_id', $room->id)->delete();
            // Intentionally preserve messages, access entries, and moderator rows for review and later purge.
            $room->delete();
        });

        if (! $request->expectsJson()) {
            return redirect()
                ->to(app(RoomLandingService::class)->destinationFor($request->user()))
                ->with('status', 'Room deleted successfully.');
        }

        return response()->json(['ok' => true]);
    }

    private function upsertCharacterAccessEntry(Request $request, Room $room, string $type)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->canManageRoomAccess($request->user(), $room, $actor), 403);

        $target = $this->targetCharacterFromRequest($request);
        $this->assertCharacterAccessTargetCanBeManaged($request, $room, $actor, $target);

        if ($type === RoomAccessEntry::TYPE_BLACKLIST) {
            RoomAccessEntry::query()
                ->where('room_id', $room->id)
                ->where('character_id', $target->id)
                ->where('type', RoomAccessEntry::TYPE_WHITELIST)
                ->where('scope', RoomAccessEntry::SCOPE_CHARACTER)
                ->delete();
        }

        RoomAccessEntry::query()->updateOrCreate(
            [
                'room_id' => $room->id,
                'character_id' => $target->id,
                'type' => $type,
                'scope' => RoomAccessEntry::SCOPE_CHARACTER,
            ],
            [
                'user_id' => null,
                'created_by_character_id' => $actor->id,
            ],
        );

        if ($type === RoomAccessEntry::TYPE_BLACKLIST) {
            $this->roomEjection->eject($room, $target, $actor);
        }

        return $this->managementResponse(
            $request,
            $room,
            $type === RoomAccessEntry::TYPE_WHITELIST ? 'Whitelist updated.' : 'Character ban updated.'
        );
    }

    private function deleteCharacterAccessEntry(Request $request, Room $room, Character $target, string $type)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->canManageRoomAccess($request->user(), $room, $actor), 403);
        $this->assertCharacterAccessTargetCanBeManaged($request, $room, $actor, $target);

        RoomAccessEntry::query()
            ->where('room_id', $room->id)
            ->where('character_id', $target->id)
            ->where('type', $type)
            ->where('scope', RoomAccessEntry::SCOPE_CHARACTER)
            ->delete();

        return $this->managementResponse(
            $request,
            $room,
            $type === RoomAccessEntry::TYPE_WHITELIST ? 'Whitelist entry removed.' : 'Character ban removed.'
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function ownerUserId(Room $room): ?int
    {
        return $this->roomAccess->ownerUserId($room);
    }

    private function assertCharacterAccessTargetCanBeManaged(Request $request, Room $room, Character $actor, Character $target): void
    {
        if ((int) $room->owner_character_id === (int) $target->id) {
            abort(403);
        }

        if ($this->ownerUserId($room) !== null && (int) $target->user_id === (int) $this->ownerUserId($room)) {
            abort(403);
        }

        if (! $this->roomAccess->isOwner($room, $actor)
            && ! $this->roomAccess->isAdmin($request->user())
            && $this->roomAccess->isModerator($room, $target)) {
            abort(403);
        }
    }

    private function assertAccountAccessTargetCanBeManaged(Request $request, Room $room, Character $actor, Character $target): void
    {
        if ((int) $actor->user_id === (int) $target->user_id) {
            abort(422, 'You cannot change your own account ban state.');
        }

        if ($this->ownerUserId($room) !== null && (int) $target->user_id === (int) $this->ownerUserId($room)) {
            abort(403);
        }

        if (! $this->roomAccess->isOwner($room, $actor)
            && ! $this->roomAccess->isAdmin($request->user())
            && $this->roomAccess->userHasModeratorRole($room, (int) $target->user_id)) {
            abort(403);
        }
    }

    private function canKickTarget(Request $request, Room $room, Character $actor, Character $target): bool
    {
        if ((int) $actor->id === (int) $target->id) {
            return false;
        }

        if ((int) $room->owner_character_id === (int) $target->id) {
            return $this->roomAccess->isAdmin($request->user());
        }

        if (! $this->roomAccess->isOwner($room, $actor)
            && ! $this->roomAccess->isAdmin($request->user())
            && $this->roomAccess->isModerator($room, $target)) {
            return false;
        }

        return true;
    }

    private function canManageCharacterAccessTarget(Request $request, Room $room, Character $actor, Character $target): bool
    {
        if ((int) $actor->id === (int) $target->id) {
            return false;
        }

        if ((int) $room->owner_character_id === (int) $target->id) {
            return false;
        }

        $ownerUserId = $this->ownerUserId($room);
        if ($ownerUserId !== null && (int) $target->user_id === $ownerUserId) {
            return false;
        }

        if (! $this->roomAccess->isOwner($room, $actor)
            && ! $this->roomAccess->isAdmin($request->user())
            && $this->roomAccess->isModerator($room, $target)) {
            return false;
        }

        return true;
    }

    private function canManageAccountAccessTarget(Request $request, Room $room, Character $actor, Character $target): bool
    {
        if ((int) $actor->user_id === (int) $target->user_id) {
            return false;
        }

        $ownerUserId = $this->ownerUserId($room);
        if ($ownerUserId !== null && (int) $target->user_id === $ownerUserId) {
            return false;
        }

        if (! $this->roomAccess->isOwner($room, $actor)
            && ! $this->roomAccess->isAdmin($request->user())
            && $this->roomAccess->userHasModeratorRole($room, (int) $target->user_id)) {
            return false;
        }

        return true;
    }

    private function assertKickTargetCanBeManaged(Request $request, Room $room, Character $actor, Character $target): void
    {
        abort_if((int) $actor->id === (int) $target->id, 422, 'You cannot kick yourself from the room.');
        abort_unless($this->canKickTarget($request, $room, $actor, $target), 403);
    }

    private function ownedCharacterFromRequest(Request $request): Character
    {
        $validated = $request->validate([
            'character_id' => ['required', 'integer', 'exists:characters,id'],
        ]);

        $character = Character::findOrFail($validated['character_id']);
        abort_if((int) $character->user_id !== (int) $request->user()->id, 403);

        return $character;
    }

    private function abortIfNotManagedPublicRoom(Room $room): void
    {
        abort_if(! $room->isPublicRoom(), 404);
    }

    private function activeOwnedCharacterFromSession(Request $request): Character
    {
        $characterId = (int) $request->session()->get('active_character_id', 0);
        abort_if($characterId <= 0, 403);

        $character = Character::findOrFail($characterId);
        abort_if((int) $character->user_id !== (int) $request->user()->id, 403);

        return $character;
    }

    private function targetCharacterFromRequest(Request $request): Character
    {
        $request->validate([
            'target_character_id' => ['nullable', 'integer'],
            'target_character_handle' => ['nullable', 'string', 'max:120'],
        ]);

        $targetCharacterId = (int) $request->input('target_character_id', 0);
        $targetCharacterHandle = trim((string) $request->input('target_character_handle', ''));

        if ($targetCharacterId > 0) {
            $character = Character::find($targetCharacterId);

            if ($character) {
                return $character;
            }
        }

        if ($targetCharacterHandle !== '') {
            if (preg_match('/^\d+$/', $targetCharacterHandle)) {
                throw ValidationException::withMessages([
                    'target_character_handle' => ['Use the public handle format Name#ABCD, not a raw character id.'],
                ]);
            }

            $character = Character::resolvePublicHandle($targetCharacterHandle);
            if ($character) {
                return $character;
            }
        }

        throw ValidationException::withMessages([
            'target_character_handle' => ['Target character not found. Use the full public handle format Name#ABCD.'],
        ]);
    }

    private function managementResponse(Request $request, Room $room, string $statusMessage)
    {
        if (! $request->expectsJson()) {
            return redirect()
                ->route('rooms.show', [
                    'room' => $room->slug,
                    'tool' => $request->input('context_tool', 'settings'),
                ])
                ->with('status', $statusMessage);
        }

        return response()->json(['ok' => true]);
    }
}
