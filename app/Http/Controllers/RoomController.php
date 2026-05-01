<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Models\CharacterBlock;
use App\Models\Room;
use App\Models\Message;
use App\Models\MessageReport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\UniqueConstraintViolationException;

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
            $this->assertDmMember($room);
        }

        $activeCharacterId = $this->activeCharacterIdForRoom($room);

        if ($activeCharacterId) {
            $this->assertRoomAccess($room, $activeCharacterId);

            app(\App\Services\MarkConversationRead::class)(
                $activeCharacterId,
                $room->id
            );
        }

        $messages = $room->messages()
            ->withTrashed()
            ->with(['character', 'user'])
            ->latest()
            ->take(50)
            ->get()
            ->reverse();

        $this->applyBlockedMessageFlags($messages, $activeCharacterId);

        $cutoff = now()->subMinutes(5);

        $activePresenceCounts = DB::table('character_presences')
            ->where('last_seen_at', '>=', $cutoff)
            ->select('room_id', DB::raw('COUNT(*) as active_users'))
            ->groupBy('room_id');

        $sidebarRooms = Room::query()
            ->where('rooms.type', 'public')
            ->leftJoinSub($activePresenceCounts, 'active_presence_counts', function ($join) {
                $join->on('active_presence_counts.room_id', '=', 'rooms.id');
            })
            ->leftJoin('character_conversation_reads as ccr', function ($join) use ($activeCharacterId) {
                $join->on('ccr.conversation_id', '=', 'rooms.id')
                    ->where('ccr.character_id', '=', $activeCharacterId ?: 0);
            })
            ->select(
                'rooms.id',
                'rooms.name',
                'rooms.slug',
                DB::raw('COALESCE(active_presence_counts.active_users, 0) as active_users')
            )
            ->selectRaw('
                (
                    SELECT COUNT(*)
                    FROM messages m
                    WHERE m.room_id = rooms.id
                    AND m.deleted_at IS NULL
                    AND m.id > COALESCE(ccr.last_read_message_id, 0)
                    AND NOT EXISTS (
                        SELECT 1
                        FROM character_blocks cb
                        WHERE (
                            cb.blocker_character_id = ?
                            AND cb.blocked_character_id = m.character_id
                        ) OR (
                            cb.blocker_character_id = m.character_id
                            AND cb.blocked_character_id = ?
                        )
                    )
                ) as unread_count
            ', [$activeCharacterId ?: 0, $activeCharacterId ?: 0])
            ->orderBy('rooms.created_at', 'desc')
            ->get();

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

    private function activeCharacterIdForRoom(Room $room): ?int
    {
        if ($room->type === 'dm') {
            return $this->getLockedDmCharacterId($room);
        }

        return $this->activeOwnedCharacterId();
    }

    private function activeOwnedCharacterId(): ?int
    {
        $sessionCharacterId = (int) session('active_character_id', 0);

        if ($sessionCharacterId > 0 && DB::table('characters')
            ->where('id', $sessionCharacterId)
            ->where('user_id', Auth::id())
            ->exists()) {
            return $sessionCharacterId;
        }

        $firstCharacterId = (int) DB::table('characters')
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->value('id');

        if ($firstCharacterId <= 0) {
            return null;
        }

        session(['active_character_id' => $firstCharacterId]);

        return $firstCharacterId;
    }

    private function canModerate(): bool
    {
        return (bool) (Auth::user()->is_admin ?? false);
    }

    private function abortIfDmBlocked(int $firstCharacterId, int $secondCharacterId): void
    {
        abort_if(
            CharacterBlock::existsBetween($firstCharacterId, $secondCharacterId),
            403,
            'You cannot send a DM to this character.'
        );
    }

    private function dmParticipantCharacterIds(Room $room): array
    {
        return DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->orderBy('character_id')
            ->pluck('character_id')
            ->map(fn ($characterId) => (int) $characterId)
            ->all();
    }

    private function assertDmMessageAllowed(Room $room): void
    {
        $characterIds = $this->dmParticipantCharacterIds($room);

        abort_if(count($characterIds) !== 2, 403, 'You cannot send a DM to this character.');

        [$firstCharacterId, $secondCharacterId] = $characterIds;

        $this->abortIfDmBlocked($firstCharacterId, $secondCharacterId);
    }

    private function applyBlockedMessageFlags($messages, ?int $viewerCharacterId): void
    {
        if (! $viewerCharacterId || $this->canModerate()) {
            $messages->each->setAttribute('is_blocked_by_viewer', false);
            return;
        }

        // Room visibility is intentionally one-way: only characters the viewer blocked are collapsed.
        $blockedCharacterIds = CharacterBlock::query()
            ->where('blocker_character_id', $viewerCharacterId)
            ->pluck('blocked_character_id')
            ->map(fn ($characterId) => (int) $characterId)
            ->all();

        $blockedLookup = array_flip($blockedCharacterIds);

        $messages->each(function (Message $message) use ($blockedLookup) {
            $message->setAttribute(
                'is_blocked_by_viewer',
                $message->character_id && isset($blockedLookup[(int) $message->character_id])
            );
        });
    }

    private function assertCanEditOrDelete(Message $message): void
    {
        $isOwner = $message->user_id === Auth::id();
        abort_unless($isOwner || $this->canModerate(), 403);
    }

    private function assertRoomAccess(Room $room, int $characterId): void
    {
        // Today: all non-DM rooms are public
        if ($room->type === 'public') {
            return;
        }

        // DMs: must be a participant
        if ($room->type === 'dm') {
            $this->assertDmMember($room);
            return;
        }

        // Future: whitelist/blacklist membership checks go here
        abort(403);
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

    public function reportMessage(Request $request, Message $message)
    {
        abort_if($message->deleted_at, 410);

        $message->loadMissing('room');

        Gate::authorize('access-room', $message->room);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $report = MessageReport::firstOrCreate(
            [
                'message_id' => $message->id,
                'reporter_user_id' => Auth::id(),
            ],
            [
                'reason' => $validated['reason'],
                'status' => 'pending',
            ],
        );

        return response()->json([
            'ok' => true,
            'report_id' => $report->id,
        ], 201);
    }

    public function storeMessage(Request $request, Room $room)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        // If this is a DM, ignore the client character_id and use the locked one
        if ($room->type === 'dm') {
            $this->assertDmMember($room);
            $this->assertDmMessageAllowed($room);
            $characterId = $this->getLockedDmCharacterId($room);
        } else {
            $characterId = $this->getCharacterIdFromRequest($request);
        }

        $message = $room->messages()->create([
            'user_id'      => Auth::id(),
            'character_id' => $characterId,
            'body'         => $request->body,
        ]);

        broadcast(new MessageCreated($message))->toOthers();

        if ($request->wantsJson()) {
            return response()->json($message->load('user', 'character'));
        }

        return back();
    }

    public function latest(Room $room, Request $request)
    {
        // DMs require membership, public rooms do not
        if ($room->type === 'dm') {
            $this->assertDmMember($room);
        }

        $viewerCharacterId = null;
        if ($room->type === 'public') {
            $requestedCharacterId = (int) $request->query('character_id', 0);
            if ($requestedCharacterId > 0) {
                $this->assertCharacterOwnedByUser($requestedCharacterId);
                $viewerCharacterId = $requestedCharacterId;
            } else {
                $viewerCharacterId = $this->activeOwnedCharacterId();
            }
        }

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

        $messages = $q->get();
        $this->applyBlockedMessageFlags($messages, $viewerCharacterId);

        return response()->json($messages);
    }

    public function ping(Room $room, Request $request)
    {
        $characterId = $this->getCharacterIdFromRequest($request);
        $this->assertRoomAccess($room, $characterId);

        DB::table('character_presences')->updateOrInsert(
            ['character_id' => $characterId],
            [
                'room_id'      => $room->id,
                'last_seen_at' => now(),
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        app(\App\Services\MarkConversationRead::class)(
            $characterId,
            $room->id
        );

        return response()->json(['ok' => true]);
    }

    public function leave(Room $room, Request $request)
    {
        $characterId = $this->getCharacterIdFromRequest($request);
        $this->assertRoomAccess($room, $characterId);

        DB::table('character_presences')
            ->where('character_id', $characterId)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function sidebar()
    {
        $characterId = (int) request()->query('character_id', 0);

        if ($characterId > 0) {
            $this->assertCharacterOwnedByUser($characterId);
        } else {
            $characterId = $this->activeOwnedCharacterId() ?: 0;
        }

        $cutoff = now()->subMinutes(5);

        $activePresenceCounts = DB::table('character_presences')
            ->where('last_seen_at', '>=', $cutoff)
            ->select('room_id', DB::raw('COUNT(*) as active_users'))
            ->groupBy('room_id');

        $rooms = Room::query()
            ->where('rooms.type', 'public')
            ->leftJoinSub($activePresenceCounts, 'active_presence_counts', function ($join) {
                $join->on('active_presence_counts.room_id', '=', 'rooms.id');
            })
            ->leftJoin('character_conversation_reads as ccr', function ($join) use ($characterId) {
                $join->on('ccr.conversation_id', '=', 'rooms.id')
                    ->where('ccr.character_id', '=', $characterId);
            })
            ->select(
                'rooms.id',
                'rooms.name',
                'rooms.slug',
                DB::raw('COALESCE(active_presence_counts.active_users, 0) as active_users')
            )
            ->selectRaw('
                (
                    SELECT COUNT(*)
                    FROM messages m
                    WHERE m.room_id = rooms.id
                    AND m.deleted_at IS NULL
                    AND m.id > COALESCE(ccr.last_read_message_id, 0)
                    AND NOT EXISTS (
                        SELECT 1
                        FROM character_blocks cb
                        WHERE (
                            cb.blocker_character_id = ?
                            AND cb.blocked_character_id = m.character_id
                        ) OR (
                            cb.blocker_character_id = m.character_id
                            AND cb.blocked_character_id = ?
                        )
                    )
                ) as unread_count
            ', [$characterId, $characterId])
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
            ->leftJoin('character_conversation_reads as ccr', function ($join) {
                $join->on('ccr.conversation_id', '=', 'rooms.id')
                    ->whereColumn('ccr.character_id', 'mine.character_id');
            })
            ->where('mine.user_id', $me)
            ->where('rooms.type', 'dm')
            ->orderByDesc('rooms.updated_at')
            ->select([
                'rooms.id as room_id',
                'rooms.slug',
                'rooms.updated_at',
                'other_char.id as other_character_id',
                'other_char.name as other_character_name',
                'mine.character_id as my_character_id',
            ])
            ->selectRaw('
                EXISTS (
                    SELECT 1
                    FROM character_blocks cb
                    WHERE cb.blocker_character_id = mine.character_id
                    AND cb.blocked_character_id = other.character_id
                ) as is_blocked_by_viewer
            ')
            ->selectRaw('
                (
                    SELECT COUNT(*)
                    FROM messages m
                    WHERE m.room_id = rooms.id
                    AND m.deleted_at IS NULL
                    AND m.id > COALESCE(ccr.last_read_message_id, 0)
                    AND NOT EXISTS (
                        SELECT 1
                        FROM character_blocks cb
                        WHERE (
                            cb.blocker_character_id = mine.character_id
                            AND cb.blocked_character_id = m.character_id
                        ) OR (
                            cb.blocker_character_id = m.character_id
                            AND cb.blocked_character_id = mine.character_id
                        )
                    )
                ) as unread_count
            ')
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

        $owns = DB::table('characters')
            ->where('id', $myCharacterId)
            ->where('user_id', $me)
            ->exists();
        abort_unless($owns, 403);

        $otherChar = DB::table('characters')->where('id', $otherCharacterId)->first();
        abort_unless($otherChar, 404);
        abort_if((int) $otherChar->user_id === (int) $me, 422);
        $this->abortIfDmBlocked($myCharacterId, $otherCharacterId);

        $dmKey = Room::normalizedDmKey($myCharacterId, $otherCharacterId);

        if ($room = $this->findDmRoomForCharacterPair($myCharacterId, $otherCharacterId)) {
            return response()->json(['slug' => $room->slug]);
        }

        try {
            $room = DB::transaction(function () use ($me, $myCharacterId, $otherCharacterId, $otherChar, $dmKey) {
                if ($existing = $this->findDmRoomForCharacterPair($myCharacterId, $otherCharacterId)) {
                    return $existing;
                }

                $room = Room::create([
                    'name'       => 'DM',
                    'slug'       => 'dm-' . Str::random(20),
                    'user_id'    => $me,
                    'created_by' => $me,
                    'type'       => 'dm',
                    'dm_key'     => $dmKey,
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
                        'user_id' => (int) $otherChar->user_id,
                        'character_id' => $otherCharacterId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);

                return $room;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $room = Room::where('type', 'dm')->where('dm_key', $dmKey)->first();
            throw_unless($room, $exception);
        }

        return response()->json(['slug' => $room->slug]);
    }

    private function findDmRoomForCharacterPair(int $firstCharacterId, int $secondCharacterId): ?Room
    {
        [$lowCharacterId, $highCharacterId] = Room::normalizedDmPair($firstCharacterId, $secondCharacterId);

        $roomId = DB::table('rooms')
            ->join('dm_participants', 'dm_participants.room_id', '=', 'rooms.id')
            ->where('rooms.type', 'dm')
            ->groupBy('rooms.id')
            ->havingRaw('COUNT(*) = 2')
            ->havingRaw('COUNT(DISTINCT dm_participants.character_id) = 2')
            ->havingRaw('SUM(CASE WHEN dm_participants.character_id IN (?, ?) THEN 1 ELSE 0 END) = 2', [
                $lowCharacterId,
                $highCharacterId,
            ])
            ->orderBy('rooms.id')
            ->value('rooms.id');

        return $roomId ? Room::find($roomId) : null;
    }

    public function dmMessages(Room $room, Request $request)
    {
        $this->assertDmMember($room);
        $characterId = $this->getLockedDmCharacterId($room);

        app(\App\Services\MarkConversationRead::class)(
            $characterId,
            $room->id
        );

        $after = (int) $request->query('after', 0);

        $q = $room->messages()
            ->with(['character', 'user'])
            ->orderBy('id');

        if ($after > 0) {
            $q->where('id', '>', $after);
        }

        $messages = $q->take(100)->get();
        $this->applyBlockedMessageFlags($messages, $characterId);

        return response()->json([
            'room' => [
                'id' => $room->id,
                'slug' => $room->slug,
                'name' => $room->name,
            ],
            'messages' => $messages,
        ]);
    }

    public function dmSend(Room $room, Request $request)
    {
        abort_unless($room->type === 'dm', 404);
        $this->assertDmMember($room);
        $this->assertDmMessageAllowed($room);

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $characterId = $this->getLockedDmCharacterId($room);

        $message = $room->messages()->create([
            'user_id'      => Auth::id(),
            'character_id' => $characterId,
            'body'         => $request->body,
        ]);

        broadcast(new MessageCreated($message))->toOthers();

        return response()->json([
            'ok' => true,
            'message' => $message->load(['user', 'character']),
        ]);
    }

    private function assertDmMember(Room $room): void
    {
        if ($room->type !== 'dm') {
            return;
        }

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
