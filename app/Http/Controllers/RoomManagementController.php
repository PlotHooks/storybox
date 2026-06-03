<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Room;
use App\Models\RoomAccessEntry;
use App\Models\RoomCharacterRole;
use App\Services\RoomAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomManagementController extends Controller
{
    public function __construct(
        private readonly RoomAccessService $roomAccess,
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

    public function addWhitelist(Request $request, Room $room)
    {
        return $this->upsertAccessEntry($request, $room, RoomAccessEntry::TYPE_WHITELIST);
    }

    public function removeWhitelist(Request $request, Room $room, Character $character)
    {
        return $this->deleteAccessEntry($request, $room, $character, RoomAccessEntry::TYPE_WHITELIST);
    }

    public function addBlacklist(Request $request, Room $room)
    {
        return $this->upsertAccessEntry($request, $room, RoomAccessEntry::TYPE_BLACKLIST);
    }

    public function removeBlacklist(Request $request, Room $room, Character $character)
    {
        return $this->deleteAccessEntry($request, $room, $character, RoomAccessEntry::TYPE_BLACKLIST);
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
                ->route('rooms.index')
                ->with('status', 'Room deleted successfully.');
        }

        return response()->json(['ok' => true]);
    }

    private function upsertAccessEntry(Request $request, Room $room, string $type)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->canManageRoomAccess($request->user(), $room, $actor), 403);

        $target = $this->targetCharacterFromRequest($request);
        $this->assertTargetCanBeManaged($request, $room, $actor, $target);

        if ($type === RoomAccessEntry::TYPE_BLACKLIST) {
            RoomAccessEntry::query()
                ->where('room_id', $room->id)
                ->where('character_id', $target->id)
                ->where('type', RoomAccessEntry::TYPE_WHITELIST)
                ->delete();

            // Blacklisting should take effect immediately for active room presence.
            $room->characterPresences()
                ->where('character_id', $target->id)
                ->delete();
        }

        RoomAccessEntry::query()->updateOrCreate(
            [
                'room_id' => $room->id,
                'character_id' => $target->id,
                'type' => $type,
            ],
            [
                'created_by_character_id' => $actor->id,
            ],
        );

        return $this->managementResponse(
            $request,
            $room,
            $type === RoomAccessEntry::TYPE_WHITELIST ? 'Whitelist updated.' : 'Blacklist updated.'
        );
    }

    private function deleteAccessEntry(Request $request, Room $room, Character $target, string $type)
    {
        $this->abortIfNotManagedPublicRoom($room);

        $actor = $this->ownedCharacterFromRequest($request);
        abort_unless($this->roomAccess->canManageRoomAccess($request->user(), $room, $actor), 403);
        $this->assertTargetCanBeManaged($request, $room, $actor, $target);

        RoomAccessEntry::query()
            ->where('room_id', $room->id)
            ->where('character_id', $target->id)
            ->where('type', $type)
            ->delete();

        return $this->managementResponse(
            $request,
            $room,
            $type === RoomAccessEntry::TYPE_WHITELIST ? 'Whitelist entry removed.' : 'Blacklist entry removed.'
        );
    }

    private function assertTargetCanBeManaged(Request $request, Room $room, Character $actor, Character $target): void
    {
        if ((int) $room->owner_character_id === (int) $target->id) {
            abort_if(! $this->roomAccess->isAdmin($request->user()), 403);
            abort(422, 'The room owner cannot be changed through this endpoint.');
        }

        if (! $this->roomAccess->isOwner($room, $actor) && $this->roomAccess->isModerator($room, $target)) {
            abort(403);
        }
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
