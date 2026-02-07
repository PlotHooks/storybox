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

                    @php $characters = Auth::user()->characters; @endphp

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
                            $name = optional($c)->name ?? $message->user->name;

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
                             data-deleted="{{ $isDeleted ? '1' : '0' }}">

                            <div class="flex items-start gap-2">
                                <span class="msg-name font-medium text-gray-200" data-style='{!! $nameStyleJson !!}'>
                                    {{ $name }}
                                </span>

                                <span class="text-[10px] text-gray-500 opacity-70">
                                    {{ $message->created_at->diffForHumans() }}
                                </span>

                                <span class="msg-deleted text-[10px] text-gray-500 opacity-70 {{ $isDeleted ? '' : 'hidden' }}">
                                    (deleted)
                                </span>
                            </div>

                            <div class="msg-body text-gray-100 whitespace-pre-line"
                                 data-style='{!! $bodyStyleJson !!}'>
                                {{ $isDeleted ? '[deleted]' : $text }}
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Send --}}
                <div class="border-t border-gray-800 p-3">
                    <form method="POST" action="{{ route('rooms.messages.store', $room) }}" id="message-form">
                        @csrf

                        {{-- character id --}}
                        <input type="hidden" name="character_id" id="character-id-input" value="{{ $activeCharacterId }}">

                        {{-- We submit BOTH fields so your controller can validate either "body" or "content" --}}
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
                    <button id="tab-dms" type="button" class="px-2 py-1 rounded hover:bg-gray-800">DMs</button>
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

                    <div id="panel-dms" class="hidden px-3 py-2">
                        No conversations yet.
                    </div>

                </div>
            </div>

        </div>
    </div>

<script>
let lastMessageId = {{ $messages->last()?->id ?? 0 }};
const roomSlug = @json($room->slug);
const csrf = @json(csrf_token());
const currentUserId = {{ (int) Auth::id() }};

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
const tabDms     = document.getElementById('tab-dms');

const panelRooms = document.getElementById('panel-rooms');
const panelUsers = document.getElementById('panel-users');
const panelDms   = document.getElementById('panel-dms');

const tabMeta    = document.getElementById('tab-meta');
const userListEl = document.getElementById('user-list');

/* ---------------------------
   LEFT/RIGHT TOGGLES
---------------------------- */
document.getElementById('toggle-left')?.addEventListener('click', () => {
    leftPanel?.classList.toggle('hidden');
});
document.getElementById('toggle-right')?.addEventListener('click', () => {
    rightPanel?.classList.toggle('hidden');
});

/* ---------------------------
   GRADIENT / FADE
---------------------------- */
function buildStops(s){
    const stops = [];
    if (s.c1) stops.push(s.c1);
    if (s.c2) stops.push(s.c2);
    if (s.c3) stops.push(s.c3);
    if (s.c4) stops.push(s.c4);
    return stops.filter(Boolean);
}
function applyGradientText(el, stops){
    el.style.backgroundImage = `linear-gradient(90deg, ${stops.join(',')})`;
    el.style.webkitBackgroundClip = 'text';
    el.style.backgroundClip = 'text';
    el.style.color = 'transparent';
    el.style.display = 'inline-block';
}
function applySolidText(el, color){
    el.style.backgroundImage = '';
    el.style.webkitBackgroundClip = '';
    el.style.backgroundClip = '';
    el.style.color = color || '#D8F3FF';
}
function applyStyleFromDataset(el){
    if (!el) return;
    let s = {};
    try { s = JSON.parse(el.dataset.style || '{}'); } catch(e) { s = {}; }
    const stops = buildStops(s);
    const shouldFade = !!s.fade && stops.length >= 2;
    if (shouldFade) applyGradientText(el, stops);
    else applySolidText(el, s.c1);
}
function applyStylesIn(root){
    (root || document).querySelectorAll('.msg-name, .msg-body').forEach(applyStyleFromDataset);
}
applyStylesIn(document);

/* ---------------------------
   ACTIVE CHARACTER (per tab)
---------------------------- */
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
        setTabCharacterId(parseInt(switcher.value, 10));
    }
})();

switcher?.addEventListener('change', function(){
    const newId = parseInt(this.value, 10);
    if (!newId) return;
    setTabCharacterId(newId);
    sendPresencePing();
});

/* ---------------------------
   PRESENCE PING
---------------------------- */
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
sendPresencePing();
setInterval(sendPresencePing, 30000);

/* ---------------------------
   LEAVE ROOM
---------------------------- */
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
    leaveRoom().finally(() => window.location.href = '/rooms');
});
window.addEventListener('beforeunload', () => leaveRoom());

/* ---------------------------
   ENTER TO SEND + mirror content
---------------------------- */
function syncContentMirror() {
    if (contentMirror && textarea) contentMirror.value = textarea.value;
}
textarea?.addEventListener('input', syncContentMirror);

textarea?.addEventListener('keydown', function(e){
    if(e.key === 'Enter' && !e.shiftKey){
        e.preventDefault();
        syncContentMirror();
        form.requestSubmit();
    }
});

form?.addEventListener('submit', function(){
    // make sure both fields are set
    syncContentMirror();
    const id = getTabCharacterId();
    if (hiddenChar) hiddenChar.value = String(id);
});

/* ---------------------------
   FETCH NEW MESSAGES
   - respects deleted_at
   - respects character name + styles
---------------------------- */
function fetchNewMessages() {
    fetch(`/rooms/${roomSlug}/messages/latest?after=` + lastMessageId, {
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

            const isDeleted = !!msg.deleted_at;
            const text = isDeleted ? '[deleted]' : (msg.content ?? msg.body ?? '');

            const div = document.createElement('div');
            div.className = "border-b border-gray-800 py-1.5 msg-row";
            div.dataset.messageId = String(msg.id);
            div.dataset.deleted = isDeleted ? '1' : '0';

            div.innerHTML = `
                <div class="flex items-start gap-2">
                    <span class="msg-name font-medium text-gray-200"
                        data-style='${JSON.stringify({c1,c2,c3,c4,fade:fadeName})}'>${name}</span>
                    <span class="text-[10px] text-gray-500 opacity-70">${msg.created_at_human ?? ''}</span>
                    <span class="msg-deleted text-[10px] text-gray-500 opacity-70 ${isDeleted ? '' : 'hidden'}">(deleted)</span>
                </div>
                <div class="msg-body text-gray-100 whitespace-pre-line"
                    data-style='${JSON.stringify({c1,c2,c3,c4,fade:fadeMsg})}'>${text}</div>
            `;

            container.appendChild(div);
            applyStylesIn(div);
            lastMessageId = msg.id;
        });

        if (wasNearBottom) container.scrollTop = container.scrollHeight;
    })
    .catch((e) => {
        console.error('fetchNewMessages error', e);
    });
}
setInterval(fetchNewMessages, 2500);

/* ---------------------------
   USERS TAB (ROSTER)
   - accepts {roster:[...]} OR direct [...]
---------------------------- */
function refreshUserList() {
    if (!userListEl) return;

    fetch(`/rooms/${roomSlug}/roster`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    .then(r => {
        if (!r.ok) throw new Error('Roster HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        const roster = Array.isArray(data) ? data : (Array.isArray(data.roster) ? data.roster : []);
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

            const row = document.createElement('div');
            row.className = 'border-b border-gray-800 pb-2';

            row.innerHTML = `
                <a href="/characters/${p.character_id}" target="_blank" rel="noopener noreferrer"
                   class="msg-name text-sm font-medium hover:underline"
                   data-style='${JSON.stringify({c1,c2,c3,c4,fade:fadeName})}'>
                   ${p.character_name ?? ('#' + p.character_id)}
                </a>
                <div class="text-[10px] text-gray-500">${p.user_name ?? ''}</div>
            `;

            userListEl.appendChild(row);
        });

        applyStylesIn(userListEl);
    })
    .catch((e) => {
        console.error('refreshUserList error', e);
        userListEl.innerHTML = `<div class="text-red-400">Roster error. Check console.</div>`;
    });
}

/* ---------------------------
   TABS
---------------------------- */
function showRoomsTab(){
    tabRooms.className = 'px-2 py-1 rounded bg-gray-800';
    tabUsers.className = 'px-2 py-1 rounded hover:bg-gray-800';
    tabDms.className   = 'px-2 py-1 rounded hover:bg-gray-800';

    panelRooms.classList.remove('hidden');
    panelUsers.classList.add('hidden');
    panelDms.classList.add('hidden');

    if (tabMeta) tabMeta.textContent = '# active / name';
}
function showUsersTab(){
    tabUsers.className = 'px-2 py-1 rounded bg-gray-800';
    tabRooms.className = 'px-2 py-1 rounded hover:bg-gray-800';
    tabDms.className   = 'px-2 py-1 rounded hover:bg-gray-800';

    panelRooms.classList.add('hidden');
    panelUsers.classList.remove('hidden');
    panelDms.classList.add('hidden');

    if (tabMeta) tabMeta.textContent = 'character / user';
    refreshUserList();
}
function showDmsTab(){
    tabDms.className   = 'px-2 py-1 rounded bg-gray-800';
    tabRooms.className = 'px-2 py-1 rounded hover:bg-gray-800';
    tabUsers.className = 'px-2 py-1 rounded hover:bg-gray-800';

    panelRooms.classList.add('hidden');
    panelUsers.classList.add('hidden');
    panelDms.classList.remove('hidden');

    if (tabMeta) tabMeta.textContent = 'direct messages';
}

tabRooms?.addEventListener('click', showRoomsTab);
tabUsers?.addEventListener('click', showUsersTab);
tabDms?.addEventListener('click', showDmsTab);
showRoomsTab();

setInterval(() => {
    if (panelUsers && !panelUsers.classList.contains('hidden')) refreshUserList();
}, 5000);

/* ---------------------------
   INITIAL SCROLL
---------------------------- */
if (container) container.scrollTop = container.scrollHeight;
</script>

</x-app-layout>
