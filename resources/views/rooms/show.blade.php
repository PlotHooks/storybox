<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $room->name }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

                {{-- LEFT COLUMN: placeholder for Global OOC / system feed --}}
                <div class="hidden lg:flex lg:flex-col lg:col-span-3 bg-gray-900 text-gray-200 shadow sm:rounded-lg"
                     style="height: 70vh;">
                    <div class="px-3 py-2 border-b border-gray-700 text-xs font-semibold uppercase tracking-wide">
                        Global OOC (coming soon)
                    </div>
                    <div class="flex-1 overflow-y-auto px-3 py-2 text-xs text-gray-400 space-y-1">
                        <p>This panel will eventually be a permanent GOOC / system feed.</p>
                        <p>For now, it’s just a placeholder so we can shape the layout.</p>
                    </div>
                </div>

                {{-- CENTER COLUMN: actual room chat --}}
                <div class="lg:col-span-6 space-y-4">

                    {{-- Top bar: owner + character switcher --}}
                    <div class="flex items-center justify-between bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4">
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            Room owner:
                            <span class="font-semibold">
                                {{ optional($room->owner)->name ?? 'Unknown' }}
                            </span>
                        </div>

                        @php
                            $characters = Auth::user()->characters;
                        @endphp

                        @if ($characters->count() > 0)
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-600 dark:text-gray-300">
                                    Posting as:
                                </span>
                                <select id="character-switcher"
                                        class="rounded border-gray-600 bg-gray-900 text-sm text-gray-100">
                                    @foreach ($characters as $char)
                                        <option value="{{ $char->id }}"
                                            {{ $char->id == $activeCharacterId ? 'selected' : '' }}>
                                            {{ $char->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="text-xs text-red-400">
                                You need at least one character to post.
                            </div>
                        @endif
                    </div>

                    {{-- Chat box: fixed height, messages scroll, input fixed at bottom --}}
                    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg flex flex-col"
                         style="height: 70vh;">

                        {{-- Messages list --}}
                        <div id="message-container"
                             class="flex-1 overflow-y-auto p-4 space-y-3 border-b border-gray-200 dark:border-gray-700">
                            @foreach ($messages as $message)
                                <div class="border-b border-gray-200 dark:border-gray-700 pb-2 mb-2">
                                    <div class="text-xs text-gray-500">
                                        {{ optional($message->character)->name ?? $message->user->name }}
                                        · {{ $message->created_at->diffForHumans() }}
                                    </div>
                                    <div class="text-gray-800 dark:text-gray-100 whitespace-pre-line">
                                        {{ $message->body }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- New message form --}}
                        <div class="p-4">
                            <form method="POST" action="{{ route('rooms.messages.store', $room) }}" id="message-form">
                                @csrf

                                <div>
                                    <label for="body" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                        New message
                                    </label>
                                    <textarea
                                        id="body"
                                        name="body"
                                        rows="3"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                                               focus:border-indigo-500 focus:ring-indigo-500
                                               dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100"
                                    >{{ old('body') }}</textarea>

                                    @error('body')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mt-3 flex justify-end">
                                    <x-primary-button id="send-button">
                                        Send
                                    </x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- RIGHT COLUMN: placeholder for room list / user list --}}
                <div class="hidden lg:flex lg:flex-col lg:col-span-3 bg-gray-900 text-gray-200 shadow sm:rounded-lg"
                     style="height: 70vh;">
                    <div class="px-3 py-2 border-b border-gray-700 text-xs font-semibold uppercase tracking-wide">
                        Rooms / Users (coming soon)
                    </div>
                    <div class="flex-1 overflow-y-auto px-3 py-2 text-xs text-gray-400 space-y-1">
                        <p>This will be the room list and/or user list panel later.</p>
                        <p>For now it’s just here to match the three-column chat layout you’re aiming for.</p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Initial last id and room slug from server
        let lastMessageId = {{ $messages->last()?->id ?? 0 }};
        const roomSlug = @json($room->slug);
        const container = document.getElementById('message-container');

        // On first load, scroll to bottom of message area
        if (container) {
            container.scrollTop = container.scrollHeight;
        }

        function fetchNewMessages() {
            fetch(`/rooms/${roomSlug}/messages/latest?after=` + lastMessageId)
                .then(r => r.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0 || !container) return;

                    const wasNearBottom =
                        container.scrollHeight - container.scrollTop - container.clientHeight < 80;

                    data.forEach(msg => {
                        const div = document.createElement('div');
                        div.className = "border-b border-gray-200 dark:border-gray-700 pb-2 mb-2";
                        div.innerHTML = `
                            <div class="text-xs text-gray-500">
                                ${(msg.character && msg.character.name) ? msg.character.name : msg.user.name}
                            </div>
                            <div class="text-gray-800 dark:text-gray-100 whitespace-pre-line">
                                ${msg.content ?? msg.body}
                            </div>
                        `;
                        container.appendChild(div);
                        lastMessageId = msg.id;
                    });

                    if (wasNearBottom) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
        }

        setInterval(fetchNewMessages, 2500);

        // Character switching
        const switcher = document.getElementById('character-switcher');

        if (switcher) {
            switcher.addEventListener('change', function () {
                const charId = this.value;

                fetch(`/characters/${charId}/switch`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                })
                .then(() => console.log('Switched to character ' + charId))
                .catch(() => alert('Could not switch character.'));
            });
        }

        // Enter to send, Shift+Enter for newline
        const form = document.getElementById('message-form');
        const textarea = document.getElementById('body');

        if (textarea && form) {
            textarea.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    form.submit();
                }
            });
        }
    </script>
</x-app-layout>
