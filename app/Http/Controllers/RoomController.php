<?php

namespace App\Http\Controllers;

use App\Models\Room;
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
            'user_id'     => $userId,
            'created_by'  => $userId,
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

        $cutoff = now()->subMinutes(5);

        $sidebarRooms = Room::query()
            ->leftJoin('character_presences', function ($join) use ($cutoff) {
                $join->on('rooms.id', '=', 'character_presences.room_id')
                    ->where('character_presences.last_seen_at', '>=', $cutoff);
            })
            ->select(
                'rooms.id',
                'rooms.name',
                'rooms.slug',
                DB::raw('COUNT(character_presences.id) as active_users')
            )
            ->groupBy('rooms.id', 'rooms.name', 'rooms.slug')
            ->orderBy('rooms.created_at', 'desc')
            ->get();

        // NOTE: active character is now per-tab (client-side). We still pass something for initial select.
        $activeCharacterId = Auth::user()->characters()->value('id');

        return view('rooms.show', compact(
            'room',
            'messages',
            'activeCharacterId',
            'sidebarRooms'
        ));
    }

    private function assertCharacterOwnedByUser(int $characterId): void
    {
        $ok = DB::table('characters')
            ->where('id', $characterId)
            ->where('user_id', Auth::id())
            ->exists();

        abort_unless($ok, 403);
    }

    private function getCharacterIdFromRequest(Request $request): int
    {
        $characterId = (int) $request->input('character_id', 0);
        abort_if($characterId <= 0, 422, 'character_id is required');
        $this->assertCharacterOwnedByUser($characterId);
        return $characterId;
    }

    public function storeMessage(Request $request, Room $room)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $characterId = $this->getCharacterIdFromRequest($request);

        $message = $room->messages()->create([
            'user_id'      => Auth::id(),
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
        $lastId = (int) $request->query('after', 0);

        $messages = $room->messages()
            ->where('id', '>', $lastId)
            ->with(['user', 'character'])
            ->orderBy('id')
            ->get();

        return response()->json($messages);
    }

    public function ping(Room $room, Request $request)
    {
        $characterId = $this->getCharacterIdFromRequest($request);

        DB::table('character_presences')->updateOrInsert(
            ['character_id' => $characterId],
            [
                'room_id'      => $room->id,
                'last_seen_at' => now(),
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function leave(Room $room, Request $request)
    {
        $characterId = $this->getCharacterIdFromRequest($request);

        DB::table('character_presences')
            ->where('character_id', $characterId)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function sidebar()
    {
        $cutoff = now()->subMinutes(5);

        $rooms = Room::query()
            ->leftJoin('character_presences', function ($join) use ($cutoff) {
                $join->on('rooms.id', '=', 'character_presences.room_id')
                    ->where('character_presences.last_seen_at', '>=', $cutoff);
            })
            ->select(
                'rooms.id',
                'rooms.name',
                'rooms.slug',
                DB::raw('COUNT(character_presences.id) as active_users')
            )
            ->groupBy('rooms.id', 'rooms.name', 'rooms.slug')
            ->orderBy('rooms.created_at', 'desc')
            ->get();

        return response()->json(['rooms' => $rooms]);
    }

    public function roster(Room $room)
    {
        $cutoff = now()->subMinutes(5);

        $roster = DB::table('character_presences')
            ->join('characters', 'characters.id', '=', 'character_presences.character_id')
            ->join('users', 'users.id', '=', 'characters.user_id')
            ->where('character_presences.room_id', $room->id)
            ->where('character_presences.last_seen_at', '>=', $cutoff)
            ->orderBy('characters.name')
            ->select([
                'characters.id as character_id',
                'characters.name as character_name',
                'users.name as user_name',
            ])
            ->get();

        return response()->json(['roster' => $roster]);
    }
}
