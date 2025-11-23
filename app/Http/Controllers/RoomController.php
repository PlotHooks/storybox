<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'user_id'     => $request->user()->id,  // ⚠ important
            'name'        => $request->name,
            'slug'        => Str::slug($request->name) . '-' . uniqid(),
            'description' => $request->description,
            'created_by'  => $request->user()->id,  // also stored here
        ]);

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

    // NEW: sidebar room list
    $sidebarRooms = Room::orderBy('name')->get();

    return view('rooms.show', compact(
        'room',
        'messages',
        'activeCharacterId',
        'sidebarRooms'
    ));
}
public function sidebar()
{
    // Get all rooms, ordered by name for a stable list
    $rooms = Room::orderBy('name')->get();

    // Map them to a simple payload the JS expects
    $payload = $rooms->map(function (Room $room) {
        return [
            'id'           => $room->id,
            'name'         => $room->name,
            'slug'         => $room->slug,
            'active_users' => $room->active_users, // <- accessor we just added
        ];
    });

    return response()->json([
        'rooms' => $payload,
    ]);
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
            'user_id'      => $request->user()->id,
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
}
