@php
    $dmActive = request()->routeIs('dms.*');
    $charactersPanelAvailable = request()->routeIs('rooms.*');
    $charactersActive = request()->routeIs('characters.*') || ($charactersPanelAvailable && request()->query('characters') === '1');
    $charactersButtonClasses = $charactersActive
        ? 'inline-flex items-center rounded-md border border-amber-400/70 bg-amber-500/15 px-3 py-2 text-sm font-semibold leading-5 text-[#fff2cc] shadow-[0_0_0_1px_rgba(245,158,11,0.2),0_0_18px_rgba(245,158,11,0.16)] focus:outline-none focus:ring-2 focus:ring-amber-400/50 transition duration-150 ease-in-out'
        : 'inline-flex items-center rounded-md border border-transparent px-3 py-2 text-sm font-medium leading-5 text-[#8f8675] hover:border-[#5a431f] hover:bg-[#141416] hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-400/40 transition duration-150 ease-in-out';
    $dmButtonClasses = $dmActive
        ? 'relative inline-flex items-center rounded-md border border-amber-400/70 bg-amber-500/15 px-3 py-2 text-sm font-semibold text-[#fff2cc] shadow-[0_0_0_1px_rgba(245,158,11,0.2),0_0_18px_rgba(245,158,11,0.16)] transition focus:outline-none focus:ring-2 focus:ring-amber-400/50'
        : 'relative inline-flex items-center rounded-md border border-transparent px-3 py-2 text-sm font-medium text-[#8f8675] transition hover:border-[#5a431f] hover:bg-[#141416] hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-400/40';
    $siteContentButtonClasses = 'relative inline-flex items-center rounded-md border border-transparent px-3 py-2 text-sm font-medium text-[#8f8675] transition hover:border-[#5a431f] hover:bg-[#141416] hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-400/40';
@endphp

<nav x-data="{ open: false }" class="border-b border-[#2a241a] bg-[#0b0b0c]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex">
                <div class="flex shrink-0 items-center">
                    <a
                        href="{{ route('rooms.landing') }}"
                        aria-label="Storybox chat home"
                        title="Storybox"
                        class="group inline-flex items-center gap-3 rounded-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                    >
                        <img
                            src="{{ asset('images/storybox-icon.png') }}"
                            alt="Storybox"
                            class="block h-10 w-auto object-contain"
                        >
                        <span class="hidden text-lg font-semibold tracking-[0.18em] text-[#f2dfb5] sm:inline">StoryBox</span>
                    </a>
                </div>

                <div class="hidden space-x-4 sm:-my-px sm:ms-10 sm:flex">
                    @if ($charactersPanelAvailable)
                        <button type="button" data-open-characters-panel class="{{ $charactersButtonClasses }}">
                            Characters
                        </button>
                    @else
                        <x-nav-link :href="route('characters.index')" :active="request()->routeIs('characters.*')">
                            Characters
                        </x-nav-link>
                    @endif

                    <button
                        id="global-dm-button"
                        class="{{ $dmButtonClasses }}"
                        onclick="window.dispatchEvent(new CustomEvent('open-dm-window'))"
                        aria-pressed="{{ $dmActive ? 'true' : 'false' }}"
                    >
                        DMs

                        <span
                            id="dm-unread-badge"
                            class="hidden absolute -top-2 -right-2 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] text-white"
                        >
                            0
                        </span>
                    </button>

                    <button
                        id="global-site-content-button"
                        type="button"
                        class="{{ $siteContentButtonClasses }}"
                        onclick="window.dispatchEvent(new CustomEvent('open-site-content-window', { detail: { collection: 'rules-faq', title: 'Rules / FAQ' } }))"
                    >
                        Rules / FAQ
                    </button>
                </div>
            </div>

            <div class="hidden sm:ms-6 sm:flex sm:items-center">
                <div class="flex items-center gap-3">
                    <a
                        href="{{ route('profile.edit') }}"
                        class="inline-flex items-center rounded-md px-3 py-2 text-sm text-[#8f8675] transition hover:text-[#f2dfb5]"
                    >
                        {{ Auth::user()->name }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#d6c8ad] transition hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]"
                        >
                            Log Out
                        </button>
                    </form>
                </div>
            </div>

            <div class="-me-2 flex items-center gap-2 sm:hidden">
                @if ($charactersPanelAvailable)
                    <button type="button" data-open-characters-panel class="{{ $charactersButtonClasses }}">
                        Characters
                    </button>
                @else
                    <a href="{{ route('characters.index') }}" class="{{ $charactersButtonClasses }}">
                        Characters
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}" class="me-2">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#d6c8ad] transition hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]"
                    >
                        Log Out
                    </button>
                </form>

                <button @click="open = ! open"
                        class="rounded-md p-2 text-[#8f8675] hover:bg-[#141416] hover:text-[#f2dfb5]">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path :class="{'hidden': open, 'inline-flex': ! open }"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16"/>
                        <path :class="{'hidden': ! open, 'inline-flex': open }"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div class="border-t border-[#2a241a] bg-[#0b0b0c] px-4 py-2 sm:hidden">
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="{{ $dmButtonClasses }} px-2.5 py-1.5 text-xs"
                onclick="window.dispatchEvent(new CustomEvent('open-dm-window'))"
            >
                DMs
            </button>
            <button
                type="button"
                class="{{ $siteContentButtonClasses }} px-2.5 py-1.5 text-xs"
                onclick="window.dispatchEvent(new CustomEvent('open-site-content-window', { detail: { collection: 'rules-faq', title: 'Rules / FAQ' } }))"
            >
                Rules / FAQ
            </button>
        </div>
    </div>
</nav>
