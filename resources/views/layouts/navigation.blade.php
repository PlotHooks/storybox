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
@endphp

<nav x-data="{ open: false }" class="bg-[#0b0b0c] border-b border-[#2a241a]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a
                        href="{{ route('rooms.landing') }}"
                        aria-label="Storybox chat home"
                        title="Storybox"
                        class="group inline-flex items-center rounded-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                    >
                        <img
                            src="{{ asset('images/storybox-icon.png') }}"
                            alt="Storybox"
                            class="block h-10 w-auto object-contain"
                        >
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
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
                            class="hidden absolute -top-2 -right-2 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded-full"
                        >
                            0
                        </span>
                    </button>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <div class="flex items-center gap-3">
                    <a
                        href="{{ route('profile.edit') }}"
                        class="inline-flex items-center px-3 py-2 text-sm rounded-md text-[#8f8675] hover:text-[#f2dfb5] transition"
                    >
                        {{ Auth::user()->name }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5] transition"
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
                        class="inline-flex items-center rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5] transition"
                    >
                        Log Out
                    </button>
                </form>

                <button @click="open = ! open"
                        class="p-2 rounded-md text-[#8f8675] hover:text-[#f2dfb5] hover:bg-[#141416]">
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
</nav>
