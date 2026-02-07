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
                        @endphp

                        <div class="border-b border-gray-800 py-1.5">
                            <span class="msg-name font-medium text-gray-200" data-style='{!! $nameStyleJson !!}'>
                                {{ $name }}
                            </span>
                            <span class="text-[10px] text-gray-500 ml-2">
                                {{ $message->created_at->diffForHumans() }}
                            </span>

                            <div class="msg-body text-gray-100 whitespace-pre-line" data-style='{!! $bodyStyleJson !!}'>
                                {{ $message->content }}
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Send --}}
                <div class="border-t border-gray-800 p-3">
                    <form method="POST" action="{{ route('rooms.messages.store', $room) }}" id="message-form">
                        @csrf
                        <input type="hidden" name="character_id" id="character-id-input" value="{{ $activeCharacterId }}">

                        <textarea
                            id="body"
                            name="content"
                            rows="3"
                            required
                            placeholder="Enter to send. Shift+Enter for newline."
                            class="mt-1 block w-full rounded-md border-gray-700 bg-gray-950 text-gray-100"
                        >{{ old('content') }}</textarea>

                        @error('content')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror

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

                <div class="px-3 py-2 border-b border-gray-800 text-xs font-semibold text-green-400 flex gap-2">

                    <button id="tab-rooms" type="button" class="px-2 py-1 rounded bg-gray-800">Rooms</button>
                    <button id="tab-users" type="button" class="px-2 py-1 rounded hover:bg-gray-800">Users</button>
                    <button id="tab-dms" type="button" class="px-2 py-1 rounded hover:bg-gray-800">DMs</button>

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

                    <div id="panel-users" class="hidden p-3">
                        Loading...
                    </div>

                    <div id="panel-dms" class="hidden p-3">
                        No conversations yet.
                    </div>

                </div>
            </div>

        </div>
    </div>

<script>
const container = document.getElementById('message-container');
const form = document.getElementById('message-form');
const textarea = document.getElementById('body');

let lastMessageId = {{ $messages->last()?->id ?? 0 }};
const roomSlug = @json($room->slug);
const csrf = @json(csrf_token());

/* ---------------------------
   PANEL TOGGLES
---------------------------- */
const leftPanel  = document.getElementById('left-panel');
const rightPanel = document.getElementById('right-panel');

document.getElementById('toggle-left')?.addEventListener('click', () => {
    leftPanel?.classList.toggle('hidden');
});

document.getElementById('toggle-right')?.addEventListener('click', () => {
    rightPanel?.classList.toggle('hidden');
});

/* ---------------------------
   GRADIENT / FADE STYLES
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
   AUTO SCROLL ON SEND
---------------------------- */
if (form) {
    form.addEventListener('submit', () => {
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 50);
    });
}

/* ---------------------------
   ENTER TO SEND
---------------------------- */
textarea?.addEventListener('keydown', function(e){
    if(e.key === 'Enter' && !e.shiftKey){
        e.preventDefault();
        form.requestSubmit();
    }
});

/* ---------------------------
   FETCH NEW MESSAGES
---------------------------- */
function fetchNewMessages() {
    fetch(`/rooms/${roomSlug}/messages/latest?after=` + lastMessageId)
        .then(r => r.json())
        .then(data => {
            if(!Array.isArray(data) || !data.length) return;

            const nearBottom =
                container.scrollHeight - container.scrollTop - container.clientHeight < 80;

            data.forEach(msg => {
                // name
                const name = (msg.character && msg.character.name) ? msg.character.name : (msg.user?.name ?? 'Unknown');

                // settings for fade
                let s = msg.character?.settings || {};
                if (typeof s === 'string') { try { s = JSON.parse(s); } catch(e) { s = {}; } }

                const c1 = s.text_color_1 || '#D8F3FF';
                const c2 = s.text_color_2 || null;
                const c3 = s.text_color_3 || null;
                const c4 = s.text_color_4 || null;

                const fadeMsg = !!s.fade_message;
                const fadeName = !!s.fade_name;

                const div = document.createElement('div');
                div.className = "border-b border-gray-800 py-1.5";

                div.innerHTML = `
                    <span class="msg-name font-medium text-gray-200"
                        data-style='${JSON.stringify({c1,c2,c3,c4,fade:fadeName})}'>${name}</span>
                    <div class="msg-body text-gray-100 whitespace-pre-line"
                        data-style='${JSON.stringify({c1,c2,c3,c4,fade:fadeMsg})}'>${msg.content ?? ''}</div>
                `;

                container.appendChild(div);
                applyStylesIn(div);

                lastMessageId = msg.id;
            });

            if(nearBottom){
                container.scrollTop = container.scrollHeight;
            }
        })
        .catch(() => {});
}

/* slowed from 2500 */
setInterval(fetchNewMessages, 4000);

/* ---------------------------
   TABS
---------------------------- */
const tabRooms = document.getElementById('tab-rooms');
const tabUsers = document.getElementById('tab-users');
const tabDms   = document.getElementById('tab-dms');

const panelRooms = document.getElementById('panel-rooms');
const panelUsers = document.getElementById('panel-users');
const panelDms   = document.getElementById('panel-dms');

function showRoomsTab(){
    panelRooms.classList.remove('hidden');
    panelUsers.classList.add('hidden');
    panelDms.classList.add('hidden');
}

function showUsersTab(){
    panelRooms.classList.add('hidden');
    panelUsers.classList.remove('hidden');
    panelDms.classList.add('hidden');
}

function showDmsTab(){
    panelRooms.classList.add('hidden');
    panelUsers.classList.add('hidden');
    panelDms.classList.remove('hidden');
}

tabRooms?.addEventListener('click', showRoomsTab);
tabUsers?.addEventListener('click', showUsersTab);
tabDms?.addEventListener('click', showDmsTab);

showRoomsTab();
</script>

</x-app-layout>
