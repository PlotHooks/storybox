<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            Characters
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-none w-full mx-auto px-4 sm:px-6 lg:px-8">
            @include('characters._manager', ['panelMode' => false])
        </div>
    </div>
</x-app-layout>
