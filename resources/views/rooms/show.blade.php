<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $room->name }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-none w-full mx-auto h-[calc(100vh-6rem)] flex flex-col lg:flex-row gap-4 px-2 md:px-4">

            {{-- LEFT COLUMN: reserved for later --}}
            <div id="left-panel" class="w-full lg:w-72 bg-gray-900 text-gray-100 rounded-lg shadow flex flex-col">
                <div class="px-3 py-2 border-b border-gray-800 text-xs font-semibold text-green-400">
                    Left Panel (reserved)
                </div>
                <div class="flex-1 overflow-y-auto px-3 py-2 text-xs text-gray-300">
                    <p class="text-gray-500">Reserved for future features.</p>
                </div>
            </div>

            {{-- CENTER COLUMN: main room chat --}}
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
                    @endphp

                    @if ($characters->count() > 0)
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                id="toggle-left"
                                class="rounded border border-gray-700 bg-gray-800 text-xs text-gray-100 px-2 py-1 hover:bg-gray-700">
                                Toggle Left
                            </button>

                            <button
                                type="button"
                                id="toggle-right"
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

                            <button
                                type="button"
                                id="leave-room-btn"
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

                {{-- Messages list --}}
                <div id="message-container" class="flex-1 overflow-y-auto px-4 py-2">
                    @foreach ($messages as $message)
                        @php
                            $c = $message->character;
                            $name = optional($c)->name ?? $message->user->name;

                            $s = $c->settings ?? [];
                            $c1 = $s['text_color_1'] ?? '#D8F3FF';
                            $c2 = $s['text_color_2'] ?? null;
                            $c3 = $s['text_color_3'] ?? null;
                            $c4 = $s['text_color_4'] ?? null;

                            $fadeMsg = (bool) ($s['fade_message'] ?? false);
                            $fadeName = (bool) ($s['fade_name'] ?? false);

                            $nameStyleJson = json_encode([
                                'c1' => $c1, 'c2' => $c2, 'c3' => $c3, 'c4' => $c4, 'fade' => $fadeName,
                            ], JSON_UNESCAPED_SLASHES);

                            $bodyStyleJson = json_encode([
                                'c1' => $c1, 'c2' => $c2, 'c3' => $c3, 'c4' => $c4, 'fade' => $fadeMsg,
                            ], JSON_UNESCAPED_SLASHES);

                            $isOwner = $message->user_id === Auth::id();
                            $isAdmin = (bool) (Auth::user()->is_admin ?? false);
                            $canEdit = $isOwner || $isAdmin;

                            // If you are using soft deletes, this will work:
                            $isDeleted = method_exists($message, 'trashed') ? $message->trashed() : false;
                        @endphp

                        <div class="border-b border-gray-800 py-1.5 msg-row"
                             data-message-id="{{ $message->id }}"
                             data-user-id="{{ $message->user_id }}"
                             data-can-edit="{{ $canEdit ? '1' : '0' }}">

                            <div class="flex items-start justify-between gap-2 leading-tight mb-0">
                                <div class="flex items-start gap-2">
                                    <span class="msg-name text-sm md:text-base font-medium" data-style='{!! $nameStyleJson !!}'>{{ $name }}</span>
                                    <span class="text-[10px] text-gray-500 opacity-70">{{ $message->created_at->diffForHumans() }}</span>
                                    <span class="msg-edited text-[10px] text-gray-500 opacity-70 hidden">(edited)</span>
                                    <span class="msg-deleted text-[10px] text-gray-500 opacity-70 {{ $isDeleted ? '' : 'hidden' }}">(deleted)</span>
                                </div>

                                @if ($canEdit)
                                    <div class="flex items-center gap-2 text-[10px]">
                                        <button type="button" class="msg-edit-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700"
                                            {{ $isDeleted ? 'disabled' : '' }}>
                                            Edit
                                        </button>
                                        <button type="button" class="msg-del-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700"
                                            {{ $isDeleted ? 'disabled' : '' }}>
                                            Delete
                                        </button>
                                    </div>
                                @endif
                            </div>

                            <div class="text-sm md:text-base text-gray-100 whitespace-pre-line leading-snug -mt-5">
                                <span class="msg-body" data-style='{!! $bodyStyleJson !!}'>
                                    {{ $isDeleted ? '[deleted]' : $message->body }}
                                </span>

                                <div class="msg-editbox hidden mt-2">
                                    <textarea class="msg-edit-textarea w-full rounded border border-gray-700 bg-gray-950 text-base text-gray-100 leading-relaxed p-2"
                                              rows="3"></textarea>
                                    <div class="mt-2 flex gap-2 justify-end">
                                        <button type="button" class="msg-cancel-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700">
                                            Cancel
                                        </button>
                                        <button type="button" class="msg-save-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700">
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- New message form --}}
                <div class="border-t border-gray-800 p-3">
                    <form method="POST" action="{{ route('rooms.messages.store', $room) }}" id="message-form">
                        @csrf
                        <input type="hidden" name="character_id" id="character-id-input" value="">

                        <div>
                            <textarea
                                id="body"
                                name="body"
                                rows="3"
                                required
                                placeholder="Type your message. Enter to send, Shift+Enter for a new line."
                                class="mt-1 block w-full rounded-md border-gray-700 bg-gray-950 text-base md:text-lg text-gray-100 leading-relaxed shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >{{ old('body') }}</textarea>

                            @error('body')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-2 flex justify-end">
                            <x-primary-button id="send-button">
                                Send
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- RIGHT COLUMN: Rooms / Users tabs --}}
            <div id="right-panel" class="w-full lg:w-80 bg-gray-900 text-gray-100 rounded-lg shadow flex flex-col">
                <div class="px-3 py-2 border-b border-gray-800 text-xs font-semibold text-green-400 flex items-center justify-between">
                    <div class="flex gap-2">
                        <button id="tab-rooms" type="button" class="px-2 py-1 rounded bg-gray-800">Rooms</button>
                        <button id="tab-users" type="button" class="px-2 py-1 rounded hover:bg-gray-800 text-gray-200">Users</button>
                    </div>
                    <span id="tab-meta" class="text-[10px] text-gray-500"># active / name</span>
                </div>

                <div class="flex-1 overflow-y-auto text-xs">
                    <div id="panel-rooms">
                        <div id="room-list">
                            @foreach ($sidebarRooms as $r)
                                <button
                                    type="button"
                                    onclick="window.location.href='{{ route('rooms.show', $r->slug) }}'"
                                    class="w-full flex items-center justify-between px-3 py-1.5 text-left hover:bg-gray-800
                                           {{ $r->id === $room->id ? 'bg-gray-800 font-semibold text-teal-300' : 'text-gray-200' }}">
                                    <span class="w-6 text-[10px] text-gray-400 text-right">
                                        {{ $r->active_users ?? 0 }}
                                    </span>
                                    <span class="flex-1 truncate ml-1">
                                        {{ $r->name }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
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

    <style>
      .char-row { position: relative; }
      .char-card {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 6px;
        z-index: 50;
        display: none;
        width: 220px;
        padding: 8px;
        border-radius: 8px;
        background: #111827;
        border: 1px solid #374151;
        box-shadow: 0 10px 25px rgba(0,0,0,.35);
        pointer-events: none;
      }
      .char-row:hover .char-card { display: block; }
    </style>

    <script>
        let lastMessageId = {{ $messages->last()?->id ?? 0 }};
        const roomSlug = @json($room->slug);
        const csrf = @json(csrf_token());
        const currentUserId = {{ (int) Auth::id() }};
        const isAdmin = {{ (int) ((Auth::user()->is_admin ?? false) ? 1 : 0) }};

        const leftPanel = document.getElementById('left-panel');
        const rightPanel = document.getElementById('right-panel');
        const toggleLeftBtn = document.getElementById('toggle-left');
        const toggleRightBtn = document.getElementById('toggle-right');

        const container  = document.getElementById('message-container');
        const form       = document.getElementById('message-form');
        const textarea   = document.getElementById('body');
        const switcher   = document.getElementById('character-switcher');
        const hiddenChar = document.getElementById('character-id-input');

        const tabRooms   = document.getElementById('tab-rooms');
        const tabUsers   = document.getElementById('tab-users');
        const panelRooms = document.getElementById('panel-rooms');
        const panelUsers = document.getElementById('panel-users');
        const tabMeta    = document.getElementById('tab-meta');
        const userListEl = document.getElementById('user-list');

        function setPanelHidden(panel, key, hidden) {
            if (!panel) return;
            panel.classList.toggle('hidden', hidden);
            sessionStorage.setItem(key, hidden ? '1' : '0');
        }
        function togglePanel(panel, key) {
            if (!panel) return;
            const hidden = panel.classList.contains('hidden');
            setPanelHidden(panel, key, !hidden);
        }
        setPanelHidden(leftPanel, 'hide_left_panel', sessionStorage.getItem('hide_left_panel') === '1');
        setPanelHidden(rightPanel, 'hide_right_panel', sessionStorage.getItem('hide_right_panel') === '1');
        if (toggleLeftBtn) toggleLeftBtn.addEventListener('click', () => togglePanel(leftPanel, 'hide_left_panel'));
        if (toggleRightBtn) toggleRightBtn.addEventListener('click', () => togglePanel(rightPanel, 'hide_right_panel'));

        function shortSigil(id) {
            return Math.abs(id * 2654435761 % 0xFFFFFFFF)
                .toString(16)
                .toUpperCase()
                .slice(0, 4);
        }

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
            try { s = JSON.parse(el.dataset.style || '{}'); } catch (e) { s = {}; }
            const stops = buildStops(s);
            const shouldFade = !!s.fade && stops.length >= 2;
            if (shouldFade) applyGradientText(el, stops);
            else applySolidText(el, s.c1);
        }
        function applyStylesIn(root) {
            (root || document).querySelectorAll('.msg-name, .msg-body').forEach(applyStyleFromDataset);
        }
        applyStylesIn(document);

        function getTabCharacterId() {
            const v = sessionStorage.getItem('active_character_id');
            return v ? parseInt(v, 10) : 0;
        }
        function setTabCharacterId(id) {
            sessionStorage.setItem('active_character_id', String(id));
            if (hiddenChar) hiddenChar.value = String(id);
        }

        (function initActiveCharacterPerTab() {
            if (!switcher) return;
            const stored = getTabCharacterId();
            if (stored) {
                switcher.value = String(stored);
                setTabCharacterId(stored);
            } else {
                const initial = parseInt(switcher.value, 10);
                setTabCharacterId(initial);
            }
        })();

        if (switcher) {
            switcher.addEventListener('change', function () {
                const newId = parseInt(this.value, 10);
                if (!newId) return;
                setTabCharacterId(newId);
                sendPresencePing();
            });
        }

        function showRoomsTab() {
            if (!tabRooms || !tabUsers || !panelRooms || !panelUsers) return;
            tabRooms.className = 'px-2 py-1 rounded bg-gray-800';
            tabUsers.className = 'px-2 py-1 rounded hover:bg-gray-800 text-gray-200';
            panelRooms.classList.remove('hidden');
            panelUsers.classList.add('hidden');
            if (tabMeta) tabMeta.textContent = '# active / name';
        }
        function showUsersTab() {
            if (!tabRooms || !tabUsers || !panelRooms || !panelUsers) return;
            tabUsers.className = 'px-2 py-1 rounded bg-gray-800';
            tabRooms.className = 'px-2 py-1 rounded hover:bg-gray-800 text-gray-200';
            panelUsers.classList.remove('hidden');
            panelRooms.classList.add('hidden');
            if (tabMeta) tabMeta.textContent = 'character / user';
            refreshUserList();
        }
        if (tabRooms) tabRooms.addEventListener('click', showRoomsTab);
        if (tabUsers) tabUsers.addEventListener('click', showUsersTab);
        showRoomsTab();

        function refreshUserList() {
            if (!userListEl) return;

            fetch(`/rooms/${roomSlug}/roster`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    const roster = Array.isArray(data.roster) ? data.roster : [];
                    userListEl.innerHTML = '';

                    if (roster.length === 0) {
                        userListEl.innerHTML = `<div class="text-gray-500">Nobody here.</div>`;
                        return;
                    }

                    roster.forEach(p => {
                        const sigil = shortSigil(p.character_id);

                        let s = {};
                        try {
                            s = typeof p.settings === 'string'
                                ? JSON.parse(p.settings)
                                : (p.settings || {});
                        } catch {
                            s = {};
                        }

                        const c1 = s.text_color_1 || '#D8F3FF';
                        const c2 = s.text_color_2 || null;
                        const c3 = s.text_color_3 || null;
                        const c4 = s.text_color_4 || null;
                        const fadeName = !!s.fade_name;

                        const row = document.createElement('div');
                        row.className = 'char-row border-b border-gray-800 pb-2';

                        row.innerHTML = `
                            <a href="/characters/${p.character_id}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="msg-name text-sm md:text-base font-medium hover:underline"
                               data-style='${JSON.stringify({ c1, c2, c3, c4, fade: fadeName })}'>
                               ${p.character_name}
                            </a>

                            <div class="char-card text-xs text-gray-200">
                                <div class="font-semibold">${p.character_name} ⟡${sigil}</div>
                                <div class="text-[10px] text-gray-400">ID verification</div>
                            </div>
                        `;

                        userListEl.appendChild(row);
                    });

                    applyStylesIn(userListEl);
                })
                .catch(() => {});
        }

        setInterval(() => {
            if (panelUsers && !panelUsers.classList.contains('hidden')) refreshUserList();
        }, 5000);

        if (container) container.scrollTop = container.scrollHeight;

        function canEditMessage(msg) {
            return (msg.user_id && (parseInt(msg.user_id, 10) === currentUserId)) || !!isAdmin;
        }

        function attachMessageActions(root) {
            (root || document).querySelectorAll('.msg-row').forEach(row => {
                if (row.dataset.bound === '1') return;
                row.dataset.bound = '1';

                const editBtn = row.querySelector('.msg-edit-btn');
                const delBtn  = row.querySelector('.msg-del-btn');
                const bodyEl  = row.querySelector('.msg-body');
                const editBox = row.querySelector('.msg-editbox');
                const ta      = row.querySelector('.msg-edit-textarea');
                const saveBtn = row.querySelector('.msg-save-btn');
                const cancelBtn = row.querySelector('.msg-cancel-btn');
                const editedTag = row.querySelector('.msg-edited');
                const deletedTag = row.querySelector('.msg-deleted');

                const id = row.dataset.messageId;

                if (editBtn) {
                    editBtn.addEventListener('click', () => {
                        if (!bodyEl || !editBox || !ta) return;
                        const currentText = bodyEl.textContent;
                        ta.value = currentText.trim();
                        editBox.classList.remove('hidden');
                        editBtn.disabled = true;
                        if (delBtn) delBtn.disabled = true;
                    });
                }

                if (cancelBtn) {
                    cancelBtn.addEventListener('click', () => {
                        if (!editBox) return;
                        editBox.classList.add('hidden');
                        if (editBtn) editBtn.disabled = false;
                        if (delBtn) delBtn.disabled = false;
                    });
                }

                if (saveBtn) {
                    saveBtn.addEventListener('click', () => {
                        if (!ta || !bodyEl) return;
                        const newBody = ta.value;

                        fetch(`/messages/${id}`, {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ body: newBody }),
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (!data || !data.ok) return;

                            bodyEl.textContent = data.message?.body ?? newBody;
                            if (editedTag) editedTag.classList.remove('hidden');

                            if (editBox) editBox.classList.add('hidden');
                            if (editBtn) editBtn.disabled = false;
                            if (delBtn) delBtn.disabled = false;
                        })
                        .catch(() => {});
                    });
                }

                if (delBtn) {
                    delBtn.addEventListener('click', () => {
                        if (!confirm('Delete this message?')) return;

                        fetch(`/messages/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            credentials: 'same-origin',
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (!data || !data.ok) return;

                            if (bodyEl) bodyEl.textContent = '[deleted]';
                            if (deletedTag) deletedTag.classList.remove('hidden');

                            if (editBtn) editBtn.disabled = true;
                            if (delBtn) delBtn.disabled = true;

                            if (editBox) editBox.classList.add('hidden');
                        })
                        .catch(() => {});
                    });
                }
            });
        }

        attachMessageActions(document);

        function fetchNewMessages() {
            fetch(`/rooms/${roomSlug}/messages/latest?after=` + lastMessageId)
                .then(r => r.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) return;
                    if (!container) return;

                    const wasNearBottom =
                        container.scrollHeight - container.scrollTop - container.clientHeight < 80;

                    data.forEach(msg => {
                        const name = (msg.character && msg.character.name) ? msg.character.name : msg.user.name;

                        const s = msg.character?.settings || {};
                        const c1 = s.text_color_1 || '#D8F3FF';
                        const c2 = s.text_color_2 || null;
                        const c3 = s.text_color_3 || null;
                        const c4 = s.text_color_4 || null;

                        const fadeMsg = !!s.fade_message;
                        const fadeName = !!s.fade_name;

                        const canEdit = canEditMessage(msg);

                        const div = document.createElement('div');
                        div.className = "border-b border-gray-800 py-1.5 msg-row";
                        div.dataset.messageId = String(msg.id);
                        div.dataset.userId = String(msg.user_id ?? 0);
                        div.dataset.canEdit = canEdit ? '1' : '0';

                        div.innerHTML = `
                            <div class="flex items-start justify-between gap-2 leading-tight mb-0">
                                <div class="flex items-start gap-2">
                                    <span class="msg-name text-sm md:text-base font-medium" data-style='${JSON.stringify({c1,c2,c3,c4,fade:fadeName})}'>${name}</span>
                                    <span class="text-[10px] text-gray-500 opacity-70">${msg.created_at_human ?? ''}</span>
                                    <span class="msg-edited text-[10px] text-gray-500 opacity-70 hidden">(edited)</span>
                                    <span class="msg-deleted text-[10px] text-gray-500 opacity-70 ${msg.deleted_at ? '' : 'hidden'}">(deleted)</span>
                                </div>

                                ${canEdit ? `
                                    <div class="flex items-center gap-2 text-[10px]">
                                        <button type="button" class="msg-edit-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700" ${msg.deleted_at ? 'disabled' : ''}>Edit</button>
                                        <button type="button" class="msg-del-btn rounded border border-gray-700 bg-gray-800 px-2 py-1 text-gray-100 hover:bg-gray-700" ${msg.deleted_at ? 'disabled' : ''}>Delete</button>
                                    </div>
                                ` : ''}
                            </div>

                            <div class="text-sm md:text-base text-gray-100 whitespace-pre-line leading-snug -mt-5">
                                <span class="msg-body" data-style='${JSON.stringify({c1,c2,c3,c4,fade:fadeMsg})}'>${msg.deleted_at ? '[deleted]' : (msg.content ?? msg.body ?? '')}</span>

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
                        attachMessageActions(div);

                        lastMessageId = msg.id;
                    });

                    if (wasNearBottom) container.scrollTop = container.scrollHeight;
                })
                .catch(() => {});
        }
        setInterval(fetchNewMessages, 2500);

        function sendPresencePing() {
            const characterId = getTabCharacterId();
            if (!characterId) return;

            fetch(`/rooms/${roomSlug}/presence`, {
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

        sendPresencePing();
        setInterval(sendPresencePing, 30000);

        if (form) {
            form.addEventListener('submit', function () {
                const id = getTabCharacterId();
                if (hiddenChar) hiddenChar.value = String(id);
            });
        }

        if (textarea && form) {
            textarea.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const id = getTabCharacterId();
                    if (hiddenChar) hiddenChar.value = String(id);
                    form.requestSubmit();
                }
            });
        }

        const leaveBtn = document.getElementById('leave-room-btn');
        if (leaveBtn) {
            leaveBtn.addEventListener('click', () => {
                leaveRoom().finally(() => window.location.href = '/rooms');
            });
        }

        window.addEventListener('beforeunload', () => leaveRoom());
    </script>
</x-app-layout>
