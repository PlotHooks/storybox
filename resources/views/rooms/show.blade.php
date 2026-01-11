<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            {{ $room->name }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto h-[calc(100vh-6rem)] flex gap-4 px-4">

            {{-- LEFT COLUMN: Global OOC placeholder --}}
            <div class="w-1/4 bg-gray-900 text-gray-100 rounded-lg shadow flex flex-col">
                <div class="px-3 py-2 border-b border-gray-800 text-xs font-semibold text-green-400">
                    Global OOC (always open)
                </div>
                <div class="flex-1 overflow-y-auto px-3 py-2 text-xs text-gray-300">
                    {{-- Later: embed a real GOOC room here --}}
                    <p class="text-gray-500">
                        Global OOC stream will live here eventually.
                    </p>
                </div>
            </div>

            {{-- CENTER COLUMN: main room chat --}}
            <div class="flex-1 bg-gray-900 rounded-lg shadow flex flex-col">

                {{-- Top bar: owner + character switcher --}}
                <div class="flex items-center justify-between px-4 py-2 border-b border-gray-800">
                    <div class="text-xs text-gray-300">
                        Room owner:
                        <span class="font-semibold text-gray-100">
                            {{ optional($room->owner)->name ?? 'Unknown' }}
                        </span>
                    </div>

                    @php
                        $characters = Auth::user()->characters;
                    @endphp

                    @if ($characters->count() > 0)
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-300">
                                Posting as:
                            </span>
                            <select id="character-switcher"
                                    class="rounded border-gray-700 bg-gray-800 text-xs text-gray-100 px-2 py-1">
                                @foreach ($characters as $char)
                                    <option value="{{ $char->id }}"
                                        {{ $char->id == $activeCharacterId ? 'selected' : '' }}>
                                        {{ $char->name }}
                                    </option>
                                @endforeach
                            </select>

                            {{-- NEW: Leave room button --}}
                            <button
                                type="button"
                                id="leave-room-btn"
                                class="rounded border border-gray-700 bg-gray-800 text-xs text-gray-100 px-2 py-1 hover:bg-gray-700">
                                Leave room
                            </button>
                        </div>
                    @else
                        <div class="text-xs text-red-400">
                            You need at least one character to post.
                        </div>
                    @endif
                </div>

                {{-- Messages list --}}
                <div id="message-container"
                     class="flex-1 overflow-y-auto p-4 space-y-3">
                    @foreach ($messages as $message)
                        <div class="border-b border-gray-800 pb-2 mb-2">
                            <div class="text-[10px] text-gray-400">
                                {{ optional($message->character)->name ?? $message->user->name }}
                                · {{ $message->created_at->diffForHumans() }}
                            </div>
                            <div class="text-sm text-gray-100 whitespace-pre-line">
                                {{ $message->body }}
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- New message form, fixed at bottom of center column --}}
                <div class="border-t border-gray-800 p-3">
                    <form method="POST" action="{{ route('rooms.messages.store', $room) }}" id="message-form">
                        @csrf

                        <div>
                            <textarea
                                id="body"
                                name="body"
                                rows="3"
                                required
                                placeholder="Type your message. Enter to send, Shift+Enter for a new line."
                                class="mt-1 block w-full rounded-md border-gray-700 bg-gray-950 text-sm text-gray-100
                                       shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >{{ old('body') }}</textarea>

                            @error('body')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-2 flex justify-end">
                            <x-primary-button id="send-button">
                                Send
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- RIGHT COLUMN: room list with active user counts --}}
            <div class="w-1/5 bg-gray-900 text-gray-100 rounded-lg shadow flex flex-col">
                <div class="px-3 py-2 border-b border-gray-800 text-xs font-semibold text-green-400 flex items-center justify-between">
                    <span>Rooms</span>
                    <span class="text-[10px] text-gray-500"># active / name</span>
                </div>

                <div id="room-list" class="flex-1 overflow-y-auto text-xs">
                    @foreach ($sidebarRooms as $r)
                        <button
                            type="button"
                            onclick="window.location.href='{{ route
