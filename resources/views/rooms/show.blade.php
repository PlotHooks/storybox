{{-- resources/views/rooms/show.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $room->name }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-none w-full mx-auto h-[calc(100vh-6rem)] flex flex-col lg:flex-row gap-4 px-2 md:px-4">

            {{-- LEFT COLUMN --}}
            <div id="left-panel" class="w-full lg:w-72 bg-gray-900 text-gray-100 rounded-lg shadow flex flex-col">
                <div class="px-3 py-2 border-b border-gray-800 text-xs font-semibold text-green-400">
                    Left Panel (reserved)
                </div>
                <div class="flex-1 overflow-y-auto px-3 py-2 text-xs text-gray-300">
                    <p class="text-gray-500">Reserved for future features.</p>
                </div>
            </div>

            {{-- CENTER --}}
            <div class="flex-1 bg-gray-900 rounded-lg shadow flex flex-col">

                {{-- Top bar --}}
                <div class="flex items-center justify-between px-4 py-2 border-b border-gray-800">
                    <div class="text-xs text-gray-300">
                        Room owner:
                        <span class="font-semibold text-gray-100">
                            {{ optional($room->owner)->name ?? 'Unknown' }}
                        </span>
                    </div>

                    @php
                        $characters = Auth::user()->characters;
                        $isAdminBlade = (bool) (Auth::user()->is_admin ?? false);
                    @endphp

                    @if ($characters->count() > 0)
                        <div class="flex items-center gap-2">

                            <button id="toggle-left" type="button"
                                class="rounded border border-gray-700 bg-gray-800 text-xs text-gray-100 px-2 py-1 hover:bg-gray-700">
                                Toggle Left
                            </button>

                            <button id="toggle-right" type="button"
                                class="rounded border border-gray-700 bg-gray-800 text-xs text-gray-100 px-2 py-1 hover:bg-gray-700">
                                Toggle Right
                            </button>

                            <span class="text-xs text-gray-300">Posting as:</span>

                            <select id="character-switcher"
                                class="rounded border-gray-700 bg-gray-800 text-xs text-gray-100 px-2 py-1">
                                @foreach ($characters as $char)
                                    <option value="{{ $char->id }}" {{ $char->id == $activeCharacterId ? 'selected' : '' }}>
                                        {{ $char->name }}
                                    </option>
                                @endforeach
                            </select>

                            <button id="leave-room-btn" type="button"
                                class="rounded border border-gray-700 bg-gray-800 text-xs text-gray-100 px-2 py-1 hover:bg-gray-700">
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
                <div id="message-container" class="flex-1 overflow-y-auto px-4 py-2">
                    @foreach ($messages as $message)
                        @php
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
                        @endphp

                        <div class="border-b border-gray-800 py-1.5 msg-row"
                             data-message-id="{{ $message->id }}"
                             data-user-id="{{ $message->user_id }}"
                             data-can-edit="{{ $canEdit ? '1' : '0' }}"
                             data-deleted="{{ $isDeleted ? '1' : '0' }}">

                            <div class="flex items-start justify-between gap-2 leading-tight mb-0">
                                <div class="flex items-start gap-2">
                                    <button type="button"
                                        class="char-trigger msg-name text-sm md:text-base font-medium text-left cursor-pointer hover:underline"
                                        data-style='{!! $nameStyleJson !!}'
                                        data-character-id="{{ $c?->id ?? '' }}"
                                        data-user-id="{{ $message->user_id ?? '' }}"
                                        data-character-name="{{ e($name) }}">
                                        {{ $name }}
                                    </button>

                                    <span class="text-[10px] text-gray-500 opacity-70">{{ $message->created_at->diffForHumans() }}</span>
                                    <span class="msg-edited text-[10px] text-gray-500 opacity-70 hidden">(edited)</span>
                                    <span class="msg-deleted text-[10px] text-gray-500 opacity-70 {{ $isDeleted ? '' : 'hidden' }}">(deleted)</span>
                                </div>

                                <div class="flex items-center gap-2 text-[10px]">
                                    <button type="button"
                                        class="msg-report-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700"
                                        {{ $isDeleted ? 'disabled' : '' }}>
                                        Report
                                    </button>

                                    @if ($canEdit)
                                        <button type="button"
                                            class="msg-edit-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700"
                                            {{ $isDeleted ? 'disabled' : '' }}>
                                            Edit
                                        </button>
                                        <button type="button"
                                            class="msg-del-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700"
                                            {{ $isDeleted ? 'disabled' : '' }}>
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <div class="text-sm md:text-base text-gray-100 whitespace-pre-line leading-snug">
                                <span class="msg-body" data-style='{!! $bodyStyleJson !!}'>
                                    {{ $isDeleted ? '[deleted]' : $text }}
                                </span>

                                @if ($canEdit)
                                    <div class="msg-editbox hidden mt-2">
                                        <textarea class="msg-edit-textarea w-full rounded border border-gray-700 bg-gray-950 text-base text-gray-100 leading-relaxed p-2"
                                                  rows="3"></textarea>
                                        <div class="mt-2 flex gap-2 justify-end">
                                            <button type="button"
                                                class="msg-cancel-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700">
                                                Cancel
                                            </button>
                                            <button type="button"
                                                class="msg-save-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700">
                                                Save
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Send --}}
                <div class="border-t border-gray-800 p-3">
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
                            class="mt-1 block w-full rounded-md border-gray-700 bg-gray-950 text-gray-100"
                        >{{ old('body') }}</textarea>

                        <div class="mt-2 flex justify-end">
                            <x-primary-button>
                                Send
                            </x-primary-button>
                        </div>
                    </form>
                </div>

            </div>

            {{-- RIGHT --}}
            <div id="right-panel" class="w-full lg:w-80 bg-gray-900 text-gray-100 rounded-lg shadow flex flex-col">

                <div class="px-3 py-2 border-b border-gray-800 text-xs font-semibold text-green-400 flex items-center gap-2">
                    <button id="tab-rooms" type="button" class="px-2 py-1 rounded bg-gray-800">Rooms</button>
                    <button id="tab-users" type="button" class="px-2 py-1 rounded hover:bg-gray-800">Users</button>
                    <span id="tab-meta" class="text-[10px] text-gray-500 ml-auto"># active / name</span>
                </div>

                <div class="flex-1 overflow-y-auto text-xs">

                    <div id="panel-rooms">
                        @foreach ($sidebarRooms as $r)
                            <button type="button"
                                onclick="window.location.href='{{ route('rooms.show', $r->slug) }}'"
                                class="w-full text-left px-3 py-1.5 hover:bg-gray-800">
                                {{ $r->name }}
                            </button>
                        @endforeach
                    </div>

                    <div id="panel-users" class="hidden px-3 py-2">
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
            <div id="char-popover-title" class="font-semibold text-gray-100 text-sm"></div>
            <div id="char-popover-sub" class="text-[10px] text-gray-400 mt-1">ID verification</div>

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

        const reportModal = document.getElementById('message-report-modal');
        const reportForm = document.getElementById('message-report-form');
        const reportReason = document.getElementById('message-report-reason');
        const reportSubmit = document.getElementById('message-report-submit');
        const reportCancel = document.getElementById('message-report-cancel');
        let reportMessageId = null;

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

            // Optional debug (safe: values exist now)
            // console.log('popover trigger dataset:', { characterId, userIdRaw, userId, characterName });

            const sigil = characterId ? shortSigil(parseInt(characterId, 10)) : '----';

            if (popTitle) popTitle.textContent = `${characterName} #${sigil}`;
          
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
                    detail: { slug: data.slug }
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
            }).catch(() => {});
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
            fetch(`/rooms/${roomSlug}/messages?after=` + lastMessageId, {
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

                    let s = msg.character?.settings || {};
                    if (typeof s === 'string') { try { s = JSON.parse(s); } catch(e) { s = {}; } }

                    const c1 = s.text_color_1 || '#D8F3FF';
                    const c2 = s.text_color_2 || null;
                    const c3 = s.text_color_3 || null;
                    const c4 = s.text_color_4 || null;

                    const fadeMsg = !!s.fade_message;
                    const fadeName = !!s.fade_name;

                    const isDeleted = !!msg.deleted_at || !!msg.is_deleted || (msg.body === '[deleted]') || (msg.content === '[deleted]');
                    const text = isDeleted ? '[deleted]' : (msg.content ?? msg.body ?? '');

                    const canEdit = !!isAdmin || ((msg.user_id ?? 0) === currentUserId);

                    const div = document.createElement('div');
                    div.className = "border-b border-gray-800 py-1.5 msg-row";
                    div.dataset.messageId = String(msg.id);
                    div.dataset.userId = String(msg.user_id ?? 0);
                    div.dataset.canEdit = canEdit ? '1' : '0';
                    div.dataset.deleted = isDeleted ? '1' : '0';

                    const safeNameAttr = escAttr(name);
                    const safeNameHtml = escHtml(name);
                    const safeTextHtml = escHtml(text);
                    const safeCreatedAt = escHtml(msg.created_at_human ?? '');
                    const nameStyle = escAttr(JSON.stringify({c1,c2,c3,c4,fade:fadeName}));
                    const bodyStyle = escAttr(JSON.stringify({c1,c2,c3,c4,fade:fadeMsg}));

                    div.innerHTML = `
                        <div class="flex items-start justify-between gap-2 leading-tight mb-0">
                            <div class="flex items-start gap-2">
                                <button type="button"
                                    class="char-trigger msg-name text-sm md:text-base font-medium text-left cursor-pointer hover:underline"
                                    data-style='${nameStyle}'
                                    data-character-id="${msg.character?.id ?? ''}"
                                    data-user-id="${msg.user_id ?? ''}"
                                    data-character-name="${safeNameAttr}">
                                    ${safeNameHtml}
                                </button>

                                <span class="text-[10px] text-gray-500 opacity-70">${safeCreatedAt}</span>
                                <span class="msg-edited text-[10px] text-gray-500 opacity-70 hidden">(edited)</span>
                                <span class="msg-deleted text-[10px] text-gray-500 opacity-70 ${isDeleted ? '' : 'hidden'}">(deleted)</span>
                            </div>

                            ${canEdit ? `
                                <div class="flex items-center gap-2 text-[10px]">
                                    <button type="button" class="msg-report-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700" ${isDeleted ? 'disabled' : ''}>Report</button>
                                    <button type="button" class="msg-edit-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700" ${isDeleted ? 'disabled' : ''}>Edit</button>
                                    <button type="button" class="msg-del-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700" ${isDeleted ? 'disabled' : ''}>Delete</button>
                                </div>
                            ` : `
                                <div class="flex items-center gap-2 text-[10px]">
                                    <button type="button" class="msg-report-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700" ${isDeleted ? 'disabled' : ''}>Report</button>
                                </div>
                            `}
                        </div>

                        <div class="text-sm md:text-base text-gray-100 whitespace-pre-line leading-snug">
                            <span class="msg-body" data-style='${bodyStyle}'>${safeTextHtml}</span>

                            ${canEdit ? `
                                <div class="msg-editbox hidden mt-2">
                                    <textarea class="msg-edit-textarea w-full rounded border border-gray-700 bg-gray-950 text-base text-gray-100 leading-relaxed p-2" rows="3"></textarea>
                                    <div class="mt-2 flex gap-2 justify-end">
                                        <button type="button" class="msg-cancel-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700">Cancel</button>
                                        <button type="button" class="msg-save-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700">Save</button>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
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

                    const row = document.createElement('div');
                    row.className = 'char-row border-b border-gray-800 pb-2';

                    const safeNameAttr = escAttr(displayName);
                    const safeDisplayName = escHtml(displayName);
                    const nameStyle = escAttr(JSON.stringify({c1,c2,c3,c4,fade:fadeName}));

                    row.innerHTML = `
                        <button type="button"
                            class="char-trigger msg-name text-sm font-medium hover:underline text-left cursor-pointer"
                            data-style='${nameStyle}'
                            data-character-id="${p.character_id ?? ''}"
                            data-user-id="${p.user_id ?? ''}"
                            data-character-name="${safeNameAttr}">
                            ${safeDisplayName}
                        </button>

                        <div class="text-[10px] text-gray-500">${safeDisplayName} #${sigil}</div>
                    `;

                    userListEl.appendChild(row);
                });

                applyStylesIn(userListEl);
            })
            .catch((err) => {
                console.error('Roster error:', err);
                userListEl.innerHTML = `<div class="text-red-400">Roster error</div>`;
            });
        }
        /* Refresh DMs */
        const dmListEl = document.getElementById('panel-dms');

        /* tabs */
function showRoomsTab() {
    tabRooms.className = 'px-2 py-1 rounded bg-gray-800';
    tabUsers.className = 'px-2 py-1 rounded hover:bg-gray-800';

    panelRooms.classList.remove('hidden');
    panelUsers.classList.add('hidden');

    if (tabMeta) tabMeta.textContent = '# active / name';
}

function showUsersTab() {
    tabUsers.className = 'px-2 py-1 rounded bg-gray-800';
    tabRooms.className = 'px-2 py-1 rounded hover:bg-gray-800';

    panelRooms.classList.add('hidden');
    panelUsers.classList.remove('hidden');

    if (tabMeta) tabMeta.textContent = 'character / user';
    refreshUserList();
}

tabRooms?.addEventListener('click', showRoomsTab);
tabUsers?.addEventListener('click', showUsersTab);
showRoomsTab();

setInterval(() => {
    if (panelUsers && !panelUsers.classList.contains('hidden')) refreshUserList();
}, 5000);


        if (container) container.scrollTop = container.scrollHeight;
    </script>
</x-app-layout>
