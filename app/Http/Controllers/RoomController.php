<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
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

        $userId = Auth::id();

        $room = Room::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name) . '-' . uniqid(),
            'description' => $request->description,
            // keep both so DB is happy and owner() still works
            'user_id'     => $userId,
            'created_by'  => $userId,
        ]);

        return redirect()
            ->route('rooms.show', $room->slug)
            ->with('status', 'Room created.');
    }

    public function show(Room $room)
    {
        // last 50 messages, oldest at top
        $messages = $room->messages()
            ->with(['character', 'user'])
            ->latest()
            ->take(50)
            ->get()
            ->reverse();

        $activeCharacterId = session('active_character_id');

        // sidebar: rooms + active user counts based on room_presences
        $sidebarRooms = Room::query()
            ->leftJoin('room_presences', 'rooms.id', '=', 'room_presences.room_id')
            ->select(
                'rooms.*',
                DB::raw('COUNT(room_presences.id) as active_users')
            )
            ->groupBy('rooms.id')
            ->orderBy('rooms.created_at', 'desc')
            ->get();

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
            'user_id'      => Auth::id(),
            'character_id' => $characterId,
            'body'         => $request->body,
        ]);

        if ($request->wantsJson()) {
            return response()->json(
                $message->load('user', 'character')
            );
        }

        return back();
    }

    public function latest(Room $room, Request $request)
    {
        $lastId = (int) $request->query('after', 0);

        $messages = $room->messages()
            ->where('id', '>', $lastId)
            ->with(['user', 'character'])
            ->orderBy('id')
            ->get();

        return response()->json($messages);
    }

    /**
     * Presence ping: mark current user as "seen" in this room.
     */
    public function ping(Room $room, Request $request)
    {
        $userId = $request->user()->id;

        DB::table('room_presences')->updateOrInsert(
            [
                'room_id' => $room->id,
                'user_id' => $userId,
            ],
            [
                'last_seen_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Sidebar data: list of rooms + active_users counts.
     */
    public function sidebar()
    {
        $cutoff = now()->subMinutes(5);

        $rooms = Room::query()
            ->leftJoin('room_presences', function ($join) use ($cutoff) {
                $join->on('rooms.id', '=', 'room_presences.room_id')
                     ->where('room_presences.last_seen_at', '>=', $cutoff);
            })
            ->select(
                'rooms.id',
                'rooms.name',
                'rooms.slug',
                DB::raw('COUNT(room_presences.id) as active_users')
            )
            ->groupBy('rooms.id', 'rooms.name', 'rooms.slug')
            ->orderBy('rooms.created_at', 'desc')
            ->get();

        return response()->json([
            'rooms' => $rooms,
        ]);
    }
}
