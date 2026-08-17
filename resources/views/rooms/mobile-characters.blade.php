<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Characters · {{ config('app.name', 'Storybox') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-[100dvh] bg-[#050505] font-sans text-[#d6c8ad] antialiased">
    <main class="mx-auto min-h-[100dvh] max-w-lg px-5 pb-10 pt-8">
        <header class="mb-8">
            <a href="{{ route('rooms.mobile-characters') }}" class="inline-flex items-center gap-3 rounded-sm focus:outline-none focus:ring-2 focus:ring-purple-400/60">
                <img src="{{ asset('images/storybox-icon.png') }}" alt="Storybox" class="h-11 w-auto object-contain">
                <span class="text-lg font-semibold tracking-[0.18em] text-[#f2dfb5]">StoryBox</span>
            </a>
            <p class="mt-7 text-[11px] font-semibold uppercase tracking-[0.22em] text-purple-300">Choose a character</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-[#f2dfb5]">Where will your story go?</h1>
        </header>

        @if ($characterCards->isEmpty())
            <section class="rounded-2xl border border-[#342a4d] bg-[#111018] p-6 shadow-2xl">
                <p class="text-xl font-semibold text-[#f2dfb5]">Create your first character</p>
                <p class="mt-2 text-sm leading-6 text-[#a59bb2]">Characters are how you enter rooms and take part in Storybox.</p>
                <div class="mt-6 space-y-3">
                    <a href="{{ route('characters.index') }}" class="flex min-h-14 items-center justify-center rounded-xl bg-purple-500 px-4 text-base font-semibold text-white">Create Character</a>
                    <a href="{{ route('rooms.index') }}" class="flex min-h-14 items-center justify-center rounded-xl border border-[#514265] bg-[#18151f] px-4 text-base font-semibold text-[#efe0ff]">Browse Rooms</a>
                    <a href="{{ route('public.rules-faq') }}" class="flex min-h-12 items-center justify-center rounded-xl px-4 text-sm font-semibold text-purple-200">Site Rules</a>
                </div>
            </section>
        @else
            <section aria-label="Your characters" class="space-y-4">
                @foreach ($characterCards as $card)
                    @php
                        $character = $card['character'];
                        $avatar = $character->externalAvatarUrl();
                        $room = $card['room'];
                        $dmUnreadCount = $card['dm_unread_count'];
                    @endphp
                    <button type="button" data-character-card data-character-id="{{ $character->id }}" class="group flex min-h-32 w-full items-center gap-4 rounded-2xl border border-[#382e4b] bg-[#121018] p-5 text-left shadow-xl transition hover:border-purple-400/70 hover:bg-[#181421] focus:outline-none focus:ring-2 focus:ring-purple-300 disabled:cursor-wait disabled:opacity-60">
                        @if ($avatar)
                            <img src="{{ $avatar }}" alt="" class="h-20 w-20 shrink-0 rounded-2xl object-cover ring-1 ring-purple-300/30">
                        @else
                            <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-500 to-violet-800 text-2xl font-semibold text-white">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($character->name, 0, 1)) }}</span>
                        @endif
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xl font-semibold text-[#f2dfb5]">{{ $character->name }}</span>
                            @if ($room)
                                <span class="mt-2 block truncate text-sm text-purple-200">{{ $room->name }}</span>
                            @else
                                <span class="mt-2 block text-sm text-[#9b91a7]">Not currently in a room</span>
                            @endif
                            @if ($dmUnreadCount > 0)
                                <span class="mt-3 inline-flex rounded-full bg-purple-500/20 px-2.5 py-1 text-xs font-semibold text-purple-100">{{ $dmUnreadCount > 99 ? '99+' : $dmUnreadCount }} unread {{ \Illuminate\Support\Str::plural('DM', $dmUnreadCount) }}</span>
                            @endif
                        </span>
                        <span aria-hidden="true" class="text-2xl text-purple-300">›</span>
                    </button>
                @endforeach
            </section>

            <nav aria-label="Character actions" class="mt-8 grid grid-cols-2 gap-3">
                <a href="{{ route('characters.index') }}" class="flex min-h-12 items-center justify-center rounded-xl border border-[#514265] bg-[#18151f] px-3 text-sm font-semibold text-purple-100">Create Character</a>
                <a href="{{ route('rooms.index') }}" class="flex min-h-12 items-center justify-center rounded-xl border border-[#514265] bg-[#18151f] px-3 text-sm font-semibold text-purple-100">Browse Rooms</a>
            </nav>
        @endif
    </main>

    <script>
        (() => {
            const endpoint = @json(route('rooms.current-character'));
            const fallbackUrl = @json(route('rooms.landing'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            let switching = false;
            document.querySelectorAll('[data-character-card]').forEach((card) => {
                card.addEventListener('click', async () => {
                    if (switching) return;
                    switching = true;
                    card.disabled = true;
                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                            credentials: 'same-origin',
                            body: JSON.stringify({character_id: Number(card.dataset.characterId || 0)}),
                        });
                        const data = await response.json().catch(() => null);
                        if (!response.ok || !data?.ok) throw new Error('Could not select character.');
                        window.location.assign(data.room_url || fallbackUrl);
                    } catch (_) {
                        switching = false;
                        card.disabled = false;
                        window.alert('Could not select character. Please try again.');
                    }
                });
            });
        })();
    </script>
</body>
</html>
