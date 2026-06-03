<nav x-data="{ open: false }" class="bg-[#0b0b0c] border-b border-[#2a241a]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">

            <!-- LEFT -->
            <div class="flex">

                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a
                        href="{{ route('dashboard') }}"
                        aria-label="Storybox dashboard"
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

                <!-- Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">

                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-nav-link>

                    <x-nav-link :href="route('characters.index')" :active="request()->routeIs('characters.*')">
                        Characters
                    </x-nav-link>

                    <x-nav-link :href="route('rooms.index')" :active="request()->routeIs('rooms.*')">
                        Rooms
                    </x-nav-link>

                    <!-- 🔥 DM BUTTON -->
                    <button
                        id="global-dm-button"
                        class="relative text-[#8f8675] hover:text-[#f2dfb5] transition"
                        onclick="window.dispatchEvent(new CustomEvent('open-dm-window'))"
                    >
                        DMs

                        <!-- unread badge placeholder -->
                        <span
                            id="dm-unread-badge"
                            class="hidden absolute -top-2 -right-4 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded-full"
                        >
                            0
                        </span>
                    </button>

                </div>
            </div>

            <!-- RIGHT -->
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

            <!-- MOBILE -->
            <div class="-me-2 flex items-center sm:hidden">
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
