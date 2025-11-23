<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::orderBy('created_at', 'desc')->get();
        return view('rooms.index', compact('rooms'));
    }

    public function create()
    {
        return view('rooms.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $room = Room::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name) . '-' . uniqid(),
            'description' => $request->description,
            'created_by'  => auth()->id(),
        });

        return redirect()
            ->route('rooms.show', $room->slug)
            ->with('status', 'Room created.');
    }

    public function show(Room $room)
    {
        $messages = $room->messages()
            ->with(['character', 'user'])
            ->latest()
            ->take(50)
            ->get()
            ->reverse();

        $activeCharacterId = session('active_character_id');

        // rooms for the right-hand sidebar, with real presence counts
        $sidebarRooms = $this->getSidebarRooms();

        return view('rooms.show', compact(
            'room',
            'messages',
            'activeCharacterId',
            'sidebarRooms'
        ));
    }

    public function storeMessage(Request $request, Room $room)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $characterId = session('active_character_id');

        if (! $characterId) {
            return back()->withErrors([
                'body' => 'You must select an active character before posting.',
            ]);
        }

        $message = $room->messages()->create([
            'room_id'      => $room->id,
            'user_id'      => auth()->id(),
            'character_id' => $characterId,
            'body'         => $request->body,
        ]);

        if ($request->wantsJson()) {
            return response()->json($message->load('user', 'character'));
        }

        return back();
    }

    public function latest(Room $room, Request $request)
    {
        $lastId = $request->query('after', 0);

        $messages = $room->messages()
            ->where('id', '>', $lastId)
            ->with(['user', 'character'])
            ->orderBy('id')
            ->get();

        return response()->json($messages);
    }

    /**
     * Presence ping: mark the current user as present in this room.
     */
    public function ping(Room $room, Request $request)
    {
        $userId = $request->user()->id;

        DB::table('room_user_presence')->updateOrInsert(
            ['room_id' => $room->id, 'user_id' => $userId],
            [
                'last_seen_at' => now(),
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * JSON list of rooms for the right-hand sidebar, with active user counts.
     */
    public function sidebar()
    {
        $rooms = $this->getSidebarRooms();

        return response()->json([
            'rooms' => $rooms->map(fn ($room) => [
                'id'           => $room->id,
                'name'         => $room->name,
                'slug'         => $room->slug,
                'active_users' => $room->active_users,
            ]),
        ]);
    }

    /**
     * Helper to build sidebar room list using presence table.
     */
    protected function getSidebarRooms()
    {
        $cutoff = now()->subSeconds(90);

        // All rooms
        $rooms = Room::orderBy('created_at', 'desc')->get();

        // Presence counts in the last 90 seconds
        $presenceCounts = DB::table('room_user_presence')
            ->select('room_id', DB::raw('COUNT(DISTINCT user_id) as cnt'))
            ->where('last_seen_at', '>=', $cutoff)
            ->groupBy('room_id')
            ->pluck('cnt', 'room_id'); // [room_id => count]

        // Attach active_users property
        $rooms->each(function ($room) use ($presenceCounts) {
            $room->active_users = $presenceCounts[$room->id] ?? 0;
        });

        return $rooms;
    }
}
