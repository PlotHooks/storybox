{{-- resources/views/rooms/show.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $room->name }}
        </h2>
    </x-slot>

    <div class="box-border h-[calc(100dvh-7.625rem)] min-h-0 overflow-hidden py-4 bg-gray-950/60">
        <div class="max-w-none w-full mx-auto h-full min-h-0 overflow-hidden flex flex-col lg:flex-row gap-3 px-2 md:px-4">

            {{-- LEFT COLUMN --}}
            <div id="left-panel" class="w-full lg:w-72 min-h-0 bg-gray-950 text-gray-100 rounded-lg shadow-2xl flex flex-col border border-gray-800/90 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-800 bg-gray-900/80">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-emerald-400">Context Dock</div>
                            <div class="mt-1 text-sm font-semibold text-gray-100">Room tools</div>
                        </div>
                        <span class="rounded border border-emerald-500/40 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-300">px</span>
                    </div>
                </div>
                <div class="border-b border-gray-800 bg-gray-950/80 p-2">
                    <div class="grid grid-cols-2 gap-1 text-[11px] font-medium text-gray-300">
                        <button type="button" data-context-tool="world" class="context-tool-btn rounded border border-emerald-500/40 bg-emerald-500/10 px-2 py-1.5 text-left text-emerald-200 shadow-[inset_0_0_0_1px_rgba(16,185,129,0.08)]">World Book</button>
                        <button type="button" data-context-tool="notes" class="context-tool-btn rounded border border-gray-800 bg-gray-900/80 px-2 py-1.5 text-left text-gray-400 hover:border-gray-700 hover:text-gray-200">Pinned Notes</button>
                        <button type="button" data-context-tool="rules" class="context-tool-btn rounded border border-gray-800 bg-gray-900/80 px-2 py-1.5 text-left text-gray-400 hover:border-gray-700 hover:text-gray-200">Room Rules</button>
                        <button type="button" data-context-tool="character" class="context-tool-btn rounded border border-gray-800 bg-gray-900/80 px-2 py-1.5 text-left text-gray-400 hover:border-gray-700 hover:text-gray-200">Character Info</button>
                    </div>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto px-4 py-4 text-xs text-gray-300">
                    <div data-context-panel="world" class="context-tool-panel rounded-md border border-gray-800 bg-gray-900/70 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-100">World Book</h3>
                            <span class="text-[10px] uppercase tracking-[0.18em] text-gray-500">Draft</span>
                        </div>
                        <p class="mt-2 leading-relaxed text-gray-400">
                            Shared lore, locations, NPC notes, and timeline anchors will live here.
                        </p>
                        <div class="mt-3 space-y-2">
                            <div class="rounded border border-gray-800 bg-gray-950/70 p-2">
                                <div class="text-[11px] font-semibold text-emerald-300">Sample entry</div>
                                <p class="mt-1 leading-relaxed text-gray-400">A short room-specific note can be pinned for quick reference during play.</p>
                            </div>
                        </div>
                    </div>
                    <div data-context-panel="notes" class="context-tool-panel hidden rounded-md border border-dashed border-gray-800 bg-gray-900/40 p-3 text-gray-500">
                        Pinned Notes are coming soon.
                    </div>
                    <div data-context-panel="rules" class="context-tool-panel hidden rounded-md border border-dashed border-gray-800 bg-gray-900/40 p-3 text-gray-500">
                        Room Rules are coming soon.
                    </div>
                    <div data-context-panel="character" class="context-tool-panel hidden rounded-md border border-dashed border-gray-800 bg-gray-900/40 p-3 text-gray-500">
                        Character Info is coming soon.
                    </div>
                </div>
            </div>

            {{-- CENTER --}}
            <div class="flex-1 min-h-0 bg-gray-950 rounded-lg shadow-2xl flex flex-col border border-gray-800/90 overflow-hidden ring-1 ring-emerald-500/10">

                {{-- Top bar --}}
                <div class="shrink-0 flex flex-col gap-3 border-b border-gray-800 bg-gray-900/90 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-sm bg-emerald-400 shadow-[0_0_12px_rgba(52,211,153,0.75)]"></span>
                            <h1 class="truncate text-lg font-semibold text-gray-50 md:text-xl">{{ $room->name }}</h1>
                        </div>
                        @if (! empty($room->description))
                            <p class="mt-1 max-w-3xl truncate text-sm text-gray-400">{{ $room->description }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-gray-500">
                            <span class="rounded border border-gray-800 bg-gray-950/70 px-2 py-1">
                                Owner <span class="font-medium text-gray-300">{{ optional($room->owner)->name ?? 'Unknown' }}</span>
                            </span>
                            <span class="rounded border border-gray-800 bg-gray-950/70 px-2 py-1">
                                Messages <span class="font-medium text-gray-300">{{ $messages->count() }}</span>
                            </span>
                            <span id="room-active-count" class="rounded border border-gray-800 bg-gray-950/70 px-2 py-1">
                                Active <span class="font-medium text-gray-500">syncing</span>
                            </span>
                        </div>
                    </div>

                    @php
                        $characters = Auth::user()->characters;
                        $isAdminBlade = (bool) (Auth::user()->is_admin ?? false);
                        $viewerCharacterId = $characters->contains('id', (int) $activeCharacterId)
                            ? (int) $activeCharacterId
                            : null;
                    @endphp

                    @if ($characters->count() > 0)
                        <div class="flex flex-wrap items-center justify-end gap-2">

                            <button id="toggle-left" type="button"
                                class="rounded border border-gray-700 bg-gray-950/80 text-xs text-gray-300 px-2 py-1 hover:border-gray-600 hover:bg-gray-800 hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                                Toggle Left
                            </button>

                            <button id="toggle-right" type="button"
                                class="rounded border border-gray-700 bg-gray-950/80 text-xs text-gray-300 px-2 py-1 hover:border-gray-600 hover:bg-gray-800 hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                                Toggle Right
                            </button>

                            <span class="text-xs text-gray-400">Posting as</span>

                            <select id="character-switcher"
                                class="rounded border-gray-700 bg-gray-950 text-xs text-gray-100 px-2 py-1 focus:border-emerald-500 focus:ring-emerald-500">
                                @foreach ($characters as $char)
                                    <option value="{{ $char->id }}" {{ $char->id == $activeCharacterId ? 'selected' : '' }}>
                                        {{ $char->name }}
                                    </option>
                                @endforeach
                            </select>

                            <button id="leave-room-btn" type="button"
                                class="rounded border border-red-500/40 bg-red-500/10 text-xs font-semibold text-red-200 px-2 py-1 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500/50">
                                Leave room
                            </button>

                        </div>
                    @else
                        <div class="text-xs text-red-400">
                            You need at least one character to post.
                        </div>
                    @endif
                </div>

                {{-- Messages --}}
                <div id="message-container" class="flex-1 min-h-0 overflow-y-auto bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.08),transparent_32rem)] px-3 py-3 md:px-4">
                    @php
                        $messageList = $messages instanceof \Illuminate\Support\Collection
                            ? $messages->values()
                            : collect($messages)->values();
                    @endphp

                    @foreach ($messageList as $message)
                        @php
                            $prev = $loop->index > 0 ? $messageList->get($loop->index - 1) : null;
                            $messageCharacterId = (int) ($message->character_id ?? 0);
                            $prevCharacterId = (int) ($prev?->character_id ?? 0);
                            $isGrouped = $messageCharacterId > 0 && $prevCharacterId === $messageCharacterId;

                            $c = $message->character;
                            $name = optional($c)->name ?? optional($message->user)->name ?? 'Unknown';

                            $s = $c->settings ?? [];
                            if (is_string($s)) { $s = json_decode($s, true) ?: []; }

                            $c1 = $s['text_color_1'] ?? '#D8F3FF';
                            $c2 = $s['text_color_2'] ?? null;
                            $c3 = $s['text_color_3'] ?? null;
                            $c4 = $s['text_color_4'] ?? null;

                            $fadeMsg  = (bool) ($s['fade_message'] ?? false);
                            $fadeName = (bool) ($s['fade_name'] ?? false);

                            $nameStyleJson = json_encode([
                                'c1' => $c1, 'c2' => $c2, 'c3' => $c3, 'c4' => $c4, 'fade' => $fadeName,
                            ], JSON_UNESCAPED_SLASHES);

                            $bodyStyleJson = json_encode([
                                'c1' => $c1, 'c2' => $c2, 'c3' => $c3, 'c4' => $c4, 'fade' => $fadeMsg,
                            ], JSON_UNESCAPED_SLASHES);

                            $isOwner = (int)$message->user_id === (int)Auth::id();
                            $canEdit = $isOwner || $isAdminBlade;

                            $isDeleted = false;
                            if (method_exists($message, 'trashed')) {
                                $isDeleted = $message->trashed();
                            } elseif (!empty($message->deleted_at)) {
                                $isDeleted = true;
                            }

                            $text = $message->content ?? $message->body ?? '';
                            $displayText = trim((string) $text);
                            $isBlockedByViewer = (bool) ($message->is_blocked_by_viewer ?? false);
                            $blockLabel = $isBlockedByViewer ? 'Blocked' : 'Block';
                            $blockClass = $isBlockedByViewer
                                ? 'text-gray-400 hover:text-gray-300'
                                : 'text-red-400 hover:text-red-300';
                            $avatar = $c?->externalAvatarUrl();
                            $initial = strtoupper(substr($name, 0, 1));
                        @endphp

                        <div class="group relative flex flex-none gap-2 px-2 {{ $isGrouped ? 'border-0 rounded-none py-0' : 'border-t border-gray-900/40 py-0.5' }} msg-row {{ $isBlockedByViewer && ! $isAdminBlade ? 'opacity-70' : '' }}"
                             data-message-id="{{ $message->id }}"
                             data-user-id="{{ $message->user_id }}"
                             data-character-id="{{ $messageCharacterId ?: '' }}"
                             data-can-edit="{{ $canEdit ? '1' : '0' }}"
                             data-deleted="{{ $isDeleted ? '1' : '0' }}"
                             data-blocked-by-viewer="{{ $isBlockedByViewer && ! $isAdminBlade ? '1' : '0' }}">

                            <div class="w-7 shrink-0">
                                @unless ($isGrouped)
                                    @if ($avatar)
                                        <img src="{{ $avatar }}"
                                             alt="{{ $name }} avatar"
                                             loading="lazy"
                                             referrerpolicy="no-referrer"
                                             class="h-7 w-7 rounded-full object-cover">
                                    @else
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-800 bg-gray-950 text-xs font-semibold text-gray-500">
                                            {{ $initial }}
                                        </div>
                                    @endif
                                @endunless
                            </div>

                            <div class="min-w-0 flex-1 pr-28">
                                @unless ($isGrouped)
                                    <div class="mb-0 flex items-baseline gap-2">
                                        <button type="button"
                                            class="char-trigger msg-name text-base font-bold leading-none text-left cursor-pointer hover:underline focus:outline-none focus:ring-2 focus:ring-emerald-500/50 rounded-sm"
                                            data-style='{!! $nameStyleJson !!}'
                                            data-character-id="{{ $c?->id ?? '' }}"
                                            data-user-id="{{ $message->user_id ?? '' }}"
                                            data-character-name="{{ e($name) }}"
                                            data-character-avatar="{{ e($avatar ?? '') }}">
                                            {{ $name }}
                                        </button>

                                        <span class="text-[10px] text-gray-500 ml-2">{{ $message->created_at->diffForHumans() }}</span>
                                        <span class="msg-edited text-[10px] text-gray-500 ml-2 hidden">(edited)</span>
                                        <span class="msg-deleted text-[10px] text-gray-500 ml-2 {{ $isDeleted ? '' : 'hidden' }}">(deleted)</span>
                                    </div>
                                @endunless

                                @if ($isBlockedByViewer && ! $isAdminBlade)
                                    <div class="msg-blocked-notice text-xs text-gray-500 mt-1">
                                        Message hidden from a blocked character.
                                    </div>
                                @endif

                                <div class="msg-body-wrapper mt-0 text-sm leading-snug {{ $isBlockedByViewer && ! $isAdminBlade ? 'hidden msg-blocked-body' : '' }}"><span class="msg-body text-sm text-gray-400 leading-snug whitespace-pre-line" data-style='{!! $bodyStyleJson !!}'>{{ $isDeleted ? '[deleted]' : $displayText }}</span></div>

                                @if ($canEdit)
                                    <div class="msg-editbox hidden mt-2">
                                        <textarea class="msg-edit-textarea w-full rounded border border-gray-700 bg-gray-950 text-base text-gray-100 leading-relaxed p-2 focus:border-emerald-500 focus:ring-emerald-500"
                                                  rows="3"></textarea>
                                        <div class="mt-2 flex gap-2 justify-end">
                                            <button type="button"
                                                class="msg-cancel-btn rounded border border-gray-700 bg-gray-900 px-2 py-1 text-gray-200 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                                                Cancel
                                            </button>
                                            <button type="button"
                                                class="msg-save-btn rounded border border-emerald-500/50 bg-emerald-500/10 px-2 py-1 text-emerald-100 hover:bg-emerald-500/20 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                                                Save
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="msg-actions absolute right-2 top-1 flex items-center gap-1 text-[10px] opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                <button type="button"
                                    class="msg-report-btn rounded border border-gray-700/80 bg-gray-950/80 px-2 py-1 text-gray-300 hover:border-gray-600 hover:bg-gray-800 hover:text-gray-100 disabled:opacity-40"
                                    {{ $isDeleted ? 'disabled' : '' }}>
                                    Report
                                </button>

                                @if (! $isAdminBlade && $viewerCharacterId && $message->character_id && (int) $message->character_id !== $viewerCharacterId)
                                    <button type="button"
                                        class="text-xs {{ $blockClass }} ml-1"
                                        onclick="setCharacterBlock({{ $viewerCharacterId }}, {{ (int) $message->character_id }}, {{ $isBlockedByViewer ? 'false' : 'true' }})">
                                        {{ $blockLabel }}
                                    </button>
                                @endif

                                @if ($canEdit)
                                    <button type="button"
                                        class="msg-edit-btn rounded border border-gray-700/80 bg-gray-950/80 px-2 py-1 text-gray-300 hover:border-gray-600 hover:bg-gray-800 hover:text-gray-100 disabled:opacity-40"
                                        {{ $isDeleted ? 'disabled' : '' }}>
                                        Edit
                                    </button>
                                    <button type="button"
                                        class="msg-del-btn rounded border border-gray-700/80 bg-gray-950/80 px-2 py-1 text-gray-300 hover:border-red-500/50 hover:bg-red-500/10 hover:text-red-200 disabled:opacity-40"
                                        {{ $isDeleted ? 'disabled' : '' }}>
                                        Delete
                                    </button>
                                @endif
                            </div>

                        </div>
                    @endforeach
                </div>

                {{-- Send --}}
                <div class="shrink-0 border-t border-gray-800 bg-gray-900/95 p-3">
                    <form method="POST" action="{{ route('rooms.messages.store', $room) }}" id="message-form">
                        @csrf

                        <input type="hidden" name="character_id" id="character-id-input" value="{{ $activeCharacterId }}">
                        <input type="hidden" name="content" id="content-mirror" value="">

                        <textarea
                            id="body"
                            name="body"
                            rows="3"
                            required
                            placeholder="Enter to send. Shift+Enter for newline."
                            class="mt-1 block w-full resize-none rounded-md border-gray-700 bg-gray-950 text-gray-100 placeholder:text-gray-600 shadow-inner focus:border-emerald-500 focus:ring-emerald-500"
                        >{{ old('body') }}</textarea>

                        <div class="mt-2 flex items-center justify-between gap-3">
                            <div class="text-[10px] uppercase tracking-[0.18em] text-gray-600">Transmission ready</div>
                            <x-primary-button class="bg-emerald-600 hover:bg-emerald-500 focus:bg-emerald-600 active:bg-emerald-700 focus:ring-emerald-500">
                                Send
                            </x-primary-button>
                        </div>
                    </form>
                </div>

            </div>

            {{-- RIGHT --}}
            <div id="right-panel" class="w-full lg:w-80 min-h-0 bg-gray-950 text-gray-100 rounded-lg shadow-2xl flex flex-col border border-gray-800/90 overflow-hidden">

                <div class="border-b border-gray-800 bg-gray-900/80 px-3 py-3">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-emerald-400">Room Net</div>
                        <span id="tab-meta" class="text-[10px] text-gray-500"># active / name</span>
                    </div>
                    <div class="grid grid-cols-2 gap-1 text-xs font-semibold">
                        <button id="tab-rooms" type="button" class="rounded border border-emerald-500/40 bg-emerald-500/10 px-2 py-1.5 text-emerald-200">Rooms</button>
                        <button id="tab-users" type="button" class="rounded border border-gray-800 px-2 py-1.5 text-gray-400 hover:border-gray-700 hover:bg-gray-800 hover:text-gray-100">Users</button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto text-xs">

                    <div id="panel-rooms" class="p-2">
                        @foreach ($sidebarRooms as $r)
                            @php
                                $unreadCount = (int) ($r->unread_count ?? 0);
                                $unreadLabel = $unreadCount > 99 ? '99+' : (string) $unreadCount;
                                $isCurrentRoom = (int) $r->id === (int) $room->id;
                            @endphp
                            <button type="button"
                                data-room-id="{{ $r->id }}"
                                onclick="window.location.href='{{ route('rooms.show', $r->slug) }}'"
                                class="w-full rounded border px-3 py-2 text-left flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 {{ $isCurrentRoom ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100' : 'border-gray-800/80 bg-gray-900/50 text-gray-200 hover:border-gray-700 hover:bg-gray-900' }}">
                                <span class="min-w-0 flex-1 truncate font-medium">{{ $r->name }}</span>
                                <span
                                    data-room-unread-badge="{{ $r->id }}"
                                    data-unread-count="{{ $unreadCount }}"
                                    class="{{ $unreadCount > 0 ? '' : 'hidden' }} shrink-0 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                                    {{ $unreadLabel }}
                                </span>
                            </button>
                        @endforeach
                    </div>

                    <div id="panel-users" class="hidden px-3 py-3">
                        <div id="user-list" class="space-y-2 text-gray-200">
                            <div class="text-gray-500">Loading...</div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    {{-- Popover (must be before script so querySelector finds it) --}}
    <div id="char-popover"
         class="hidden fixed z-[9999] w-64 rounded-lg border border-gray-700 bg-gray-900 shadow-xl">
        <div class="p-3">
            <div class="flex items-start gap-3">
                <div id="char-popover-avatar" class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-800 bg-gray-950 text-2xl font-semibold text-gray-500"></div>
                <div class="min-w-0">
                    <div id="char-popover-title" class="font-semibold text-gray-100 text-sm"></div>
                    <div id="char-popover-sub" class="text-[10px] text-gray-400 mt-1">ID verification</div>
                </div>
            </div>

            <div class="mt-3 flex gap-2 justify-end">
                <a id="char-popover-profile"
                   href="#"
                   class="rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-100 hover:bg-gray-700">
                    Profile
                </a>

                <button id="char-popover-dm"
                        type="button"
                        class="rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-100 hover:bg-gray-700">
                    DM
                </button>
            </div>
        </div>
    </div>

    <div id="message-report-modal"
         class="hidden fixed inset-0 z-[10000] bg-black/70 flex items-center justify-center px-4">
        <form id="message-report-form"
              class="w-full max-w-md rounded-lg border border-gray-700 bg-gray-900 p-4 shadow-xl">
            <h3 class="text-sm font-semibold text-gray-100">Report message</h3>
            <textarea id="message-report-reason"
                      class="mt-3 w-full rounded border border-gray-700 bg-gray-950 p-2 text-sm text-gray-100"
                      rows="4"
                      maxlength="1000"
                      required
                      placeholder="What should moderators review?"></textarea>
            <div class="mt-3 flex justify-end gap-2">
                <button type="button"
                        id="message-report-cancel"
                        class="rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-100 hover:bg-gray-700">
                    Cancel
                </button>
                <button type="submit"
                        id="message-report-submit"
                        class="rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-100 hover:bg-gray-700">
                    Submit
                </button>
            </div>
        </form>
    </div>

    <style>
        .char-row { position: relative; }
    </style>

    <script>
        let lastMessageId = {{ $messages->last()?->id ?? 0 }};
        const conversationId = {{ (int) $room->id }};
        const conversationChannelName = `private-conversation.${conversationId}`;
        const roomSlug = @json($room->slug);
        const csrf = @json(csrf_token());
        const currentUserId = {{ (int) Auth::id() }};
        const isAdmin = {{ (int) ((Auth::user()->is_admin ?? false) ? 1 : 0) }};
        const ownedCharacterIds = @json($characters->pluck('id')->map(fn ($id) => (int) $id)->values());
        const seenMessageIds = new Set();

        const container  = document.getElementById('message-container');
        const form       = document.getElementById('message-form');
        const textarea   = document.getElementById('body');
        const contentMirror = document.getElementById('content-mirror');

        const switcher   = document.getElementById('character-switcher');
        const hiddenChar = document.getElementById('character-id-input');

        const leftPanel  = document.getElementById('left-panel');
        const rightPanel = document.getElementById('right-panel');

        const tabRooms   = document.getElementById('tab-rooms');
        const tabUsers   = document.getElementById('tab-users');

        const panelRooms = document.getElementById('panel-rooms');
        const panelUsers = document.getElementById('panel-users');

        const tabMeta    = document.getElementById('tab-meta');
        const userListEl = document.getElementById('user-list');
        const activeCountEl = document.getElementById('room-active-count');

        const reportModal = document.getElementById('message-report-modal');
        const reportForm = document.getElementById('message-report-form');
        const reportReason = document.getElementById('message-report-reason');
        const reportSubmit = document.getElementById('message-report-submit');
        const reportCancel = document.getElementById('message-report-cancel');
        let reportMessageId = null;

        function parseUnreadCount(value) {
            const n = parseInt(value || '0', 10);
            return Number.isFinite(n) && n > 0 ? n : 0;
        }

        function parseBool(value) {
            return value === true || value === 1 || value === '1';
        }

        function formatUnreadCount(count) {
            return count > 99 ? '99+' : String(count);
        }

        function setUnreadBadge(badge, count) {
            if (!badge) return;
            const normalized = parseUnreadCount(count);
            badge.dataset.unreadCount = String(normalized);
            badge.textContent = formatUnreadCount(normalized);
            badge.classList.toggle('hidden', normalized <= 0);
        }

        function clearRoomUnreadBadge(roomId) {
            const badge = document.querySelector(`[data-room-unread-badge="${roomId}"]`);
            setUnreadBadge(badge, 0);
        }

        document.querySelectorAll('[data-message-id]').forEach((row) => {
            const id = parseInt(row.dataset.messageId, 10);
            if (id) seenMessageIds.add(id);
        });

        if (!window.__roomMessageActionsBound) {
            window.__roomMessageActionsBound = true;
            document.addEventListener('click', handleMessageActionClick, true);
        }

        document.getElementById('toggle-left')?.addEventListener('click', () => leftPanel?.classList.toggle('hidden'));
        document.getElementById('toggle-right')?.addEventListener('click', () => rightPanel?.classList.toggle('hidden'));

        const contextToolButtons = Array.from(document.querySelectorAll('[data-context-tool]'));
        const contextToolPanels = Array.from(document.querySelectorAll('[data-context-panel]'));
        function showContextTool(tool) {
            contextToolButtons.forEach((button) => {
                const isActiveTool = button.dataset.contextTool === tool;
                button.className = isActiveTool
                    ? 'context-tool-btn rounded border border-emerald-500/40 bg-emerald-500/10 px-2 py-1.5 text-left text-emerald-200 shadow-[inset_0_0_0_1px_rgba(16,185,129,0.08)]'
                    : 'context-tool-btn rounded border border-gray-800 bg-gray-900/80 px-2 py-1.5 text-left text-gray-400 hover:border-gray-700 hover:text-gray-200';
            });
            contextToolPanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.contextPanel !== tool);
            });
        }
        contextToolButtons.forEach((button) => {
            button.addEventListener('click', () => showContextTool(button.dataset.contextTool));
        });

        function escAttr(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function escHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function isSafeAvatarUrl(url) {
            if (!url) return false;

            try {
                const parsed = new URL(url, window.location.origin);
                return parsed.protocol === 'http:' || parsed.protocol === 'https:';
            } catch (e) {
                return false;
            }
        }

        function avatarInitial(name) {
            return (String(name || '?').trim().charAt(0) || '?').toUpperCase();
        }

        function avatarHtml(url, name, sizeClass = 'h-7 w-7', shapeClass = 'rounded-full') {
            if (isSafeAvatarUrl(url)) {
                return `<img src="${escAttr(url)}" alt="${escAttr(name)} avatar" loading="lazy" referrerpolicy="no-referrer" class="${sizeClass} shrink-0 ${shapeClass} object-cover">`;
            }

            return `<div class="flex ${sizeClass} shrink-0 items-center justify-center ${shapeClass} border border-gray-800 bg-gray-950 text-xs font-semibold text-gray-500">${escHtml(avatarInitial(name))}</div>`;
        }

        function setCharacterBlock(blockerId, blockedId, shouldBlock) {
            const action = shouldBlock ? "Block this character?" : "Unblock this character?";
            if (!confirm(action)) return;

            const token = document.querySelector('meta[name="csrf-token"]').content;

            fetch(`/characters/${blockerId}/blocks/${blockedId}`, {
                method: shouldBlock ? 'POST' : 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error();
                return res.json();
            })
            .then(() => location.reload())
            .catch(() => alert(shouldBlock ? "Failed to block character." : "Failed to unblock character."));
        }

        function shortSigil(id) {
            const n = Number.isFinite(id) ? id : 0;
            return Math.abs((n * 2654435761) % 0xFFFFFFFF)
                .toString(16)
                .toUpperCase()
                .slice(0, 4);
        }

        /* Popover behavior */
        const pop = document.getElementById('char-popover');
        const popTitle = document.getElementById('char-popover-title');
        const popAvatar = document.getElementById('char-popover-avatar');
        const popProfile = document.getElementById('char-popover-profile');
        const popDm = document.getElementById('char-popover-dm');

        let popState = { userId: null, characterId: null };

        function hidePopover() {
            if (!pop) return;
            pop.classList.add('hidden');
            popState = { userId: null, characterId: null };
        }

        function positionPopoverNear(el) {
            if (!pop || !el) return;
            const r = el.getBoundingClientRect();

            let top = r.bottom + 8;
            let left = r.left;

            const pad = 8;
            const w = pop.offsetWidth || 256;
            const h = pop.offsetHeight || 140;

            if (left + w > window.innerWidth - pad) left = window.innerWidth - w - pad;
            if (top + h > window.innerHeight - pad) top = r.top - h - 8;

            if (left < pad) left = pad;
            if (top < pad) top = pad;

            pop.style.left = `${left}px`;
            pop.style.top = `${top}px`;
        }

        function openPopoverFromTrigger(triggerEl) {
            if (!pop || !triggerEl) return;

            const characterId = (triggerEl.dataset.characterId || '').trim();
            const userIdRaw = (triggerEl.dataset.userId || '').trim();
            const userId = userIdRaw ? parseInt(userIdRaw, 10) : null;

            const characterName = (triggerEl.dataset.characterName || triggerEl.textContent || '').trim();
            const avatar = (triggerEl.dataset.characterAvatar || '').trim();

            // Optional debug (safe: values exist now)
            // console.log('popover trigger dataset:', { characterId, userIdRaw, userId, characterName });

            const sigil = characterId ? shortSigil(parseInt(characterId, 10)) : '----';

            if (popTitle) popTitle.textContent = `${characterName} #${sigil}`;
            if (popAvatar) {
                popAvatar.innerHTML = avatarHtml(avatar, characterName, 'h-20 w-20', 'rounded-lg');
            }
          
            const isMine = (userId && userId === currentUserId);
            if (popProfile) {
                popProfile.href = (isMine && characterId) ? `/characters/${characterId}` : '#';
                popProfile.classList.toggle('hidden', !isMine);
            }

            // DM button only if it is not me and we have a user id
            if (popDm) {
                if (userId && userId !== currentUserId) {
                    popDm.classList.remove('hidden');
                    popDm.disabled = false;
                    popState.userId = userId;
                    popState.characterId = characterId;
                } else {
                    popDm.classList.add('hidden');
                    popDm.disabled = true;
                    popState.userId = null;
                    popState.characterId = characterId;
                }
            }

            pop.classList.remove('hidden');
            positionPopoverNear(triggerEl);
        }

        document.addEventListener('click', function(e) {
            const target = e.target instanceof Element ? e.target : e.target?.parentElement;
            const trigger = target?.closest('.char-trigger');
            const clickedInsidePopover = pop && e.target instanceof Node && pop.contains(e.target);

            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                openPopoverFromTrigger(trigger);
                return;
            }

            if (!clickedInsidePopover) hidePopover();
        });
/* */
        window.addEventListener('resize', () => hidePopover());
        window.addEventListener('scroll', () => hidePopover(), true);

        popDm?.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!popState.userId) return;

            fetch('/dms/start', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    other_character_id: parseInt(popState.characterId, 10),
                    my_character_id: getTabCharacterId()
                })

            })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.slug) return;

                window.dispatchEvent(new CustomEvent('open-dm-window', {
                    detail: {
                        slug: data.slug,
                        my_character_id: getTabCharacterId(),
                        other_character_id: parseInt(popState.characterId, 10),
                        is_blocked_by_viewer: false
                    }
                }));
            })
            .catch(() => {});
        });




        /* fade styles */
        function buildStops(s) {
            const stops = [];
            if (s.c1) stops.push(s.c1);
            if (s.c2) stops.push(s.c2);
            if (s.c3) stops.push(s.c3);
            if (s.c4) stops.push(s.c4);
            return stops.filter(Boolean);
        }
        function applyGradientText(el, stops) {
            el.style.backgroundImage = `linear-gradient(90deg, ${stops.join(',')})`;
            el.style.webkitBackgroundClip = 'text';
            el.style.backgroundClip = 'text';
            el.style.color = 'transparent';
            el.style.display = 'inline-block';
        }
        function applySolidText(el, color) {
            el.style.backgroundImage = '';
            el.style.webkitBackgroundClip = '';
            el.style.backgroundClip = '';
            el.style.color = color || '#D8F3FF';
        }
        function applyStyleFromDataset(el) {
            if (!el) return;
            let s = {};
            try { s = JSON.parse(el.dataset.style || '{}'); } catch(e) { s = {}; }
            const stops = buildStops(s);
            const shouldFade = !!s.fade && stops.length >= 2;
            if (shouldFade) applyGradientText(el, stops);
            else applySolidText(el, s.c1);
        }
        function applyStylesIn(root) {
            (root || document).querySelectorAll('.msg-name, .msg-body').forEach(applyStyleFromDataset);
        }
        applyStylesIn(document);

        /* active character */
        function getTabCharacterId() {
            const v = sessionStorage.getItem('active_character_id');
            return v ? parseInt(v, 10) : 0;
        }
        function getViewerCharacterId() {
            const id = getTabCharacterId();
            return ownedCharacterIds.includes(id) ? id : 0;
        }
        function setTabCharacterId(id) {
            sessionStorage.setItem('active_character_id', String(id));
            if (hiddenChar) hiddenChar.value = String(id);
        }

        window.Storybox = window.Storybox || {};
        window.Storybox.activeCharacterId = () => getTabCharacterId();
        window.StoryboxChannelCharacters = window.StoryboxChannelCharacters || {};

        /* room per character (client-side snapping) */
        function setLastRoomForCharacter(characterId, slug) {
            if (!characterId || !slug) return;
            localStorage.setItem('char_room_' + characterId, slug);
        }
        function getLastRoomForCharacter(characterId) {
            return localStorage.getItem('char_room_' + characterId) || '';
        }

        (function initActiveCharacterPerTab() {
            if (!switcher) return;
            const stored = getTabCharacterId();
            if (stored) {
                switcher.value = String(stored);
                setTabCharacterId(stored);
            } else {
                setTabCharacterId(parseInt(switcher.value, 10));
            }

            const cid = getTabCharacterId();
            if (cid) setLastRoomForCharacter(cid, roomSlug);
        })();

        switcher?.addEventListener('change', function() {
            const newId = parseInt(this.value, 10);
            if (!newId) return;

            const oldId = getTabCharacterId();
            if (oldId) setLastRoomForCharacter(oldId, roomSlug);

            setTabCharacterId(newId);

            const target = getLastRoomForCharacter(newId);
            if (target && target !== roomSlug) {
                window.location.href = `/rooms/${target}`;
                return;
            }

            sendPresencePing();
        });

        /* presence */
        function sendPresencePing() {
            const characterId = getTabCharacterId();
            if (!characterId) return Promise.resolve();

            return fetch(`/rooms/${roomSlug}/presence`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ character_id: characterId }),
            })
            .then((response) => {
                if (response.ok) clearRoomUnreadBadge(conversationId);
                return response;
            })
            .catch(() => {});
        }
        sendPresencePing().finally(() => startRoomRealtime());
        setInterval(sendPresencePing, 30000);

        /* leave room */
        function leaveRoom() {
            const characterId = getTabCharacterId();
            if (!characterId) return Promise.resolve();

            return fetch(`/rooms/${roomSlug}/leave`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                keepalive: true,
                body: JSON.stringify({ character_id: characterId }),
            }).catch(() => {});
        }

        document.getElementById('leave-room-btn')?.addEventListener('click', () => {
            const cid = getTabCharacterId();
            if (cid) setLastRoomForCharacter(cid, roomSlug);
            leaveRoom().finally(() => window.location.href = '/rooms');
        });

        window.addEventListener('beforeunload', () => leaveRoom());

        /* send */
        function syncContentMirror() {
            if (contentMirror && textarea) contentMirror.value = textarea.value;
        }
        textarea?.addEventListener('input', syncContentMirror);

        textarea?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                syncContentMirror();
                form?.requestSubmit();
            }
        });

        form?.addEventListener('submit', function() {
            syncContentMirror();
            const id = getTabCharacterId();
            if (hiddenChar) hiddenChar.value = String(id);
        });

        async function fetchJson(url, options, label) {
            const response = await fetch(url, options);
            const text = await response.text();
            let data = null;

            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (error) {
                    console.error(`${label} returned invalid JSON`, {
                        status: response.status,
                        body: text,
                        error,
                    });
                    throw error;
                }
            }

            if (!response.ok) {
                console.error(`${label} failed`, {
                    status: response.status,
                    data,
                });
                throw new Error(`${label} failed with status ${response.status}`);
            }

            return data;
        }

        function openReportModal(row) {
            if (!row || row.dataset.deleted === '1') return;
            reportMessageId = row.dataset.messageId || null;
            if (!reportMessageId || !reportModal || !reportReason) return;

            reportReason.value = '';
            reportModal.classList.remove('hidden');
            reportReason.focus();
        }

        function closeReportModal() {
            reportMessageId = null;
            reportModal?.classList.add('hidden');
            if (reportReason) reportReason.value = '';
            if (reportSubmit) reportSubmit.disabled = false;
        }

        reportCancel?.addEventListener('click', closeReportModal);
        reportModal?.addEventListener('click', (e) => {
            if (e.target === reportModal) closeReportModal();
        });

        reportForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!reportMessageId || !reportReason) return;

            const reason = reportReason.value.trim();
            if (!reason) {
                reportReason.focus();
                return;
            }

            if (reportSubmit) reportSubmit.disabled = true;

            try {
                const data = await fetchJson(`/messages/${reportMessageId}/reports`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ reason }),
                }, 'Report message');

                if (!data || !data.ok) {
                    console.error('Report message returned an unexpected response', data);
                    if (reportSubmit) reportSubmit.disabled = false;
                    return;
                }

                const row = Array.from(container?.querySelectorAll('.msg-row') || [])
                    .find((candidate) => candidate.dataset.messageId === reportMessageId);
                const reportBtn = row?.querySelector('.msg-report-btn');
                if (reportBtn) {
                    reportBtn.textContent = 'Reported';
                    reportBtn.disabled = true;
                }

                closeReportModal();
            } catch (error) {
                console.error('Report message error:', error);
                if (reportSubmit) reportSubmit.disabled = false;
            }
        });

        /* report/edit/delete */
        function canEditMessageRow(row) {
            if (!row) return false;
            if (row.dataset.canEdit === '1') return true;
            const uid = parseInt(row.dataset.userId || '0', 10);
            return (uid && uid === currentUserId) || !!isAdmin;
        }

        async function handleMessageActionClick(e) {
            const target = e.target instanceof Element ? e.target : e.target?.parentElement;
            const actionBtn = target?.closest('.msg-report-btn, .msg-edit-btn, .msg-del-btn, .msg-save-btn, .msg-cancel-btn');
            if (!actionBtn || !container || !container.contains(actionBtn)) return;

            const row = actionBtn.closest('.msg-row');
            if (!row) return;

            const editBtn = row.querySelector('.msg-edit-btn');
            const delBtn  = row.querySelector('.msg-del-btn');
            const reportBtn = row.querySelector('.msg-report-btn');
            const bodyEl  = row.querySelector('.msg-body');
            const editBox = row.querySelector('.msg-editbox');
            const ta      = row.querySelector('.msg-edit-textarea');
            const editedTag = row.querySelector('.msg-edited');
            const deletedTag = row.querySelector('.msg-deleted');

            const id = row.dataset.messageId;
            const isDeleted = row.dataset.deleted === '1';

            if (actionBtn.classList.contains('msg-report-btn')) {
                openReportModal(row);
                return;
            }

            if (!canEditMessageRow(row)) return;

            if (isDeleted) {
                if (editBtn) editBtn.disabled = true;
                if (delBtn) delBtn.disabled = true;
                if (reportBtn) reportBtn.disabled = true;
                return;
            }

            if (actionBtn.classList.contains('msg-edit-btn')) {
                if (!bodyEl || !editBox || !ta) return;
                ta.value = (bodyEl.textContent || '').trim();
                editBox.classList.remove('hidden');
                if (editBtn) editBtn.disabled = true;
                if (delBtn) delBtn.disabled = true;
                return;
            }

            if (actionBtn.classList.contains('msg-cancel-btn')) {
                if (!editBox) return;
                editBox.classList.add('hidden');
                if (editBtn) editBtn.disabled = false;
                if (delBtn) delBtn.disabled = false;
                return;
            }

            if (actionBtn.classList.contains('msg-save-btn')) {
                if (!ta || !bodyEl || !id) return;
                const newBody = ta.value;

                try {
                    const data = await fetchJson(`/messages/${id}`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ body: newBody }),
                    }, 'Edit message');

                    if (!data || !data.ok) {
                        console.error('Edit message returned an unexpected response', data);
                        return;
                    }

                    bodyEl.textContent = data.message?.body ?? data.message?.content ?? newBody;
                    editedTag?.classList.remove('hidden');

                    editBox?.classList.add('hidden');
                    if (editBtn) editBtn.disabled = false;
                    if (delBtn) delBtn.disabled = false;
                } catch (error) {
                    console.error('Edit message error:', error);
                    if (editBtn) editBtn.disabled = false;
                    if (delBtn) delBtn.disabled = false;
                }
                return;
            }

            if (actionBtn.classList.contains('msg-del-btn')) {
                if (!id || !confirm('Delete this message?')) return;

                try {
                    const data = await fetchJson(`/messages/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                    }, 'Delete message');

                    if (!data || !data.ok) {
                        console.error('Delete message returned an unexpected response', data);
                        return;
                    }

                    row.dataset.deleted = '1';
                    if (bodyEl) bodyEl.textContent = '[deleted]';
                    deletedTag?.classList.remove('hidden');

                    if (editBtn) editBtn.disabled = true;
                    if (delBtn) delBtn.disabled = true;
                    if (reportBtn) reportBtn.disabled = true;
                    editBox?.classList.add('hidden');
                } catch (error) {
                    console.error('Delete message error:', error);
                }
            }
        }

        /* fetch new messages */
        function fetchNewMessages() {
            fetch(`/rooms/${roomSlug}/messages?after=${lastMessageId}&character_id=${getTabCharacterId()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (!Array.isArray(data) || data.length === 0) return;
                if (!container) return;

                const wasNearBottom =
                    container.scrollHeight - container.scrollTop - container.clientHeight < 80;

                data.forEach(msg => {
                    const mid = parseInt(msg.id, 10);
                    if (!mid || seenMessageIds.has(mid)) return;
                    seenMessageIds.add(mid);

                    const name = (msg.character && msg.character.name)
                        ? msg.character.name
                        : (msg.user?.name ?? 'Unknown');
                    const avatar = msg.character?.avatar || '';

                    let s = msg.character?.settings || {};
                    if (typeof s === 'string') { try { s = JSON.parse(s); } catch(e) { s = {}; } }

                    const c1 = s.text_color_1 || '#D8F3FF';
                    const c2 = s.text_color_2 || null;
                    const c3 = s.text_color_3 || null;
                    const c4 = s.text_color_4 || null;

                    const fadeMsg = !!s.fade_message;
                    const fadeName = !!s.fade_name;

                    const isDeleted = !!msg.deleted_at || !!msg.is_deleted || (msg.body === '[deleted]') || (msg.content === '[deleted]');
                    const text = isDeleted ? '[deleted]' : String(msg.content ?? msg.body ?? '').trim();
                    const isBlockedByViewer = !isAdmin && parseBool(msg.is_blocked_by_viewer);

                    const canEdit = !!isAdmin || ((msg.user_id ?? 0) === currentUserId);
                    const viewerCharacterId = getViewerCharacterId();
                    const messageCharacterId = parseInt(msg.character?.id ?? msg.character_id ?? 0, 10) || 0;
                    const previousRow = Array.from(container.querySelectorAll('.msg-row')).pop();
                    const previousCharacterId = parseInt(previousRow?.dataset.characterId || '0', 10) || 0;
                    const isGrouped = messageCharacterId > 0 && previousCharacterId === messageCharacterId;
                    const blockLabel = isBlockedByViewer ? 'Blocked' : 'Block';
                    const blockClass = isBlockedByViewer ? 'text-gray-400 hover:text-gray-300' : 'text-red-400 hover:text-red-300';
                    const blockButtonHtml = (!isAdmin && viewerCharacterId && messageCharacterId && messageCharacterId !== viewerCharacterId)
                        ? `<button type="button" class="text-xs ${blockClass} ml-1" onclick="setCharacterBlock(${viewerCharacterId}, ${messageCharacterId}, ${isBlockedByViewer ? 'false' : 'true'})">${blockLabel}</button>`
                        : '';

                    const div = document.createElement('div');
                    div.className = `group relative flex flex-none gap-2 px-2 ${isGrouped ? 'border-0 rounded-none py-0' : 'border-t border-gray-900/40 py-0.5'} msg-row` + (isBlockedByViewer ? " opacity-70" : "");
                    div.dataset.messageId = String(msg.id);
                    div.dataset.userId = String(msg.user_id ?? 0);
                    div.dataset.characterId = messageCharacterId ? String(messageCharacterId) : '';
                    div.dataset.canEdit = canEdit ? '1' : '0';
                    div.dataset.deleted = isDeleted ? '1' : '0';
                    div.dataset.blockedByViewer = isBlockedByViewer ? '1' : '0';

                    const safeNameAttr = escAttr(name);
                    const safeNameHtml = escHtml(name);
                    const safeTextHtml = escHtml(text);
                    const safeAvatarAttr = escAttr(avatar);
                    const safeCreatedAt = escHtml(msg.created_at_human ?? '');
                    const nameStyle = escAttr(JSON.stringify({c1,c2,c3,c4,fade:fadeName}));
                    const bodyStyle = escAttr(JSON.stringify({c1,c2,c3,c4,fade:fadeMsg}));
                    const avatarMarkup = isGrouped ? `
                        <div class="w-7 shrink-0"></div>
                    ` : `<div class="w-7 shrink-0">${avatarHtml(avatar, name, 'h-7 w-7')}</div>`;
                    const nameMarkup = isGrouped ? '' : `
                        <div class="mb-0 flex items-baseline gap-2">
                            <button type="button"
                                class="char-trigger msg-name text-base font-bold leading-none text-left cursor-pointer hover:underline focus:outline-none focus:ring-2 focus:ring-emerald-500/50 rounded-sm"
                                data-style='${nameStyle}'
                                data-character-id="${messageCharacterId || ''}"
                                data-user-id="${msg.user_id ?? ''}"
                                data-character-name="${safeNameAttr}"
                                data-character-avatar="${safeAvatarAttr}">
                                ${safeNameHtml}
                            </button>

                            <span class="text-[10px] text-gray-500 ml-2">${safeCreatedAt}</span>
                            <span class="msg-edited text-[10px] text-gray-500 ml-2 hidden">(edited)</span>
                            <span class="msg-deleted text-[10px] text-gray-500 ml-2 ${isDeleted ? '' : 'hidden'}">(deleted)</span>
                        </div>
                    `;

                    div.innerHTML = `
                        ${avatarMarkup}

                        <div class="min-w-0 flex-1 pr-28">
                            ${nameMarkup}

                            ${isBlockedByViewer ? `
                                <div class="msg-blocked-notice text-xs text-gray-500 mt-1">
                                    Message hidden from a blocked character.
                                </div>
                            ` : ''}

                            <div class="msg-body-wrapper mt-0 text-sm leading-snug ${isBlockedByViewer ? 'hidden msg-blocked-body' : ''}"><span class="msg-body text-sm text-gray-400 leading-snug whitespace-pre-line" data-style='${bodyStyle}'>${safeTextHtml}</span></div>

                            ${canEdit ? `
                                <div class="msg-editbox hidden mt-2">
                                    <textarea class="msg-edit-textarea w-full rounded border border-gray-700 bg-gray-950 text-base text-gray-100 leading-relaxed p-2 focus:border-emerald-500 focus:ring-emerald-500" rows="3"></textarea>
                                    <div class="mt-2 flex gap-2 justify-end">
                                        <button type="button" class="msg-cancel-btn rounded border border-gray-700 bg-gray-900 px-2 py-1 text-gray-200 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">Cancel</button>
                                        <button type="button" class="msg-save-btn rounded border border-emerald-500/50 bg-emerald-500/10 px-2 py-1 text-emerald-100 hover:bg-emerald-500/20 focus:outline-none focus:ring-2 focus:ring-emerald-500/50">Save</button>
                                    </div>
                                </div>
                            ` : ''}
                        </div>

                        ${canEdit ? `
                            <div class="msg-actions absolute right-2 top-1 flex items-center gap-1 text-[10px] opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                <button type="button" class="msg-report-btn rounded border border-gray-700/80 bg-gray-950/80 px-2 py-1 text-gray-300 hover:border-gray-600 hover:bg-gray-800 hover:text-gray-100 disabled:opacity-40" ${isDeleted ? 'disabled' : ''}>Report</button>
                                ${blockButtonHtml}
                                <button type="button" class="msg-edit-btn rounded border border-gray-700/80 bg-gray-950/80 px-2 py-1 text-gray-300 hover:border-gray-600 hover:bg-gray-800 hover:text-gray-100 disabled:opacity-40" ${isDeleted ? 'disabled' : ''}>Edit</button>
                                <button type="button" class="msg-del-btn rounded border border-gray-700/80 bg-gray-950/80 px-2 py-1 text-gray-300 hover:border-red-500/50 hover:bg-red-500/10 hover:text-red-200 disabled:opacity-40" ${isDeleted ? 'disabled' : ''}>Delete</button>
                            </div>
                        ` : `
                            <div class="msg-actions absolute right-2 top-1 flex items-center gap-1 text-[10px] opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                <button type="button" class="msg-report-btn rounded border border-gray-700/80 bg-gray-950/80 px-2 py-1 text-gray-300 hover:border-gray-600 hover:bg-gray-800 hover:text-gray-100 disabled:opacity-40" ${isDeleted ? 'disabled' : ''}>Report</button>
                                ${blockButtonHtml}
                            </div>
                        `}

                    `;

                    container.appendChild(div);
                    applyStylesIn(div);

                    if (mid > lastMessageId) lastMessageId = mid;
                });

                if (wasNearBottom) container.scrollTop = container.scrollHeight;
            })
            .catch(() => {});
        }

        function startRoomRealtime() {
            if (!window.Echo || !conversationId) return;

            window.StoryboxChannelCharacters[conversationChannelName] = getTabCharacterId();

            window.Echo.private(`conversation.${conversationId}`)
                .listen('.message.created', (event) => {
                    if (seenMessageIds.has(parseInt(event.id, 10))) return;
                    fetchNewMessages();
                    sendPresencePing();
                });

            window.Echo.connector?.pusher?.connection?.bind('connected', () => fetchNewMessages());
        }

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) fetchNewMessages();
        });

        setInterval(fetchNewMessages, 2500);

        /* roster */
        function refreshUserList() {
            if (!userListEl) return;

            fetch(`/rooms/${roomSlug}/roster`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                const roster = Array.isArray(data.roster) ? data.roster : [];
                userListEl.innerHTML = '';
                if (activeCountEl) {
                    activeCountEl.innerHTML = `Active <span class="font-medium text-gray-300">${roster.length}</span>`;
                }

                if (!roster.length) {
                    userListEl.innerHTML = `<div class="text-gray-500">Nobody here.</div>`;
                    return;
                }

                roster.forEach(p => {
                    let s = p.settings || {};
                    if (typeof s === 'string') { try { s = JSON.parse(s); } catch(e) { s = {}; } }

                    const c1 = s.text_color_1 || '#D8F3FF';
                    const c2 = s.text_color_2 || null;
                    const c3 = s.text_color_3 || null;
                    const c4 = s.text_color_4 || null;
                    const fadeName = !!s.fade_name;

                    const charId = parseInt(p.character_id, 10) || 0;
                    const sigil = shortSigil(charId);
                    const displayName = (p.character_name ?? ('#' + charId));
                    const avatar = p.avatar || '';

                    const row = document.createElement('div');
                    row.className = 'char-row rounded border border-gray-800/80 bg-gray-900/50 px-3 py-2';

                    const safeNameAttr = escAttr(displayName);
                    const safeAvatarAttr = escAttr(avatar);
                    const safeDisplayName = escHtml(displayName);
                    const nameStyle = escAttr(JSON.stringify({c1,c2,c3,c4,fade:fadeName}));

                    row.innerHTML = `
                        <div class="flex items-center gap-2">
                            ${avatarHtml(avatar, displayName, 'h-8 w-8')}
                            <div class="min-w-0">
                                <button type="button"
                                    class="char-trigger msg-name text-base font-bold leading-none hover:underline text-left cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-500/50 rounded-sm"
                                    data-style='${nameStyle}'
                                    data-character-id="${p.character_id ?? ''}"
                                    data-user-id="${p.user_id ?? ''}"
                                    data-character-name="${safeNameAttr}"
                                    data-character-avatar="${safeAvatarAttr}">
                                    ${safeDisplayName}
                                </button>

                                <div class="mt-1 text-[10px] text-gray-500">ID #${sigil}</div>
                            </div>
                        </div>
                    `;

                    userListEl.appendChild(row);
                });

                applyStylesIn(userListEl);
            })
            .catch((err) => {
                console.error('Roster error:', err);
                userListEl.innerHTML = `<div class="text-red-400">Roster error</div>`;
                if (activeCountEl) {
                    activeCountEl.innerHTML = `Active <span class="font-medium text-gray-500">unavailable</span>`;
                }
            });
        }
        /* Refresh DMs */
        const dmListEl = document.getElementById('panel-dms');

        /* tabs */
function showRoomsTab() {
    tabRooms.className = 'rounded border border-emerald-500/40 bg-emerald-500/10 px-2 py-1.5 text-emerald-200';
    tabUsers.className = 'rounded border border-gray-800 px-2 py-1.5 text-gray-400 hover:border-gray-700 hover:bg-gray-800 hover:text-gray-100';

    panelRooms.classList.remove('hidden');
    panelUsers.classList.add('hidden');

    if (tabMeta) tabMeta.textContent = '# active / name';
}

function showUsersTab() {
    tabUsers.className = 'rounded border border-emerald-500/40 bg-emerald-500/10 px-2 py-1.5 text-emerald-200';
    tabRooms.className = 'rounded border border-gray-800 px-2 py-1.5 text-gray-400 hover:border-gray-700 hover:bg-gray-800 hover:text-gray-100';

    panelRooms.classList.add('hidden');
    panelUsers.classList.remove('hidden');

    if (tabMeta) tabMeta.textContent = 'character / user';
    refreshUserList();
}

tabRooms?.addEventListener('click', showRoomsTab);
tabUsers?.addEventListener('click', showUsersTab);
showRoomsTab();
refreshUserList();

setInterval(() => {
    if (panelUsers && !panelUsers.classList.contains('hidden')) refreshUserList();
}, 5000);


        if (container) container.scrollTop = container.scrollHeight;
    </script>
</x-app-layout>
