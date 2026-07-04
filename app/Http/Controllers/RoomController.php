<?php

namespace App\Http\Controllers;

use App\Events\DmNotificationCreated;
use App\Events\MessageCreated;
use App\Events\ModerationMessageCreated;
use App\Events\RoomDisplayCleared;
use App\Models\Character;
use App\Models\CharacterBlock;
use App\Models\CharacterPresence;
use App\Models\Room;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\UserRoomState;
use App\Services\ChatInputParser;
use App\Services\DiceMessageFormatter;
use App\Services\MarkPublicRoomRead;
use App\Services\MessageRichTextRenderer;
use App\Services\RoomAccessService;
use App\Services\RoomHistoryExportFormatter;
use App\Services\RoomParticipationStateService;
use App\Services\RoomToolIndicatorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function __construct(
        private readonly RoomAccessService $roomAccess,
    ) {
    }

    public function landing()
    {
        return redirect()->to(app(\App\Services\RoomLandingService::class)->destinationFor(Auth::user()));
    }

    public function index()
    {
        $activeCharacter = $this->activeOwnedCharacter();

        $rooms = $this->roomAccess
            ->applyVisiblePublicRoomScope(Room::query(), Auth::user(), $activeCharacter)
            ->with(['creator', 'ownerCharacter'])
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

        app(\App\Services\RoomRetentionService::class)->ensureCanCreatePublicRoom(Auth::user());

        $userId = Auth::id();

        $room = Room::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name) . '-' . uniqid(),
            'description' => $request->description,
            'user_id'     => $userId,
            'created_by'  => $userId,
            'type'        => Room::TYPE_PUBLIC,
            'visibility'  => Room::VISIBILITY_PUBLIC,
            'owner_character_id' => $this->activeOwnedCharacterId(),
        ]);

        UserRoomState::updateOrCreate(
            [
                'user_id' => $userId,
                'room_id' => $room->id,
            ],
            [
                'is_following' => true,
            ]
        );

        return redirect()
            ->route('rooms.show', $room->slug)
            ->with('status', 'Room created.');
    }

    public function show(Room $room)
    {
        // rooms table is the conversation model.
        [$activeCharacterId, $characterSelectionNotice] = $this->activeCharacterSelectionForConversation($room);
        $activeCharacter = $this->ownedCharacterById($activeCharacterId);

        if ($room->isPublicRoom()) {
            abort_unless($this->roomAccess->canViewRoom(Auth::user(), $room, $activeCharacter), 403);
            $this->markPublicRoomRead($room->id);
        }

        if ($activeCharacterId) {
            $this->assertConversationParticipant($room, $activeCharacterId);

            if ($room->type === 'dm') {
                app(\App\Services\MarkConversationRead::class)(
                    $activeCharacterId,
                    $room->id
                );
            }
        }

        $messages = $room->messages()
            ->withTrashed()
            ->with(['character', 'user'])
            ->latest()
            ->take(50)
            ->get()
            ->reverse();

        $this->applyBlockedMessageFlags($messages, $activeCharacterId);
        $this->hydrateRenderedMessages($messages);

        $sidebarRooms = $this->sidebarRoomsForPublicRooms($activeCharacter)->get();
        $isFollowingRoom = (bool) ($sidebarRooms->firstWhere('id', $room->id)->is_following ?? false);

        $canManageRoom = false;
        $canManageModerators = false;
        $canDeleteRoom = false;
        $roomModerators = collect();
        $roomWhitelist = collect();
        $roomBlacklist = collect();

        if ($room->isPublicRoom() && $activeCharacter) {
            $canManageRoom = $this->roomAccess->canManageRoom(Auth::user(), $room, $activeCharacter);
            $canManageModerators = $this->roomAccess->isOwner($room, $activeCharacter) || $this->roomAccess->isAdmin(Auth::user());
            $canDeleteRoom = $this->roomAccess->canDeleteRoom(Auth::user(), $room, $activeCharacter);
        }

        if ($room->isPublicRoom()) {
            $roomModerators = $room->roomCharacterRoles()
                ->with('character')
                ->where('role', \App\Models\RoomCharacterRole::ROLE_MODERATOR)
                ->get()
                ->filter(fn ($role) => $role->character !== null)
                ->sortBy(fn ($role) => strtolower($role->character->name))
                ->values();

            $roomWhitelist = $room->roomAccessEntries()
                ->with('character')
                ->where('type', \App\Models\RoomAccessEntry::TYPE_WHITELIST)
                ->get()
                ->filter(fn ($entry) => $entry->character !== null)
                ->sortBy(fn ($entry) => strtolower($entry->character->name))
                ->values();

            $roomBlacklist = $room->roomAccessEntries()
                ->with('character')
                ->where('type', \App\Models\RoomAccessEntry::TYPE_BLACKLIST)
                ->get()
                ->filter(fn ($entry) => $entry->character !== null)
                ->sortBy(fn ($entry) => strtolower($entry->character->name))
                ->values();
        }

        $roomToolIndicators = $room->isPublicRoom()
            ? app(RoomToolIndicatorService::class)->indicatorsFor(Auth::user(), $room)
            : [];

        $roomRecovery = app(\App\Services\RoomRecoveryService::class);
        $recoverableRoomCount = $roomRecovery->recoverableRoomCountForUser(Auth::user());
        $showRecoveryLink = $this->roomAccess->isAdmin(Auth::user()) || $recoverableRoomCount > 0;

        $roomParticipationTokens = [];

        if ($room->isPublicRoom()) {
            $participationState = app(RoomParticipationStateService::class);

            foreach (Auth::user()->characters()->orderBy('name')->get() as $ownedCharacter) {
                if ($this->roomAccess->canViewRoom(Auth::user(), $room, $ownedCharacter)) {
                    $roomParticipationTokens[$ownedCharacter->id] = $participationState->issueToken($room, $ownedCharacter);
                }
            }
        }

        return view('rooms.show', compact(
            'room',
            'messages',
            'activeCharacterId',
            'characterSelectionNotice',
            'sidebarRooms',
            'canManageRoom',
            'canManageModerators',
            'canDeleteRoom',
            'roomModerators',
            'roomWhitelist',
            'roomBlacklist',
            'isFollowingRoom',
            'roomToolIndicators',
            'roomParticipationTokens',
            'showRecoveryLink',
            'recoverableRoomCount'
        ));
    }

    public function profile(Room $room): View
    {
        [$activeCharacterId] = $this->activeCharacterSelectionForConversation($room);
        $activeCharacter = $this->ownedCharacterById($activeCharacterId);

        abort_unless($room->isPublicRoom(), 404);
        abort_unless($this->roomAccess->canViewRoom(Auth::user(), $room, $activeCharacter), 403);

        $canManageRoom = false;

        if ($activeCharacter) {
            $canManageRoom = $this->roomAccess->canManageRoom(Auth::user(), $room, $activeCharacter);
        }

        return $this->renderProfilePage($room, $canManageRoom);
    }

    public function history(Room $room, Request $request): View
    {
        [$activeCharacterId] = $this->activeCharacterSelectionForConversation($room);
        $activeCharacter = $this->ownedCharacterById($activeCharacterId);

        abort_unless($room->isPublicRoom(), 404);
        abort_unless($this->roomAccess->canViewRoom(Auth::user(), $room, $activeCharacter), 403);

        $canManageRoom = $activeCharacter !== null
            && $this->roomAccess->canManageRoom(Auth::user(), $room, $activeCharacter);

        $today = now()->startOfDay();
        $cutoffDate = $today->copy()->subDays(29);

        $request->validate([
            'day' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $activeDays = $room->messages()
            ->withTrashed()
            ->where('created_at', '>=', $cutoffDate)
            ->selectRaw('DATE(created_at) as history_day, COUNT(*) as message_count')
            ->groupBy('history_day')
            ->orderBy('history_day')
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->history_day,
                'message_count' => (int) $row->message_count,
            ])
            ->values();

        $activeDayDates = $activeDays->pluck('date')->values();
        $activeDayCounts = $activeDays->pluck('message_count', 'date');
        $selectedDay = $this->resolveRoomHistoryDay(
            trim((string) $request->query('day', '')),
            $activeDayDates->all(),
            $cutoffDate,
            $today,
        );

        $messages = $room->messages()
            ->withTrashed()
            ->with('character')
            ->where('created_at', '>=', $cutoffDate)
            ->whereBetween('created_at', [$selectedDay->copy()->startOfDay(), $selectedDay->copy()->endOfDay()])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $this->hydrateRenderedMessages($messages);

        $selectedDayString = $selectedDay->toDateString();
        $calendarDays = collect(range(0, 29))
            ->map(function (int $offset) use ($today, $selectedDayString, $activeDayCounts, $room): array {
                $day = $today->copy()->subDays($offset);
                $date = $day->toDateString();
                $messageCount = (int) ($activeDayCounts[$date] ?? 0);

                return [
                    'date' => $date,
                    'label' => $day->format('D, M j'),
                    'day_number' => $day->format('j'),
                    'month_label' => strtoupper($day->format('M')),
                    'is_selected' => $date === $selectedDayString,
                    'is_active' => $messageCount > 0,
                    'message_count' => $messageCount,
                    'url' => route('rooms.history.show', ['room' => $room->slug, 'day' => $date]),
                ];
            })
            ->values();

        $previousActiveDay = $activeDayDates
            ->filter(fn (string $date): bool => $date < $selectedDayString)
            ->last();

        $nextActiveDay = $activeDayDates->first(
            fn (string $date): bool => $date > $selectedDayString
        );

        return view('rooms.history', [
            'room' => $room,
            'canManageRoom' => $canManageRoom,
            'selectedDay' => $selectedDay,
            'selectedDayString' => $selectedDayString,
            'selectedDayHasMessages' => (bool) ($activeDayCounts[$selectedDayString] ?? false),
            'selectedDayMessageCount' => (int) ($activeDayCounts[$selectedDayString] ?? 0),
            'calendarDays' => $calendarDays,
            'messages' => $messages,
            'historyExportRows' => app(RoomHistoryExportFormatter::class)->rowsFromMessages($messages),
            'previousActiveDayUrl' => $previousActiveDay
                ? route('rooms.history.show', ['room' => $room->slug, 'day' => $previousActiveDay])
                : null,
            'nextActiveDayUrl' => $nextActiveDay
                ? route('rooms.history.show', ['room' => $room->slug, 'day' => $nextActiveDay])
                : null,
        ]);
    }

    public function profileFrame(Room $room): Response
    {
        [$activeCharacterId] = $this->activeCharacterSelectionForConversation($room);
        $activeCharacter = $this->ownedCharacterById($activeCharacterId);

        abort_unless($room->isPublicRoom(), 404);
        abort_unless($this->roomAccess->canViewRoom(Auth::user(), $room, $activeCharacter), 403);
        abort_unless($room->usesAdvancedProfile(), 404);

        return $this->frameResponse(
            view('rooms.profile-custom-frame', [
                'customDocument' => $this->buildCustomDocument(
                    $room,
                    $room->profile_custom_html,
                    $room->profile_custom_css,
                    $room->profile_custom_js,
                ),
            ])
        );
    }

    private function resolveRoomHistoryDay(string $requestedDay, array $activeDayDates, Carbon $cutoffDate, Carbon $today): Carbon
    {
        if ($requestedDay !== '') {
            try {
                $selectedDay = Carbon::createFromFormat('Y-m-d', $requestedDay, config('app.timezone'))->startOfDay();

                if ($selectedDay->betweenIncluded($cutoffDate, $today)) {
                    return $selectedDay;
                }
            } catch (\Throwable) {
                // Invalid day input falls back to the default selection.
            }
        }

        if (in_array($today->toDateString(), $activeDayDates, true)) {
            return $today->copy();
        }

        if ($activeDayDates !== []) {
            return Carbon::createFromFormat('Y-m-d', (string) end($activeDayDates), config('app.timezone'))->startOfDay();
        }

        return $today->copy();
    }

    private function renderProfilePage(Room $room, bool $canManageRoom): View
    {
        if ($room->usesAdvancedProfile()) {
            return view('rooms.profile-show-advanced', [
                'room' => $room,
                'canManageRoom' => $canManageRoom,
            ]);
        }

        $room->loadMissing('roomRules');

        return view('rooms.profile-show', [
            'room' => $room,
            'canManageRoom' => $canManageRoom,
        ]);
    }

    private function frameResponse(View $view): Response
    {
        return response($view)
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    private function buildCustomDocument(Room $room, ?string $customHtml, ?string $customCss, ?string $customJs): string
    {
        $customHtml = $customHtml ?? '';
        $customCss = $customCss ?? '';
        $customJs = $customJs ?? '';

        $baseStyle = <<<'CSS'
html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100vh;
    background: transparent;
}
CSS;

        [$stylesheetLinks, $remainingCss] = $this->extractStylesheetImports($customCss);
        $headAssets = $stylesheetLinks.'<style>'.$baseStyle.$remainingCss.'</style>';
        $scriptBlock = $customJs !== '' ? '<script>'.$customJs.'</script>' : '';
        $trimmedHtml = ltrim($customHtml);
        $looksLikeFullDocument = preg_match('/<(?:!doctype|html|head|body)\b/i', $trimmedHtml) === 1;

        if (! $looksLikeFullDocument) {
            return '<!DOCTYPE html>'
                . '<html lang="'.e(str_replace('_', '-', app()->getLocale())).'">'
                . '<head>'
                . '<meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>'.e($room->name.' Custom Profile').'</title>'
                . $headAssets
                . '</head>'
                . '<body>'
                . $customHtml
                . $scriptBlock
                . '</body>'
                . '</html>';
        }

        $document = $customHtml;

        if (stripos($document, '<head') !== false) {
            $document = preg_replace('/<\/head>/i', $headAssets.'</head>', $document, 1) ?? $document;
        } elseif (stripos($document, '<html') !== false) {
            $document = preg_replace('/<html([^>]*)>/i', '<html$1><head>'.$headAssets.'</head>', $document, 1) ?? $document;
        } else {
            $document = $headAssets.$document;
        }

        if ($scriptBlock !== '') {
            if (stripos($document, '</body>') !== false) {
                $document = preg_replace('/<\/body>/i', $scriptBlock.'</body>', $document, 1) ?? $document;
            } else {
                $document .= $scriptBlock;
            }
        }

        return $document;
    }

    private function extractStylesheetImports(string $customCss): array
    {
        $links = [];
        $remainingCss = preg_replace_callback(
            '/^\s*@import\s+url\(\s*(["\']?)(https?:\/\/[^)"\'\s]+)\1\s*\)\s*;?/im',
            function (array $matches) use (&$links): string {
                $url = $matches[2] ?? '';
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

                if (! in_array($scheme, ['http', 'https'], true)) {
                    return $matches[0];
                }

                $links[] = '<link rel="stylesheet" href="'.e($url).'">';

                return '';
            },
            $customCss,
        );

        return [implode('', $links), $remainingCss ?? $customCss];
    }

    private function assertCharacterOwnedByUser(int $characterId): void
    {
        $ok = DB::table('characters')
            ->where('id', $characterId)
            ->where('user_id', Auth::id())
            ->exists();

        if (! $ok) {
            $this->logSuspiciousAuthorizationFailure('character_not_owned', [
                'submitted_character_id' => $characterId,
            ]);
        }

        abort_unless($ok, 403);
    }

    private function logSuspiciousAuthorizationFailure(string $reason, array $context = []): void
    {
        Log::warning('Suspicious chat authorization failure', array_filter([
            'user_id' => Auth::id(),
            'submitted_character_id' => $context['submitted_character_id'] ?? null,
            'room_id' => $context['room_id'] ?? null,
            'message_id' => $context['message_id'] ?? null,
            'route' => request()->route()?->getName(),
            'reason' => $reason,
        ], fn ($value) => $value !== null));
    }

    private function getCharacterIdFromRequest(Request $request): int
    {
        $characterId = (int) $request->input('character_id', 0);
        abort_if($characterId <= 0, 422, 'character_id is required');

        $this->assertCharacterOwnedByUser($characterId);

        return $characterId;
    }

    private function activeCharacterIdForConversation(Room $conversation): ?int
    {
        if ($conversation->type === Room::TYPE_DM) {
            return $this->getLockedDmCharacterId($conversation);
        }

        return $this->activeOwnedCharacterId();
    }

    private function activeCharacterSelectionForConversation(Room $conversation): array
    {
        if ($conversation->type === Room::TYPE_DM) {
            return [$this->getLockedDmCharacterId($conversation), null];
        }

        $preferredCharacter = $this->preferredOwnedCharacter();

        if (
            $preferredCharacter !== null
            && $preferredCharacter->is_active
            && $this->roomAccess->canViewRoom(Auth::user(), $conversation, $preferredCharacter)
        ) {
            return [$preferredCharacter->id, null];
        }

        if (
            $preferredCharacter !== null
            && $preferredCharacter->is_active
            && $this->roomAccess->isBlacklisted($conversation, $preferredCharacter)
        ) {
            return [$preferredCharacter->id, null];
        }

        $fallbackCharacter = Character::query()
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->first(fn (Character $character) => $this->roomAccess->canViewRoom(Auth::user(), $conversation, $character));

        if ($fallbackCharacter !== null) {
            $notice = $preferredCharacter !== null
                ? 'Posting as reset to '.$fallbackCharacter->name.' for this room.'
                : null;

            return [$fallbackCharacter->id, $notice];
        }

        return [null, null];
    }

    private function messageCharacterIdForConversation(Room $conversation, Request $request): int
    {
        if ($conversation->type === Room::TYPE_DM) {
            return $this->getLockedDmCharacterId($conversation);
        }

        return $this->getCharacterIdFromRequest($request);
    }

    private function activeOwnedCharacterId(): ?int
    {
        return $this->activeOwnedCharacter()?->id;
    }

    private function activeOwnedCharacter(): ?Character
    {
        return $this->preferredOwnedCharacter()
            ?? Character::query()
                ->where('user_id', Auth::id())
                ->orderBy('name')
                ->first();
    }

    private function preferredOwnedCharacter(): ?Character
    {
        $sessionCharacterId = (int) session('active_character_id', 0);

        if ($sessionCharacterId <= 0) {
            return null;
        }

        return Character::query()
            ->where('id', $sessionCharacterId)
            ->where('user_id', Auth::id())
            ->first();
    }

    private function ownedCharacterById(?int $characterId): ?Character
    {
        if (($characterId ?? 0) <= 0) {
            return null;
        }

        return Character::query()
            ->where('id', $characterId)
            ->where('user_id', Auth::id())
            ->first();
    }

    private function canModerate(): bool
    {
        return $this->roomAccess->isAdmin(Auth::user());
    }

    private function markPublicRoomRead(int $roomId, ?int $latestMessageId = null): void
    {
        app(MarkPublicRoomRead::class)(Auth::id(), $roomId, $latestMessageId);
    }

    private function sidebarRoomsForPublicRooms(?Character $character)
    {
        $cutoff = Room::activePresenceCutoff();
        $userId = (int) Auth::id();

        $activePresenceCounts = DB::table('character_presences')
            ->where('last_seen_at', '>=', $cutoff)
            ->select('room_id', DB::raw('COUNT(*) as active_users'))
            ->groupBy('room_id');

        return $this->roomAccess
            ->applyVisiblePublicRoomScope(Room::query(), Auth::user(), $character)
            ->leftJoinSub($activePresenceCounts, 'active_presence_counts', function ($join) {
                $join->on('active_presence_counts.room_id', '=', 'rooms.id');
            })
            ->leftJoin('user_room_states as urs', function ($join) use ($userId) {
                $join->on('urs.room_id', '=', 'rooms.id')
                    ->where('urs.user_id', '=', $userId);
            })
            ->select(
                'rooms.id',
                'rooms.name',
                'rooms.description',
                'rooms.slug',
                'rooms.updated_at',
                DB::raw('COALESCE(active_presence_counts.active_users, 0) as active_users'),
                DB::raw('COALESCE(urs.is_following, 0) as is_following')
            )
            ->selectRaw($this->publicRoomUnreadCountSql(), [$userId, $userId, $userId])
            ->orderBy('rooms.created_at', 'desc')
            ->with(['ownerCharacter', 'creator']);
    }

    private function publicRoomUnreadCountSql(): string
    {
        return '
            CASE
                WHEN COALESCE(urs.is_following, 0) = 1 THEN (
                    SELECT COUNT(*)
                    FROM messages m
                    WHERE m.room_id = rooms.id
                    AND m.deleted_at IS NULL
                    AND m.user_id <> ?
                    AND m.id > COALESCE(urs.last_read_message_id, 0)
                    AND NOT EXISTS (
                        SELECT 1
                        FROM character_blocks cb
                        INNER JOIN characters blocker_characters ON blocker_characters.id = cb.blocker_character_id
                        WHERE blocker_characters.user_id = ?
                        AND cb.blocked_character_id = m.character_id
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM character_blocks cb
                        INNER JOIN characters blocked_characters ON blocked_characters.id = cb.blocked_character_id
                        WHERE blocked_characters.user_id = ?
                        AND cb.blocker_character_id = m.character_id
                    )
                )
                ELSE 0
            END as unread_count
        ';
    }

    public function setCurrentCharacter(Request $request)
    {
        $characterId = $this->getCharacterIdFromRequest($request);

        session(['active_character_id' => $characterId]);

        return response()->json([
            'ok' => true,
            'character_id' => $characterId,
        ]);
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

    private function conversationHasParticipant(Room $conversation, int $characterId): bool
    {
        if ($characterId <= 0) {
            return false;
        }

        $ownsCharacter = DB::table('characters')
            ->where('id', $characterId)
            ->where('user_id', Auth::id())
            ->exists();

        if (! $ownsCharacter) {
            return false;
        }

        if ($conversation->type === Room::TYPE_DM) {
            return DB::table('dm_participants')
                ->where('room_id', $conversation->id)
                ->where('user_id', Auth::id())
                ->where('character_id', $characterId)
                ->exists();
        }

        if ($conversation->type === Room::TYPE_PUBLIC) {
            $character = $this->ownedCharacterById($characterId);

            return $character !== null
                && $this->roomAccess->canViewRoom(Auth::user(), $conversation, $character);
        }

        return false;
    }

    private function assertConversationParticipant(Room $conversation, int $characterId, array $context = []): void
    {
        $ok = $this->conversationHasParticipant($conversation, $characterId);

        if (! $ok && $conversation->type === 'dm') {
            $this->logSuspiciousAuthorizationFailure(
                $context['reason'] ?? 'dm_participant_membership_failed',
                [
                    'submitted_character_id' => $characterId,
                    'room_id' => $conversation->id,
                    'message_id' => $context['message_id'] ?? null,
                ]
            );
        }

        abort_unless($ok, 403);
    }

    private function assertValidPublicRoomParticipationState(Request $request, Room $room, Character $character): void
    {
        $token = trim((string) $request->input('room_participation_token', ''));

        abort_unless(
            app(RoomParticipationStateService::class)->hasValidToken($room, $character, $token),
            403
        );
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

    private function messageSeekOptions(Request $request): array
    {
        $request->validate([
            'before_id' => ['nullable', 'integer', 'min:0'],
            'before' => ['nullable', 'integer', 'min:0'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'after' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $beforeId = $request->query('before_id', $request->query('before'));
        $afterId = $request->query('after_id', $request->query('after'));

        $beforeId = $beforeId === null || $beforeId === '' ? null : (int) $beforeId;
        $afterId = $afterId === null || $afterId === '' ? null : (int) $afterId;

        $hasBefore = $beforeId !== null && $beforeId > 0;
        $hasAfter = $afterId !== null && $afterId > 0;

        if ($hasBefore && $hasAfter) {
            throw ValidationException::withMessages([
                'cursor' => ['Use either before_id or after_id, not both.'],
            ]);
        }

        $limit = (int) $request->query('limit', 50);

        return [
            'mode' => $hasBefore ? 'before' : ($hasAfter ? 'after' : 'initial'),
            'before_id' => $hasBefore ? $beforeId : null,
            'after_id' => $hasAfter ? $afterId : null,
            'limit' => max(1, min($limit, 100)),
        ];
    }

    private function conversationMessages(Room $conversation, Request $request, bool $withTrashed = true)
    {
        $seek = $this->messageSeekOptions($request);
        $since = $request->query('since');

        $query = $conversation->messages()
            ->with('character');

        if ($withTrashed) {
            $query->withTrashed();
        }

        if ($seek['mode'] === 'before') {
            return $query->where('id', '<', $seek['before_id'])
                ->orderByDesc('id')
                ->limit($seek['limit'])
                ->get()
                ->reverse()
                ->values();
        }

        if ($seek['mode'] === 'after' || $since) {
            $afterId = $seek['after_id'] ?? 0;

            if ($since) {
                $query->where(function ($outer) use ($afterId, $since) {
                    $outer->where('id', '>', $afterId)
                        ->orWhere(function ($sub) use ($since) {
                            $sub->where('updated_at', '>', $since)
                                ->orWhere('deleted_at', '>', $since);
                        });
                });
            } else {
                $query->where('id', '>', $afterId);
            }

            return $query->orderBy('id')
                ->limit($seek['limit'])
                ->get();
        }

        return $query->orderByDesc('id')
            ->limit($seek['limit'])
            ->get()
            ->reverse()
            ->values();
    }

    private function serializeRoomMessage(Message $message): array
    {
        $message->loadMissing('character');
        $this->hydrateRenderedMessage($message);

        return [
            'id' => (int) $message->id,
            'room_id' => (int) $message->room_id,
            'character_id' => $message->character_id !== null ? (int) $message->character_id : null,
            'type' => $message->type ?? Message::TYPE_NORMAL,
            'body' => $message->body,
            'content' => $message->body,
            'structured_data' => $message->structured_data,
            'rendered_body_html' => $message->rendered_body_html,
            'created_at' => $message->created_at?->toJSON(),
            'created_at_human' => $message->created_at?->diffForHumans(),
            'updated_at' => $message->updated_at?->toJSON(),
            'deleted_at' => $message->deleted_at?->toJSON(),
            'is_deleted' => (bool) $message->deleted_at,
            'is_blocked_by_viewer' => (bool) ($message->is_blocked_by_viewer ?? false),
            'can_edit' => $message->canBeEditedBy(Auth::user()),
            'character' => $message->character ? [
                'id' => (int) $message->character->id,
                'name' => $message->character->name,
                'avatar' => $message->character->avatar,
                'settings' => $message->character->settings,
                'public_handle' => $message->character->public_handle,
                'profile_url' => route('characters.profile.show', $message->character),
            ] : null,
        ];
    }

    private function serializeRoomMessages($messages)
    {
        return collect($messages)
            ->map(fn (Message $message) => $this->serializeRoomMessage($message))
            ->values();
    }

    private function hydrateRenderedMessages($messages): void
    {
        collect($messages)->each(fn (Message $message) => $this->hydrateRenderedMessage($message));
    }

    private function hydrateRenderedMessage(Message $message): Message
    {
        if ($message->deleted_at) {
            $renderedBodyHtml = $this->renderRichText('[deleted]');
        } elseif ($message->isDice()) {
            $renderedBodyHtml = app(DiceMessageFormatter::class)->renderHtml($message->structured_data);
        } else {
            $renderedBodyHtml = $this->renderRichText($message->body);
        }

        $message->setAttribute('rendered_body_html', $renderedBodyHtml);

        return $message;
    }

    private function renderRichText(?string $body): string
    {
        return app(MessageRichTextRenderer::class)->render($body);
    }

    private function createConversationMessage(Room $conversation, int $characterId, array $parsedMessage): Message
    {
        $messageType = $parsedMessage['type'] ?? Message::TYPE_NORMAL;
        $messageBody = $parsedMessage['body'];

        if ($messageType === Message::TYPE_DICE) {
            $characterName = (string) (Character::query()->whereKey($characterId)->value('name') ?? 'Unknown');
            $messageBody = app(DiceMessageFormatter::class)->renderStoredBody($characterName, $parsedMessage['structured_data'] ?? null);
        }

        $message = $conversation->messages()->create([
            'user_id' => Auth::id(),
            'character_id' => $characterId,
            'type' => $messageType,
            'body' => $messageBody,
            'structured_data' => $parsedMessage['structured_data'] ?? null,
        ]);

        if ($conversation->isPublicRoom()) {
            app(\App\Services\RoomRetentionService::class)->recordPublicRoomPost($conversation, $message->created_at);
        }

        if ($conversation->type === Room::TYPE_DM) {
            $conversation->touch();
            $this->restoreDmForOtherParticipants($conversation->id, Auth::id());
            $this->broadcastDmNotificationToRecipients($conversation, $message, $characterId);
        }

        broadcast(new MessageCreated($message))->toOthers();
        event(new ModerationMessageCreated($message));

        return $message;
    }

    private function broadcastDmNotificationToRecipients(Room $conversation, Message $message, int $senderCharacterId): void
    {
        DB::table('dm_participants')
            ->where('room_id', $conversation->id)
            ->where('user_id', '!=', Auth::id())
            ->get(['user_id', 'character_id'])
            ->each(function (object $participant) use ($conversation, $message, $senderCharacterId): void {
                $recipientUserId = (int) ($participant->user_id ?? 0);
                $recipientCharacterId = (int) ($participant->character_id ?? 0);

                if ($recipientUserId <= 0 || $recipientCharacterId <= 0) {
                    return;
                }

                broadcast(new DmNotificationCreated(
                    $recipientUserId,
                    (int) $conversation->id,
                    (string) $conversation->slug,
                    (int) $message->id,
                    $senderCharacterId,
                    $recipientCharacterId,
                ));
            });
    }

    private function restoreDmForUser(int $roomId, int $userId): void
    {
        if (! $this->dmParticipantArchiveColumnExists()) {
            return;
        }

        DB::table('dm_participants')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->update([
                'archived_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function restoreDmForOtherParticipants(int $roomId, int $excludedUserId): void
    {
        if (! $this->dmParticipantArchiveColumnExists()) {
            return;
        }

        DB::table('dm_participants')
            ->where('room_id', $roomId)
            ->where('user_id', '!=', $excludedUserId)
            ->whereNotNull('archived_at')
            ->update([
                'archived_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function dmParticipantArchiveColumnExists(): bool
    {
        return Schema::hasColumn('dm_participants', 'archived_at');
    }

    private function assertCanEditOrDelete(Message $message): void
    {
        $isOwner = $message->user_id === Auth::id();
        abort_unless($isOwner || $this->canModerate(), 403);
    }

    public function updateMessage(Request $request, Message $message)
    {
        $this->assertCanEditOrDelete($message);
        $message->loadMissing('room');

        if (! $this->canModerate()) {
            $this->assertConversationParticipant($message->room, (int) $message->character_id, [
                'message_id' => $message->id,
                'reason' => 'message_edit_membership_failed',
            ]);
        }

        abort_if($message->isDice(), 403);
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

        $freshMessage = $message->fresh()->load(['character', 'user']);
        $this->hydrateRenderedMessage($freshMessage);

        return response()->json([
            'ok' => true,
            'message' => $message->room->isPublicRoom()
                ? $this->serializeRoomMessage($freshMessage)
                : $freshMessage,
        ]);
    }

    public function deleteMessage(Request $request, Message $message)
    {
        $this->assertCanEditOrDelete($message);
        $message->loadMissing('room');

        if (! $this->canModerate()) {
            $this->assertConversationParticipant($message->room, (int) $message->character_id, [
                'message_id' => $message->id,
                'reason' => 'message_delete_membership_failed',
            ]);
        }

        abort_if($message->isDice(), 403);

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

        $characterId = $this->activeCharacterIdForConversation($message->room);
        abort_if(! $characterId, 403);
        $this->assertConversationParticipant($message->room, $characterId);

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

        if ($room->isPublicRoom()) {
            $selectedCharacterId = (int) $request->input('character_id', 0);

            if ($selectedCharacterId <= 0 && $request->wantsJson()) {
                return $this->missingCharacterResponse();
            }
        }

        $characterId = $this->messageCharacterIdForConversation($room, $request);
        $this->assertConversationParticipant($room, $characterId);

        if ($room->type === Room::TYPE_DM) {
            $this->assertDmMessageAllowed($room);
        } else {
            $character = $this->ownedCharacterById($characterId);
            abort_if($character === null, 403);
            abort_unless($this->roomAccess->canMessageRoom(Auth::user(), $room, $character), 403);
            $this->assertValidPublicRoomParticipationState($request, $room, $character);
        }

        $parsedMessage = app(ChatInputParser::class)->parse($request->body);

        if (($parsedMessage['command'] ?? null) === 'cls') {
            abort_if($room->type === Room::TYPE_DM, 422, 'The /cls command is only available in rooms.');

            $character = $this->ownedCharacterById($characterId);
            abort_if($character === null, 403);
            abort_unless($this->roomAccess->canModerateRoom(Auth::user(), $room, $character), 403);

            broadcast(new RoomDisplayCleared($room, $character))->toOthers();

            return response()->json([
                'ok' => true,
                'command' => 'cls',
                'room_id' => (int) $room->id,
            ]);
        }

        $message = $this->createConversationMessage($room, $characterId, $parsedMessage);

        if ($room->isPublicRoom()) {
            $this->markPublicRoomRead($room->id, $message->id);
        }

        if ($request->wantsJson()) {
            return response()->json($this->serializeRoomMessage($message->load('character')));
        }

        return back();
    }

    private function missingCharacterResponse()
    {
        return response()->json([
            'message' => 'You need to create and select a character before posting in chat.',
            'code' => 'missing_character',
        ], 422);
    }

    public function latest(Room $room, Request $request)
    {
        if ($room->type === Room::TYPE_DM) {
            $viewerCharacterId = $this->getLockedDmCharacterId($room);
        } else {
            $requestedCharacterId = (int) $request->query('character_id', 0);
            if ($requestedCharacterId > 0) {
                $this->assertCharacterOwnedByUser($requestedCharacterId);
                $viewerCharacterId = $requestedCharacterId;
            } else {
                $viewerCharacterId = $this->activeOwnedCharacterId();
            }
        }

        if ($viewerCharacterId) {
            $this->assertConversationParticipant($room, $viewerCharacterId);
        } elseif ($room->isPublicRoom()) {
            abort_unless($this->roomAccess->canViewRoom(Auth::user(), $room, null), 403);
        }

        $messages = $this->conversationMessages($room, $request);
        $this->applyBlockedMessageFlags($messages, $viewerCharacterId);

        return response()->json($this->serializeRoomMessages($messages));
    }

    public function ping(Room $room, Request $request)
    {
        $characterId = $this->getCharacterIdFromRequest($request);
        $this->assertConversationParticipant($room, $characterId);
        $character = $this->ownedCharacterById($characterId);

        if ($room->isPublicRoom()) {
            abort_unless($this->roomAccess->canJoinRoom(Auth::user(), $room, $character), 403);
            $this->assertValidPublicRoomParticipationState($request, $room, $character);
        }

        $now = now();

        $characterIdsToRefresh = DB::table('character_presences')
            ->join('characters', 'characters.id', '=', 'character_presences.character_id')
            ->where('character_presences.room_id', $room->id)
            ->where('characters.user_id', Auth::id())
            ->pluck('character_presences.character_id')
            ->map(fn ($id) => (int) $id)
            ->push($characterId)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        CharacterPresence::query()->upsert(
            $characterIdsToRefresh
                ->map(fn (int $id) => [
                    'room_id' => $room->id,
                    'character_id' => $id,
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ])
                ->all(),
            ['room_id', 'character_id'],
            ['last_seen_at', 'updated_at']
        );

        if ($room->isPublicRoom()) {
            $this->markPublicRoomRead($room->id);
        } else {
            app(\App\Services\MarkConversationRead::class)(
                $characterId,
                $room->id
            );
        }

        return response()->json([
            'ok' => true,
            'refreshed_character_ids' => $characterIdsToRefresh->all(),
        ]);
    }

    public function follow(Room $room, Request $request)
    {
        abort_if(! $room->isPublicRoom(), 404);

        [$activeCharacterId] = $this->activeCharacterSelectionForConversation($room);
        $activeCharacter = $this->ownedCharacterById($activeCharacterId);

        abort_unless($this->roomAccess->canViewRoom(Auth::user(), $room, $activeCharacter), 403);

        $validated = $request->validate([
            'follow' => ['required', 'boolean'],
        ]);

        $state = UserRoomState::firstOrNew([
            'user_id' => Auth::id(),
            'room_id' => $room->id,
        ]);
        $state->is_following = (bool) $validated['follow'];
        $state->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'is_following' => $state->is_following,
            ]);
        }

        return redirect()
            ->route('rooms.show', ['room' => $room->slug, 'tool' => 'follow'])
            ->with('status', $state->is_following ? 'Room followed.' : 'Room unfollowed.');
    }

    public function leave(Room $room, Request $request)
    {
        $characterId = $this->getCharacterIdFromRequest($request);
        $this->assertConversationParticipant($room, $characterId);
        $character = $this->ownedCharacterById($characterId);

        if ($room->isPublicRoom()) {
            abort_unless($this->roomAccess->canJoinRoom(Auth::user(), $room, $character), 403);
            $this->assertValidPublicRoomParticipationState($request, $room, $character);
        }

        DB::table('character_presences')
            ->where('room_id', $room->id)
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

        $character = $this->ownedCharacterById($characterId);

        $rooms = $this->sidebarRoomsForPublicRooms($character)->get();

        return response()->json(['rooms' => $rooms]);
    }

    public function roster(Room $room)
    {
        $activeCharacter = $this->activeOwnedCharacter();

        if ($room->isPublicRoom()) {
            abort_unless($this->roomAccess->canViewRoom(Auth::user(), $room, $activeCharacter), 403);
        }

        $cutoff = Room::activePresenceCutoff();

        $roster = DB::table('character_presences')
            ->join('characters', 'characters.id', '=', 'character_presences.character_id')
            ->where('character_presences.room_id', $room->id)
            ->where('character_presences.last_seen_at', '>=', $cutoff)
            ->orderBy('characters.name')
            ->select([
                'characters.id as character_id',
                'characters.name as character_name',
                'characters.avatar as avatar',
                'characters.settings as settings',
            ])
            ->get()
            ->map(function ($entry) {
                return [
                    'character_id' => (int) $entry->character_id,
                    'character_name' => $entry->character_name,
                    'avatar' => $entry->avatar,
                    'settings' => $entry->settings,
                    'character_handle' => Character::formatPublicHandle(
                        (string) $entry->character_name,
                        (int) $entry->character_id
                    ),
                ];
            })
            ->values();

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
                    ->whereColumn('ccr.character_id', '=', 'mine.character_id');
            })
            ->where('mine.user_id', $me)
            ->where('rooms.type', Room::TYPE_DM)
            ->whereNull('rooms.deleted_at')
            ->orderByDesc('rooms.updated_at')
            ->select([
                'rooms.id as room_id',
                'rooms.slug',
                'rooms.updated_at',
                'other_char.id as other_character_id',
                'other_char.name as other_character_name',
                'other_char.avatar as other_character_avatar',
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
            ');

        if ($this->dmParticipantArchiveColumnExists()) {
            $rooms->addSelect('mine.archived_at');
        } else {
            $rooms->selectRaw('NULL as archived_at');
        }

        $rooms = $rooms->get()->map(function ($room) {
            $room->other_character_profile_url = route('characters.profile.show', ['character' => (int) $room->other_character_id]);

            return $room;
        });

        return response()->json(['rooms' => $rooms]);
    }

    public function dmTargets(Request $request)
    {
        $request->validate([
            'from_character_id' => ['required', 'integer'],
            'query' => ['nullable', 'string', 'max:100'],
        ]);

        $fromCharacterId = (int) $request->query('from_character_id', 0);
        $fromCharacter = $this->ownedCharacterById($fromCharacterId);

        if (! $fromCharacter) {
            $this->logSuspiciousAuthorizationFailure('dm_target_search_character_not_owned', [
                'submitted_character_id' => $fromCharacterId,
            ]);
        }

        abort_unless($fromCharacter, 403);

        $term = trim((string) $request->query('query', ''));
        if ($term === '') {
            return response()->json(['targets' => []]);
        }

        $nameTerm = trim(str_contains($term, '#') ? (strstr($term, '#', true) ?: $term) : $term);
        if ($nameTerm === '') {
            $nameTerm = $term;
        }

        $targets = Character::query()
            ->where('characters.user_id', '!=', Auth::id())
            ->where(function ($query) use ($nameTerm) {
                $query->where('characters.name', 'like', '%' . $nameTerm . '%');
            })
            ->whereNotExists(function ($query) use ($fromCharacterId) {
                $query->select(DB::raw(1))
                    ->from('character_blocks')
                    ->where('blocker_character_id', $fromCharacterId)
                    ->whereColumn('blocked_character_id', 'characters.id');
            })
            ->whereNotExists(function ($query) use ($fromCharacterId) {
                $query->select(DB::raw(1))
                    ->from('character_blocks')
                    ->where('blocked_character_id', $fromCharacterId)
                    ->whereColumn('blocker_character_id', 'characters.id');
            })
            ->orderBy('characters.name')
            ->limit(20)
            ->get([
                'characters.id',
                'characters.name',
                'characters.avatar',
            ])
            ->map(function ($target) {
                return [
                    'id' => (int) $target->id,
                    'name' => $target->name,
                    'avatar' => $target->avatar,
                    'handle' => Character::formatPublicHandle((string) $target->name, (int) $target->id),
                ];
            })
            ->filter(function (array $target) use ($term) {
                $needle = Str::lower($term);

                return str_contains(Str::lower($target['name']), $needle)
                    || str_contains(Str::lower($target['handle']), $needle);
            })
            ->values();

        return response()->json(['targets' => $targets]);
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

        if (! $owns) {
            $this->logSuspiciousAuthorizationFailure('dm_start_character_not_owned', [
                'submitted_character_id' => $myCharacterId,
            ]);
        }

        abort_unless($owns, 403);

        $otherChar = DB::table('characters')->where('id', $otherCharacterId)->first();
        abort_unless($otherChar, 404);
        abort_if((int) $otherChar->user_id === (int) $me, 422);
        $this->abortIfDmBlocked($myCharacterId, $otherCharacterId);

        $dmKey = Room::normalizedDmKey($myCharacterId, $otherCharacterId);

        if ($room = $this->findDmRoomForCharacterPair($myCharacterId, $otherCharacterId)) {
            $this->restoreDmForUser($room->id, $me);

            return response()->json(['slug' => $room->slug]);
        }

        try {
            $room = DB::transaction(function () use ($me, $myCharacterId, $otherCharacterId, $otherChar, $dmKey) {
                if ($existing = $this->findDmRoomForCharacterPair($myCharacterId, $otherCharacterId)) {
                    $this->restoreDmForUser($existing->id, $me);

                    return $existing;
                }

                $room = Room::create([
                    'name'       => 'DM',
                    'slug'       => 'dm-' . Str::random(20),
                    'user_id'    => $me,
                    'created_by' => $me,
                    'type'       => Room::TYPE_DM,
                    'visibility' => Room::VISIBILITY_PUBLIC,
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
            $room = Room::where('type', Room::TYPE_DM)->where('dm_key', $dmKey)->first();
            throw_unless($room, $exception);

            $this->restoreDmForUser($room->id, $me);
        }

        return response()->json(['slug' => $room->slug]);
    }

    private function findDmRoomForCharacterPair(int $firstCharacterId, int $secondCharacterId): ?Room
    {
        [$lowCharacterId, $highCharacterId] = Room::normalizedDmPair($firstCharacterId, $secondCharacterId);

        $roomId = DB::table('rooms')
            ->join('dm_participants', 'dm_participants.room_id', '=', 'rooms.id')
            ->where('rooms.type', Room::TYPE_DM)
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

    public function dmArchive(Room $room)
    {
        abort_unless($room->type === Room::TYPE_DM, 404);

        $this->getLockedDmCharacterId($room);
        $archivedAt = now();

        if (! $this->dmParticipantArchiveColumnExists()) {
            return response()->json([
                'message' => 'DM archive support is unavailable until the archive migration is applied.',
            ], 409);
        }

        DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', Auth::id())
            ->update([
                'archived_at' => $archivedAt,
                'updated_at' => $archivedAt,
            ]);

        return response()->json([
            'ok' => true,
            'archived_at' => $archivedAt->toJSON(),
        ]);
    }

    public function dmRestore(Room $room)
    {
        abort_unless($room->type === Room::TYPE_DM, 404);

        $this->getLockedDmCharacterId($room);
        $this->restoreDmForUser($room->id, Auth::id());

        return response()->json(['ok' => true]);
    }

    public function dmMessages(Room $room, Request $request)
    {
        $characterId = $this->getLockedDmCharacterId($room);
        $this->assertConversationParticipant($room, $characterId);

        if ($room->isPublicRoom()) {
            $this->markPublicRoomRead($room->id);
        } else {
            app(\App\Services\MarkConversationRead::class)(
                $characterId,
                $room->id
            );
        }

        $messages = $this->conversationMessages($room, $request, false);
        $this->applyBlockedMessageFlags($messages, $characterId);

        $otherCharacterId = (int) DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', '!=', Auth::id())
            ->value('character_id');

        return response()->json([
            'room' => [
                'id' => $room->id,
                'slug' => $room->slug,
                'name' => $room->name,
                'other_character_profile_url' => $otherCharacterId > 0
                    ? route('characters.profile.show', ['character' => $otherCharacterId])
                    : null,
            ],
            'messages' => $this->serializeRoomMessages($messages),
        ]);
    }

    public function dmSend(Room $room, Request $request)
    {
        abort_unless($room->type === Room::TYPE_DM, 404);

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $characterId = $this->getLockedDmCharacterId($room);
        $this->assertConversationParticipant($room, $characterId);
        $this->assertDmMessageAllowed($room);
        $this->restoreDmForUser($room->id, Auth::id());

        $parsedMessage = app(ChatInputParser::class)->parse($request->body);

        if (($parsedMessage['command'] ?? null) === 'cls') {
            throw ValidationException::withMessages([
                'body' => ['The /cls command is only available in rooms.'],
            ]);
        }

        $message = $this->createConversationMessage($room, $characterId, $parsedMessage)->load(['user', 'character']);

        return response()->json([
            'ok' => true,
            'message' => $this->serializeRoomMessage($message),
        ]);
    }

    private function getLockedDmCharacterId(Room $room): int
    {
        $cid = (int) DB::table('dm_participants')
            ->where('room_id', $room->id)
            ->where('user_id', Auth::id())
            ->value('character_id');

        if ($cid <= 0) {
            $this->logSuspiciousAuthorizationFailure('locked_dm_character_missing', [
                'room_id' => $room->id,
            ]);
        }

        abort_if($cid <= 0, 403);
        return $cid;
    }
}
