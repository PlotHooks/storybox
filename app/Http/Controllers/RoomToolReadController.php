<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomToolRead;
use App\Services\RoomAccessService;
use App\Services\RoomToolIndicatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomToolReadController extends Controller
{
    public function __construct(
        private readonly RoomAccessService $roomAccess,
        private readonly RoomToolIndicatorService $toolIndicators,
    ) {
    }

    public function store(Request $request, Room $room, string $tool): JsonResponse
    {
        abort_if(! $room->isPublicRoom(), 404);
        abort_unless(in_array($tool, RoomToolRead::tools(), true), 404);

        $viewerCharacter = $this->toolIndicators->viewerCharacterForRoom($request->user(), $room);
        abort_unless($this->roomAccess->canViewRoom($request->user(), $room, $viewerCharacter), 403);

        $this->toolIndicators->markSeen($request->user(), $room, $tool);

        return response()->json([
            'ok' => true,
            'tool' => $tool,
            'last_seen_at' => now()->toIso8601String(),
        ]);
    }
}
