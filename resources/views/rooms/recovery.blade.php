<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#f2dfb5] leading-tight">
            Recoverable Rooms
        </h2>
    </x-slot>

    <div class="py-6 bg-[#050505]">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-[#101012] border border-[#2a241a] shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-[#f2dfb5] mb-3">
                        Recoverable Rooms
                    </h3>

                    <p class="mb-4 text-sm text-[#8f8675]">
                        Soft-deleted public rooms remain recoverable until the retention window expires. Expired rooms do not appear here.
                    </p>

                    @if (session('status'))
                        <div class="mb-4 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                            {{ session('status') }}
                        </div>
                    @endif

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
