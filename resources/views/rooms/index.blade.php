<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Rooms
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Create room form --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        Create a room
                    </h3>

                    <form method="POST" action="{{ route('rooms.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-300">Name</label>
                            <input id="name" name="name" type="text" required maxlength="100"
                                   class="mt-1 block w-full rounded-md border-gray-600 bg-gray-900 text-sm text-gray-100 focus:border-teal-500 focus:ring-teal-500"
                                   placeholder="GOOC, Wormwood Tavern, etc.">
                            @error('name')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-300">Description</label>
                            <textarea id="description" name="description" rows="2"
                                      class="mt-1 block w-full rounded-md border-gray-600 bg-gray-900 text-sm text-gray-100 focus:border-teal-500 focus:ring-teal-500"
                                      placeholder="Short blurb about the room."></textarea>
                            @error('description')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                            Create room
                        </button>
                    </form>
                </div>
            </div>

            {{-- Room list --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        Available rooms
                    </h3>

                    @if ($rooms->isEmpty())
                        <p class="text-sm text-gray-400">No rooms yet. Be the first to create one.</p>
                    @else
                        <ul class="divide-y divide-gray-700">
                            @foreach ($rooms as $room)
                                <li class="py-3 flex items-center justify-between">
                                    <div>
                                        <a href="{{ route('rooms.show', $room->slug) }}"
                                           class="text-sm font-semibold text-teal-400 hover:text-teal-200">
                                            {{ $room->name }}
                                        </a>
                                        @if ($room->description)
                                            <div class="text-xs text-gray-400">
                                                {{ $room->description }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        by {{ $room->owner->name }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
