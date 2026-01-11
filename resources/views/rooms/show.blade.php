<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $room->name }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto h-[calc(100vh-6rem)] flex gap-4 px-4">

            {{-- LEFT COLUMN: reserved for later --}}
            <div class="w-1/4 bg-gray-900 text-gray-100 rounded-lg shadow flex flex-col">
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
                <div id="message-container" class="flex-1 overflow-y-auto p-4 space-y-3">
                    @foreach ($messages as $message)
                        <div class="border-b border-gray-800 pb-2 mb-2">
                            <div class="text-[10px] text-gray-400">
                                {{ optional($message->character)->name ?? $message->user->name }}
                                · {{ $message->created_at->diffForHumans() }}
                            </div>
                            <div class="text-base md:text-lg text-gray-100 whitespace-pre-line leading-relaxed">
                                {{ $message->body }}
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
                                class="mt-1 block w-full rounded-md border-gray-700 bg-gray-950 text-sm text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
            <div class="w-1/5 bg-gray-900 text-gray-100 rounded-lg shadow flex flex-col">
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

        const container  = document.getElementById('message-container');
        const roomListEl = document.getElementById('room-list');
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

        function shortSigil(id) {
         return Math.abs(id * 2654435761 % 0xFFFFFFFF)
        .toString(16)
        .toUpperCase()
        .slice(0, 4);
}

        
        function getTabCharacterId() {
            const v = sessionStorage.getItem('active_character_id');
            return v ? parseInt(v, 10) : 0;
        }

        function setTabCharacterId(id) {
            sessionStorage.setItem('active_character_id', String(id));
            if (hiddenChar) hiddenChar.value = String(id);
        }

        // Initialize per-tab character: use stored value, else use current select
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

        (function redirectToTabCharacterRoomOnLoad() {
    const id = getTabCharacterId();
    if (!id) return;

    fetch(`/characters/${id}/current-room`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
    })
    .then(r => r.json())
    .then(data => {
        const targetSlug = data?.room_slug || null;
        if (targetSlug && targetSlug !== roomSlug) {
            window.location.href = `/rooms/${targetSlug}`;
        }
    })
    .catch(() => {});
})();


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

    fetch(`/rooms/${roomSlug}/roster`, {
        headers: { 'Accept': 'application/json' }
    })
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

                const row = document.createElement('div');
                row.className = 'char-row border-b border-gray-800 pb-2';

                // List shows ONLY the name; card shows name + sigil
                row.innerHTML = `
                    <a href="/characters/${p.character_id}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-gray-100 hover:underline">
                    ${p.character_name}
                    </a>

                    <div class="char-card text-xs text-gray-200">
                        <div class="font-semibold">
                            ${p.character_name} ⟡${sigil}
                        </div>
                        <div class="text-[10px] text-gray-400">
                            ID verification
                        </div>
                    </div>
                `;

                userListEl.appendChild(row);
            });
        })
        .catch(() => {});
}



        setInterval(() => {
            if (panelUsers && !panelUsers.classList.contains('hidden')) {
                refreshUserList();
            }
        }, 5000);

        if (container) container.scrollTop = container.scrollHeight;

        function fetchNewMessages() {
            fetch(`/rooms/${roomSlug}/messages/latest?after=` + lastMessageId)
                .then(r => r.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) return;
                    if (!container) return;

                    const wasNearBottom =
                        container.scrollHeight - container.scrollTop - container.clientHeight < 80;

                    data.forEach(msg => {
                        const div = document.createElement('div');
                        div.className = "border-b border-gray-800 pb-2 mb-2";
                        div.innerHTML = `
                            <div class="text-[10px] text-gray-400">
                                ${(msg.character && msg.character.name) ? msg.character.name : msg.user.name}
                            </div>
                            <div class="text-sm text-gray-100 whitespace-pre-line">
                                ${msg.content ?? msg.body}
                            </div>
                        `;
                        container.appendChild(div);
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

        // character switching is now PER TAB
if (switcher) {
    switcher.addEventListener('change', async function () {
        const newId = parseInt(this.value, 10);

        switcher.disabled = true;

        try {
            // set active character for THIS TAB
            setTabCharacterId(newId);

            // find where the character currently is
            const res = await fetch(`/characters/${newId}/current-room`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            const targetSlug = data?.room_slug || null;

            // if character is in a different room, go there
            if (targetSlug && targetSlug !== roomSlug) {
                window.location.href = `/rooms/${targetSlug}`;
                return;
            }

            // if character is not in any room (or already here), join/refresh presence here
            sendPresencePing();

            if (panelUsers && !panelUsers.classList.contains('hidden')) {
                refreshUserList();
            }
        } finally {
            switcher.disabled = false;
        }
    });
}



        // ensure hidden field is set before submit
        if (form) {
            form.addEventListener('submit', function () {
                const id = getTabCharacterId();
                if (hiddenChar) hiddenChar.value = String(id);
            });
        }

        // enter-to-send
        if (textarea && form) {
            textarea.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    form.submit();
                }
            });
        }

        // presence heartbeat
        sendPresencePing();
        setInterval(sendPresencePing, 30000);

        const leaveBtn = document.getElementById('leave-room-btn');
        if (leaveBtn) {
            leaveBtn.addEventListener('click', () => {
                leaveRoom().finally(() => {
                    window.location.href = '/rooms';
                });
            });
        }

        window.addEventListener('beforeunload', () => {
            leaveRoom();
        });

        function refreshRoomList() {
            if (!roomListEl) return;

            fetch(`{{ route('rooms.sidebar') }}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.rooms) return;

                    roomListEl.innerHTML = '';

                    data.rooms.forEach(r => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.onclick = () => window.location.href = '/rooms/' + r.slug;
                        button.className =
                            'w-full flex items-center justify-between px-3 py-1.5 text-left hover:bg-gray-800 text-xs ' +
                            (r.slug === roomSlug ? 'bg-gray-800 font-semibold text-teal-300' : 'text-gray-200');

                        button.innerHTML = `
                            <span class="w-6 text-[10px] text-gray-400 text-right">${r.active_users ?? 0}</span>
                            <span class="flex-1 truncate ml-1">${r.name}</span>
                        `;

                        roomListEl.appendChild(button);
                    });
                })
                .catch(() => {});
        }

        setInterval(refreshRoomList, 10000);
    </script>
</x-app-layout>
