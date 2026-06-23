<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\RoomRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomRecoveryController extends Controller
{
    public function __construct(
        private readonly RoomRecoveryService $roomRecovery,
    ) {
    }

    public function restore(Request $request, string $room): RedirectResponse
    {
        $recoverableRoom = Room::withTrashed()->findOrFail((int) $room);

        abort_unless($this->roomRecovery->canRestoreRoom($request->user(), $recoverableRoom), 403);
        abort_unless($this->roomRecovery->isRecoverable($recoverableRoom), 404);

        $this->roomRecovery->restore($recoverableRoom);

        return redirect()
            ->route('rooms.index')
            ->with('status', 'Room restored successfully.');
    }
}
