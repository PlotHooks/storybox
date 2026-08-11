<x-app-layout>
    <div class="box-border h-[calc(100dvh-6.5rem)] sm:h-[calc(100dvh-4rem)] min-h-0 overflow-hidden py-4 bg-[#070707]">
        <div class="max-w-none w-full mx-auto h-full min-h-0 overflow-hidden flex flex-col lg:flex-row gap-3 px-2 md:px-4">
            <aside class="w-full lg:w-72 min-h-0 bg-[#0b0b0c] text-[#d6c8ad] rounded-md shadow-2xl flex flex-col border border-[#2a241a] overflow-hidden">
                <div class="px-4 py-3 border-b border-[#2a241a] bg-[#101012]"><div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-amber-400">Context Dock</div><div class="mt-1 text-sm font-semibold text-[#f2dfb5]">No room selected</div></div>
                <div class="flex-1 p-4 text-xs text-[#8f8675]"></div>
            </aside>
            <main class="min-w-0 flex-1 min-h-0 bg-[#0b0b0c] text-[#d6c8ad] rounded-md shadow-2xl flex flex-col border border-[#2a241a] overflow-hidden">
                <header class="shrink-0 border-b border-[#2a241a] bg-[#101012] px-4 py-3"><div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2"><span class="h-2 w-2 rounded-sm bg-[#6f675a]"></span><h1 class="text-lg font-semibold text-[#f2dfb5] md:text-xl">StoryBox</h1></div>@if ($characters->isNotEmpty())<div class="flex items-center gap-2"><span class="text-xs text-[#8f8675]">Posting as</span><select id="character-switcher" class="rounded border-[#332817] bg-[#0b0b0c] text-xs text-[#f2dfb5] px-2 py-1 focus:border-amber-500 focus:ring-amber-500">@foreach ($characters as $character)<option value="{{ $character->id }}" {{ $activeCharacter?->id === $character->id ? 'selected' : '' }}>{{ $character->name }}</option>@endforeach</select></div>@endif</div></header>
                <section id="empty-room-state" class="flex-1 min-h-0 flex items-center justify-center bg-[#070707] px-6 text-center"><div><p class="text-lg font-semibold text-[#f2dfb5]">You aren’t in a room yet.</p><p class="mt-2 text-sm text-[#8f8675]">Select a room from the Nexus to begin.</p></div></section>
            </main>
            <aside class="w-full lg:w-80 min-h-0 bg-[#0b0b0c] text-[#d6c8ad] rounded-md shadow-2xl flex flex-col border border-[#2a241a] overflow-hidden">
                <div class="border-b border-[#2a241a] bg-[#101012] px-3 py-3"><div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-amber-400">Nexus</div><a href="{{ route('rooms.create') }}" class="mt-3 block rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm font-semibold text-amber-100 hover:bg-amber-500/20">+ Create Room</a></div>
                <div class="flex-1 min-h-0 overflow-y-auto p-2 text-xs"><label for="room-filter-input" class="sr-only">Filter rooms</label><input id="room-filter-input" type="text" placeholder="Filter rooms" class="mb-2 block w-full rounded-md border-[#332817] bg-[#101012] px-3 py-2 text-sm text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"><div id="room-list" class="space-y-2">@foreach ($sidebarRooms as $room)<a href="{{ route('rooms.show', $room->slug) }}" data-room-item data-room-name="{{ \Illuminate\Support\Str::lower($room->name) }}" data-room-description="{{ \Illuminate\Support\Str::lower((string) $room->description) }}" class="block w-full rounded border border-[#332817] bg-[#101012] px-3 py-2 text-left text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]"><span class="block truncate font-medium">{{ $room->name }}</span>@if ($room->description)<span class="mt-1 block truncate text-[11px] text-[#8f8675]">{{ $room->description }}</span>@endif</a>@endforeach</div><p id="room-list-empty" class="hidden px-3 py-4 text-[#8f8675]">No rooms match this filter.</p></div>
                @if ($showRecoveryLink)<div class="shrink-0 border-t border-[#2a241a] bg-[#0b0b0c] p-2"><a href="{{ route('rooms.recovery') }}" class="block rounded border border-[#332817] bg-[#101012] px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Recoverable Rooms</a></div>@endif
            </aside>
        </div>
    </div>
    <script>
        const csrf = @json(csrf_token());
        const currentCharacterUrl = @json(route('rooms.current-character'));
        const switcher = document.getElementById('character-switcher');
        let switching = false;
        switcher?.addEventListener('change', async () => {
            if (switching) return;
            switching = true;
            try {
                const response = await fetch(currentCharacterUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ character_id: Number(switcher.value || 0) }) });
                const data = await response.json().catch(() => null);
                if (!response.ok || !data?.ok) throw new Error('Could not switch characters.');
                window.location.href = data.room_url || @json(route('rooms.landing'));
            } catch (_) { switching = false; window.alert('Could not switch characters. Please try again.'); }
        });
        const filter = document.getElementById('room-filter-input');
        filter?.addEventListener('input', () => { const query = filter.value.trim().toLowerCase(); let visible = 0; document.querySelectorAll('[data-room-item]').forEach((item) => { const match = !query || `${item.dataset.roomName} ${item.dataset.roomDescription}`.includes(query); item.classList.toggle('hidden', !match); if (match) visible += 1; }); document.getElementById('room-list-empty')?.classList.toggle('hidden', visible > 0); });
    </script>
</x-app-layout>
