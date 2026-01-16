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
            'user_id'     => $userId,
            'created_by'  => $userId,
        ]);

        return redirect()
            ->route('rooms.show', $room->slug)
            ->with('status', 'Room created.');
    }

    public function show(Room $room)
    {
        // Include soft-deleted messages so the UI can show [deleted] placeholders
        $messages = $room->messages()
            ->withTrashed()
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
        // If you have is_admin on users, this works.
        // If you do not, it behaves as author-only.
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

        // Don't allow editing a deleted message
        abort_if($message->deleted_at, 410, 'Message is deleted');

        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        // audit
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

        $message->delete(); // soft delete
        $message->refresh(); // ensure deleted_at is populated

        return response()->json([
            'ok'         => true,
            'id'         => $message->id,
            'deleted_at' => optional($message->deleted_at)->toISOString(),
        ]);
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
        $since  = $request->query('since'); // ISO string

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

        $messages = $q->get();

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
                'characters.settings as settings',
                'users.name as user_name',
            ])
            ->get();

        return response()->json(['roster' => $roster]);
    }
}
