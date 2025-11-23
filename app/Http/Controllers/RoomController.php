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
            'name'        => $request->name,
            'slug'        => Str::slug($request->name) . '-' . uniqid(),
            'description' => $request->description,
            'created_by'  => auth()->id(),
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

        return view('rooms.show', compact('room', 'messages', 'activeCharacterId'));
    }

    public function storeMessage(Request $request, Room $room)
    {
        // validate incoming message text
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
            'room_id'     => $room->id,
            'user_id'     => auth()->id(),
            'character_id'=> $characterId,
            'body'        => $request->body,
        ]);

        // AJAX support
        if ($request->wantsJson()) {
            return response()->json($message->load('user', 'character'));
        }

        return back();
    }
}
