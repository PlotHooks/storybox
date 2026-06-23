<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#f2dfb5] leading-tight">
            Rooms
        </h2>
    </x-slot>

    <div class="py-6 bg-[#050505]">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Create room form --}}
            <div class="bg-[#101012] border border-[#2a241a] shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-[#f2dfb5] mb-3">
                        Create a room
                    </h3>

                    @if ($errors->has('room_limit'))
                        <div class="mb-4 rounded-md border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-100">
                            {{ $errors->first('room_limit') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('rooms.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="name" class="block text-sm font-medium text-[#d6c8ad]">
                                Name
                            </label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                required
                                maxlength="100"
                                class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                                placeholder="GOOC, Wormwood Tavern, etc."
                            >
                            @error('name')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-[#d6c8ad]">
                                Description
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                                placeholder="Short blurb about the room."
                            ></textarea>
                            @error('description')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="submit"
                            class="inline-flex items-center px-4 py-2 bg-amber-500 border border-amber-400 rounded-md font-semibold text-xs text-[#120b02] uppercase tracking-widest hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#050505]"
                        >
                            Create room
                        </button>
                    </form>
                </div>
            </div>

            {{-- Room list --}}
            <div class="bg-[#101012] border border-[#2a241a] shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-[#f2dfb5] mb-3">
                        Available rooms
                    </h3>

                    @if ($rooms->isEmpty())
                        <p class="text-sm text-[#8f8675]">
                            No rooms yet. Be the first to create one.
                        </p>
                    @else
                        <ul class="divide-y divide-[#2a241a]">
                            @foreach ($rooms as $room)
                                <li class="py-3 flex items-center justify-between">
                                    <div>
                                        <a
                                            href="{{ route('rooms.show', $room->slug) }}"
                                            class="text-sm font-semibold text-amber-300 hover:text-amber-200"
                                        >
                                            {{ $room->name }}
                                        </a>

                                        @if ($room->description)
                                            <div class="text-xs text-[#8f8675]">
                                                {{ $room->description }}
                                            </div>
                                        @endif

                                        @if (($room->visibility ?? \App\Models\Room::VISIBILITY_PUBLIC) === \App\Models\Room::VISIBILITY_HIDDEN)
                                            <div class="text-[10px] uppercase tracking-[0.18em] text-amber-500/80">
                                                Hidden
                                            </div>
                                        @endif
                                    </div>

                                    <div class="text-xs text-[#8f8675]">
                                    owner {{ optional($room->ownerCharacter)->name ?? optional($room->creator)->name ?? 'Unknown' }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>


            <div class="bg-[#101012] border border-[#2a241a] shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-[#f2dfb5] mb-3">
                        Recoverable Rooms
                    </h3>

                    <p class="mb-4 text-sm text-[#8f8675]">
                        Soft-deleted public rooms remain recoverable until the retention window expires. Expired rooms do not appear here.
                    </p>

                    @if ($recoverableRooms->isEmpty())
                        <p class="text-sm text-[#8f8675]">
                            No recoverable public rooms are available on this account.
                        </p>
                    @else
                        <ul class="divide-y divide-[#2a241a]">
                            @foreach ($recoverableRooms as $room)
                                <li class="py-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="text-sm font-semibold text-amber-300">
                                            {{ $room['name'] }}
                                        </div>

                                        @if ($room['description'])
                                            <div class="text-xs text-[#8f8675]">
                                                {{ $room['description'] }}
                                            </div>
                                        @endif

                                        <div class="mt-1 text-[11px] text-[#8f8675]">
                                            Owner {{ $room['owner_name'] }} • {{ ucfirst($room['visibility']) }} • deleted {{ optional($room['deleted_at'])->format('M j, Y g:i A') }} • recover until {{ optional($room['recovery_expires_at'])->format('M j, Y g:i A') }}
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('rooms.recoverable.restore', $room['id']) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-emerald-500 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-[#050505]"
                                        >
                                            Restore Room
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
