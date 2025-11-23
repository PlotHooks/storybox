<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $room->name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center justify-between bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 mb-4">
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

            {{-- Messages list --}}
            <div id="message-container" class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 space-y-3">
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
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4">
                <form id="message-form" method="POST" action="{{ route('rooms.messages.store', $room) }}">
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
                        <x-primary-button>
                            Send
                        </x-primary-button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
        let lastMessageId = {{ $messages->last()?->id ?? 0 }};
        const roomSlug = @json($room->slug);

        function fetchNewMessages() {
            fetch(`/rooms/${roomSlug}/messages/latest?after=` + lastMessageId)
                .then(r => r.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) return;

                    const container = document.getElementById('message-container');

                    data.forEach(msg => {
                        const div = document.createElement('div');
                        div.className = "py-2 border-b border-gray-700";
                        div.innerHTML = `
                            <div class="text-sm text-gray-200 font-semibold">
                                ${msg.character?.name ?? msg.user.name}
                            </div>
                            <div class="text-gray-300 text-sm">
                                ${msg.content ?? msg.body}
                            </div>
                        `;
                        container.appendChild(div);

                        lastMessageId = msg.id;
                    });

                    window.scrollTo(0, document.body.scrollHeight);
                });
        }

        // character switching
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
        const messageForm = document.getElementById('message-form');
        const messageBody = document.getElementById('body');

        if (messageForm && messageBody) {
            messageBody.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();

                    if (this.value.trim().length === 0) {
                        return;
                    }

                    messageForm.submit();
                }
            });
        }

        setInterval(fetchNewMessages, 2500); // every 2.5s
    </script>
</x-app-layout>
