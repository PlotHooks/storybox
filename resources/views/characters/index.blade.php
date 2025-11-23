{{-- resources/views/characters/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Your Characters
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if (session('status'))
                        <div class="mb-4 rounded border border-green-500 bg-green-100/70 px-4 py-2 text-sm text-green-900">
                            {{ session('status') }}
                        </div>
                    @endif

                    <h3 class="text-lg font-bold mb-4">
                        Active character:
                    </h3>

                    @php
                        $activeId = $activeId ?? session('active_character_id');
                        $active = $characters->firstWhere('id', $activeId);
                    @endphp

                    @if ($active)
                        <div class="mb-6 rounded border border-teal-500 bg-teal-900/40 px-4 py-3">
                            <div class="text-sm uppercase tracking-wide text-teal-200">
                                Currently playing as
                            </div>
                            <div class="mt-1 text-2xl font-bold text-teal-100">
                                {{ $active->name }}
                            </div>
                        </div>
                    @else
                        <p class="mb-6 text-sm text-gray-400">
                            No active character selected yet.
                        </p>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {{-- Character list --}}
                        <div class="md:col-span-2">
                            <h3 class="text-lg font-bold mb-3">
                                Your roster
                            </h3>

                            @if ($characters->isEmpty())
                                <p class="text-sm text-gray-400">
                                    You do not have any characters yet. Use the form on the right to create one.
                                </p>
                            @else
                                <ul class="space-y-2">
                                    @foreach ($characters as $character)
                                        @php
                                            $isActive = $activeId && $activeId === $character->id;
                                        @endphp
                                        <li
                                            class="flex items-center justify-between rounded border px-3 py-2 text-sm
                                                   {{ $isActive ? 'border-teal-500 bg-teal-900/40' : 'border-gray-700 bg-gray-900/40' }}">
                                            <div>
                                                <div class="font-semibold">
                                                    {{ $character->name }}
                                                </div>
                                                <div class="text-xs text-gray-400">
                                                    ID #{{ $character->id }}
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                @if ($isActive)
                                                    <span class="rounded-full bg-teal-600 px-3 py-1 text-xs font-semibold text-white">
                                                        Active
                                                    </span>
                                                @else
                                                    <form method="POST"
                                                          action="{{ route('characters.switch', $character) }}">
                                                        @csrf
                                                        <button
                                                            type="submit"
                                                            class="rounded bg-teal-600 px-3 py-1 text-xs font-semibold text-white hover:bg-teal-500">
                                                            Switch
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        {{-- New character form --}}
                        <div>
                            <h3 class="text-lg font-bold mb-3">
                                New character
                            </h3>

                            <form method="POST" action="{{ route('characters.store') }}" class="space-y-3">
                                @csrf

                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-300">
                                        Name
                                    </label>
                                    <input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        maxlength="100"
                                        class="mt-1 block w-full rounded-md border-gray-600 bg-gray-900 text-sm text-gray-100 focus:border-teal-500 focus:ring-teal-500"
                                        placeholder="Red Woods, Plot Hooks, etc."
                                    >
                                    @error('name')
                                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <button
                                    type="submit"
                                    class="w-full rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                    Create character
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
