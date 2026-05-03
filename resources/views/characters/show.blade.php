<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $character->name }}
        </h2>
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
                    <h3 class="text-lg font-semibold mb-2">Character Profile (Placeholder)</h3>
                    <div class="text-sm text-gray-400">
                        This is a temporary character profile page.
                    </div>
                </div>
            </div>

            <div class="space-y-2 text-sm">
                <div><span class="text-gray-500">Name:</span> <span class="text-gray-100">{{ $character->name }}</span></div>
                <div><span class="text-gray-500">Character ID:</span> <span class="text-gray-100">{{ $character->id }}</span></div>
                <div><span class="text-gray-500">Owner user ID:</span> <span class="text-gray-100">{{ $character->user_id }}</span></div>
            </div>

            <div class="mt-6 text-xs text-gray-500">
                Profile editing and customization coming later.
            </div>
        </div>
    </div>
</x-app-layout>
