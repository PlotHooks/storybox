<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                {{ $character->name }}
            </h2>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <a href="{{ route('characters.profile.show', $character) }}" target="_blank" rel="noreferrer" class="inline-flex items-center rounded border border-[#5a431f] bg-[#141416] px-3 py-2 text-[#f2dfb5] hover:bg-[#191511]">View Public Profile</a>
                <a href="{{ route('characters.profile.edit', $character) }}" class="inline-flex items-center rounded border border-[#5a431f] bg-[#141416] px-3 py-2 text-[#f2dfb5] hover:bg-[#191511]">Edit Profile</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto bg-gray-900 rounded-lg shadow p-6 text-gray-200">
            <div class="mb-4 flex items-start gap-4">
                @if ($avatar = $character->externalAvatarUrl())
                    <img src="{{ $avatar }}"
                         alt="{{ $character->name }} avatar"
                         loading="lazy"
                         referrerpolicy="no-referrer"
                         class="h-32 w-32 max-h-64 max-w-64 rounded-lg object-cover">
                @else
                    <div class="flex h-32 w-32 max-h-64 max-w-64 items-center justify-center rounded-lg border border-gray-800 bg-gray-950 text-4xl font-semibold text-gray-500">
                        {{ strtoupper(substr($character->name, 0, 1)) }}
                    </div>
                @endif

                <div class="min-w-0">
                    <h3 class="text-lg font-semibold mb-2">Character Page</h3>
                    <div class="text-sm text-gray-400">
                        This owner-only page is still available for character management. Public-facing profile content now lives on the dedicated Character Profile route.
                    </div>
                    <div class="mt-2 text-sm text-amber-300">
                        {{ $character->public_handle }}
                    </div>
                </div>
            </div>

            <div class="space-y-2 text-sm">
                <div><span class="text-gray-500">Name:</span> <span class="text-gray-100">{{ $character->name }}</span></div>
                <div><span class="text-gray-500">Public Handle:</span> <span class="text-gray-100">{{ $character->public_handle }}</span></div>
                <div><span class="text-gray-500">Owner user ID:</span> <span class="text-gray-100">{{ $character->user_id }}</span></div>
                <div><span class="text-gray-500">Public Profile:</span> <a href="{{ route('characters.profile.show', $character) }}" target="_blank" rel="noreferrer" class="text-amber-300 hover:text-amber-200">{{ route('characters.profile.show', $character) }}</a></div>
            </div>

            <div class="mt-6 text-xs text-gray-500">
                Character styling remains separate from profile customization. Advanced profile HTML/CSS/JS is only rendered through the sandboxed profile iframe path.
            </div>
        </div>
    </div>
</x-app-layout>
