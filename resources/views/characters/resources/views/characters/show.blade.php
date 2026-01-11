<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $character->name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto bg-gray-900 rounded-lg shadow p-6 text-gray-200">
            <h3 class="text-lg font-semibold mb-2">Character Profile (Placeholder)</h3>
            <div class="text-sm text-gray-400 mb-4">
                This is a temporary character profile page.
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
