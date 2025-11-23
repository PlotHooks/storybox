<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Message;
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
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $room = Room::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . uniqid(),
            'description' => $request->description,
        ]);

        return redirect()->route('rooms.show', $room->slug)
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

        return view('rooms.show', compact('room', 'messages', 'activeCharacterId'));
    }

    public function storeMessage(Request $request, Room $room)
    {
        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $characterId = session('active_character_id');

        if (! $characterId) {
            return back()->withErrors([
                'content' => 'You must select an active character before posting.',
            ]);
        }

        Message::create([
            'room_id' => $room->id,
            'user_id' => auth()->id(),
            'character_id' => $characterId,
            'content' => $request->content,
        ]);

        return back();
    }
}
