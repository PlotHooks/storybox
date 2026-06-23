<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\RoomRecoveryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomRecoveryController extends Controller
{
    public function __construct(
        private readonly RoomRecoveryService $roomRecovery,
    ) {
    }

    public function index(Request $request): View
    {
        $recoverableRooms = $this->roomRecovery
            ->recoverableRoomsForUser($request->user())
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'description' => $room->description,
                'visibility' => $room->visibility ?? Room::VISIBILITY_PUBLIC,
                'owner_name' => optional($room->ownerCharacter)->name ?? optional($room->creator)->name ?? 'Unknown',
                'deleted_at' => $room->deleted_at,
                'recovery_expires_at' => $this->roomRecovery->recoveryExpiresAt($room),
            ]);

        return view('rooms.recovery', compact('recoverableRooms'));
    }

    public function restore(Request $request, string $room): RedirectResponse
    {
        $recoverableRoom = Room::withTrashed()->findOrFail((int) $room);

        abort_unless($this->roomRecovery->canRestoreRoom($request->user(), $recoverableRoom), 403);
        abort_unless($this->roomRecovery->isRecoverable($recoverableRoom), 404);

        $this->roomRecovery->restore($recoverableRoom);

        return redirect()
            ->route('rooms.recovery')
            ->with('status', 'Room restored successfully.');
    }
}
