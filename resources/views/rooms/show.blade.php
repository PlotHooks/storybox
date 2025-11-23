<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $room->name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Messages list --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 space-y-3">
                @forelse ($room->messages as $message)
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-2 mb-2">
                        <div class="text-xs text-gray-500">
                            {{ optional($message->character)->name ?? $message->user->name }}
                            · {{ $message->created_at->diffForHumans() }}
                        </div>
                        <div class="text-gray-800 dark:text-gray-100 whitespace-pre-line">
                            {{ $message->body }}
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-sm">
                        No messages yet. Say something!
                    </p>
                @endforelse
            </div>

            {{-- New message form --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4">
                <form method="POST" action="{{ route('rooms.messages.store', $room) }}">
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
</x-app-layout>
