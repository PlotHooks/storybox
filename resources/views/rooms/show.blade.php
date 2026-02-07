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

                            <button id="toggle-left"
                                class="rounded border border-gray-700 bg-gray-800 text-xs text-gray-100 px-2 py-1 hover:bg-gray-700">
                                Toggle Left
                            </button>

                            <button id="toggle-right"
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

                            <button id="leave-room-btn"
                                class="rounded border border-gray-700 bg-gray-800 text-xs text-gray-100 px-2 py-1 hover:bg-gray-700">
                                Leave room
                            </button>

                        </div>
                    @endif
                </div>

                {{-- Messages --}}
                <div id="message-container" class="flex-1 overflow-y-auto px-4 py-2">
                    @foreach ($messages as $message)
                        <div class="border-b border-gray-800 py-1.5">
                            <span class="font-medium text-gray-200">
                                {{ optional($message->character)->name ?? $message->user->name }}
                            </span>
                            <span class="text-[10px] text-gray-500 ml-2">
                                {{ $message->created_at->diffForHumans() }}
                            </span>

                            <div class="text-gray-100 whitespace-pre-line">
                                {{ $message->body }}
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Send --}}
                <div class="border-t border-gray-800 p-3">
                    <form method="POST" action="{{ route('rooms.messages.store', $room) }}" id="message-form">
                        @csrf
                        <input type="hidden" name="character_id" id="character-id-input">

                        <textarea
                            id="body"
                            name="body"
                            rows="3"
                            required
                            placeholder="Enter to send. Shift+Enter for newline."
                            class="mt-1 block w-full rounded-md border-gray-700 bg-gray-950 text-gray-100"
                        ></textarea>

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

                    <button id="tab-rooms" class="px-2 py-1 rounded bg-gray-800">Rooms</button>
                    <button id="tab-users" class="px-2 py-1 rounded hover:bg-gray-800">Users</button>
                    <button id="tab-dms" class="px-2 py-1 rounded hover:bg-gray-800">DMs</button>

                </div>

                <div class="flex-1 overflow-y-auto text-xs">

                    <div id="panel-rooms">
                        @foreach ($sidebarRooms as $r)
                            <button
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

                const div = document.createElement('div');

                div.className = "border-b border-gray-800 py-1.5";

                div.innerHTML = `
                    <span class="font-medium text-gray-200">${msg.user.name}</span>
                    <div class="text-gray-100 whitespace-pre-line">${msg.body}</div>
                `;

                container.appendChild(div);

                lastMessageId = msg.id;

            });

            if(nearBottom){
                container.scrollTop = container.scrollHeight;
            }

        });

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
