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
        // Only public rooms belong on the Rooms page.
        $rooms = Room::where('type', 'public')
            ->orderBy('created_at', 'desc')
            ->get();

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
            'type'        => 'public',
        ]);

        return redirect()
            ->route('rooms.show', $room->slug)
            ->with('status', 'Room created.');
    }

    public function show(Room $room)
    {
        // Block access to DMs you are not part of
        if ($room->type === 'dm') {
            $isMember = DB::table('room_users')
                ->where('room_id', $room->id)
                ->where('user_id', Auth::id())
                ->exists();

            abort_unless($isMember, 403);
        }

        $messages = $room->messages()
            ->withTrashed()
            ->with(['character', 'user'])
            ->latest()
            ->take(50)
            ->get()
            ->reverse();

        $cutoff = now()->subMinutes(5);

        // Right sidebar Rooms list inside a room view:
        // show public rooms only (keeps DMs out of the Rooms tab in the sidebar)
        $sidebarRooms = Room::query()
            ->where('rooms.type', 'public')
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

        $activeCharacterId = null;

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

    private function canModerate(): bool
    {
        return (bool) (Auth::user()->is_admin ?? false);
    }

    private function assertCanEditOrDelete(Message $message): void
    {
        $isOwner = $message->user_id === Auth::id();
        abort_unless($isOwner || $this->canModerate(), 403);
    }

    public function updateMessage(Request $request, Message $message)
    {
        $this->assertCanEditOrDelete($message);

        abort_if($message->deleted_at, 410);

        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        DB::table('message_edits')->insert([
            'message_id'      => $message->id,
            'editor_user_id'  => Auth::id(),
            'old_body'        => $message->body,
            'new_body'        => $request->body,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $message->body = $request->body;
        $message->save();

        return response()->json([
            'ok'      => true,
            'message' => $message->fresh()->load(['user', 'character']),
        ]);
    }

    public function deleteMessage(Request $request, Message $message)
    {
        $this->assertCanEditOrDelete($message);

        $message->deleted_by = Auth::id();
        $message->save();

        $message->delete();
        $message->refresh();

        return response()->json([
            'ok' => true,
            'id' => $message->id,
        ]);
    }

    public function storeMessage(Request $request, Room $room)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        // If this is a DM, ignore the client character_id and use the locked one
        if ($room->type === 'dm') {
            $this->assertDmMember($room);
            $characterId = $this->getLockedDmCharacterId($room);
        } else {
            $characterId = $this->getCharacterIdFromRequest($request);
        }

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
        $this->assertDmMember($room);
        $lastId = (int) $request->query('after', 0);
        $since  = $request->query('since');

        $q = $room->messages()
            ->withTrashed()
            ->with(['user', 'character'])
            ->orderBy('id');

        if ($since) {
            $q->where(function ($sub) use ($lastId, $since) {
                $sub->where('id', '>', $lastId)
                    ->orWhere('updated_at', '>', $since)
                    ->orWhere('deleted_at', '>', $since);
            });
        } else {
            $q->where('id', '>', $lastId);
        }

        return response()->json($q->get());
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

        // Sidebar should ONLY show public rooms (DMs live only in the floating DM window)
        $rooms = Room::query()
            ->where('rooms.type', 'public')
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
                'characters.settings as settings',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->get();

        return response()->json(['roster' => $roster]);
    }

    public function dmIndex()
    {
        $me = Auth::id();

        $rooms = DB::table('dm_participants as mine')
            ->join('rooms', 'rooms.id', '=', 'mine.room_id')
            ->join('dm_participants as other', function ($join) use ($me) {
                $join->on('other.room_id', '=', 'mine.room_id')
                    ->whereColumn('other.user_id', '!=', 'mine.user_id');
            })
            ->join('characters as other_char', 'other_char.id', '=', 'other.character_id')
            ->where('mine.user_id', $me)
            ->where('rooms.type', 'dm')
            ->orderByDesc('rooms.updated_at')
            ->select([
                'rooms.slug',
                'rooms.updated_at',
                'other_char.id as other_character_id',
                'other_char.name as other_character_name',
                'mine.character_id as my_character_id',
            ])
            ->get();

        return response()->json(['rooms' => $rooms]);
    }


    public function dmStart(Request $request)
    {
        $request->validate([
            'other_character_id' => ['required', 'integer'],
            'my_character_id'    => ['required', 'integer'],
        ]);

        $me = Auth::id();
        $myCharacterId = (int) $request->my_character_id;
        $otherCharacterId = (int) $request->other_character_id;

        abort_if($myCharacterId <= 0 || $otherCharacterId <= 0, 422);

        // my character must belong to me
        $owns = DB::table('characters')
            ->where('id', $myCharacterId)
            ->where('user_id', $me)
            ->exists();
        abort_unless($owns, 403);

        // other character must exist and not be mine
        $otherChar = DB::table('characters')->where('id', $otherCharacterId)->first();
        abort_unless($otherChar, 404);
        abort_if((int)$otherChar->user_id === (int)$me, 422);

        // Do we already have a DM room between these two characters?
        $existingRoomId = DB::table('dm_participants as a')
            ->join('dm_participants as b', function ($join) use ($myCharacterId, $otherCharacterId) {
                $join->on('a.room_id', '=', 'b.room_id')
                    ->where('a.character_id', '=', $myCharacterId)
                    ->where('b.character_id', '=', $otherCharacterId);
            })
            ->join('rooms', 'rooms.id', '=', 'a.room_id')
            ->where('rooms.type', 'dm')
            ->value('rooms.id');

        if ($existingRoomId) {
            $room = Room::find($existingRoomId);
            return response()->json(['slug' => $room->slug]);
        }

        // Create DM room
        $room = Room::create([
            'name'       => 'DM',
            'slug'       => 'dm-' . Str::random(20),
            'user_id'    => $me,
            'created_by' => $me,
            'type'       => 'dm',
        ]);

        DB::table('dm_participants')->insert([
            [
                'room_id' => $room->id,
                'user_id' => $me,
                'character_id' => $myCharacterId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'room_id' => $room->id,
                'user_id' => (int)$otherChar->user_id,
                'character_id' => $otherCharacterId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return response()->json(['slug' => $room->slug]);
    }


    public function dmMessages(Room $room, Request $request)
    {
        $this->assertDmMember($room);

        $after = (int) $request->query('after', 0);

        $q = $room->messages()
            ->with(['character', 'user'])
            ->orderBy('id');

        if ($after > 0) {
            $q->where('id', '>', $after);
        }

        return response()->json([
            'room' => [
                'id' => $room->id,
                'slug' => $room->slug,
                'name' => $room->name,
            ],
            'messages' => $q->take(100)->get(),
        ]);
    }

    public function dmSend(Room $room, Request $request)
    {
        $this->assertDmMember($room);

        $request->validate([
            'body' => 'required|string|max:2000',
            'character_id' => 'required|integer',
        ]);

        $characterId = (int) $request->character_id;
        $this->assertCharacterOwnedByUser($characterId);

        $message = $room->messages()->create([
            'user_id'      => Auth::id(),
            'character_id' => $characterId,
            'body'         => $request->body,
        ]);

        return response()->json([
            'ok' => true,
            'message' => $message->load(['user', 'character']),
        ]);
    }

        private function assertDmMember(Room $room): void
        {
            if ($room->type !== 'dm') return;

            $ok = DB::table('dm_participants')
                ->where('room_id', $room->id)
                ->where('user_id', Auth::id())
                ->exists();

            abort_unless($ok, 403);
        }

        private function getLockedDmCharacterId(Room $room): int
        {
            $cid = (int) DB::table('dm_participants')
                ->where('room_id', $room->id)
                ->where('user_id', Auth::id())
                ->value('character_id');

            abort_if($cid <= 0, 403);
            return $cid;
        }




}
