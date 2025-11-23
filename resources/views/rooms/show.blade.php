<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $room->name }}
            </h2>
            <a href="{{ route('rooms.index') }}"
               class="text-xs text-teal-400 hover:text-teal-200">
                ← Back to rooms
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if ($room->description)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="p-4 text-sm text-gray-300">
                        {{ $room->description }}
                    </div>
                </div>
            @endif

            {{-- Messages --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="p-4 h-[480px] overflow-y-auto space-y-3">
                    @forelse ($messages as $message)
                        <div class="text-sm">
                            <div class="flex items-baseline space-x-2">
                                <span class="font-semibold text-teal-300">
                                    {{ optional($message->character)->name ?? $message->user->name }}
                                </span>
                                <span class="text-[11px] text-gray-500">
                                    {{ $message->created_at->format('Y-m-d H:i') }}
                                </span>
                            </div>
                            <div class="text-gray-100">
                                {!! nl2br(e($message->content)) !!}
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">
                            No messages yet. Say something and break the silence.
                        </p>
                    @endforelse
                </div>
            </div>

            {{-- Post form --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="p-4">
                    @if (! $activeCharacterId)
                        <div class="mb-3 rounded border border-yellow-500 bg-yellow-900/40 px-3 py-2 text-xs text-yellow-100">
                            You don’t have an active character selected.  
                            Use the character dropdown in the top bar to pick one before posting.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('rooms.messages.store', $room->slug) }}" class="space-y-3">
                        @csrf

                        <div>
                            <label for="content" class="block text-xs font-medium text-gray-300">
                                Post as {{ $activeCharacterId ? optional(auth()->user()->characters->firstWhere('id', $activeCharacterId))->name : '—' }}
                            </label>
                            <textarea id="content" name="content" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-600 bg-gray-900 text-sm text-gray-100 focus:border-teal-500 focus:ring-teal-500"
                                      @if(! $activeCharacterId) disabled @endif
                                      placeholder="Type your message here..."></textarea>
                            @error('content')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                @if (! $activeCharacterId) disabled @endif
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed">
                            Send
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
