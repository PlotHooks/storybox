<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 dark:bg-gray-900 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    <!-- Characters link -->
                    <x-nav-link :href="route('characters.index')" :active="request()->routeIs('characters.index')">
                        {{ __('Characters') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Right side: Active Character + User Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:space-x-6">

                <!-- Active Character Dropdown -->
                @php
                    $activeId = session('active_character_id');
                    $activeCharacter = $activeId ? Auth::user()->characters->firstWhere('id', $activeId) : null;
                @endphp

                <div>
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4
                                           font-medium rounded-md text-gray-500 dark:text-gray-300 bg-white dark:bg-gray-800
                                           hover:text-gray-700 dark:hover:text-gray-100 focus:outline-none transition">
                                @if ($activeCharacter)
                                    <div>{{ $activeCharacter->name }}</div>
                                @else
                                    <div class="italic text-gray-400 dark:text-gray-500">No Active Character</div>
                                @endif

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                              clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <!-- List characters -->
                            @foreach (Auth::user()->characters as $character)
                                <form method="POST"
                                      action="{{ route('characters.switch', $character) }}">
                                    @csrf
                                    <x-dropdown-link :href="route('characters.switch', $character)"
                                         onclick="event.preventDefault(); this.closest('form').submit();">

                                        @if ($activeCharacter && $activeCharacter->id === $character->id)
                                            <span class="font-semibold text-teal-600 dark:text-teal-300">
                                                {{ $character->name }} (Active)
                                            </span>
                                        @else
                                            {{ $character->name }}
                                        @endif
                                    </x-dropdown-link>
                                </form>
                            @endforeach

                            <hr class="border-gray-200 dark:border-gray-700">

                            <!-- Manage characters -->
                            <x-dropdown-link :href="route('characters.index')">
                                {{ __('Manage Characters') }}
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </div>

                <!-- User Dropdown -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4
                                       font-medium rounded-md text-gray-500 dark:text-gray-300 bg-white dark:bg-gray-800
                                       hover:text-gray-700 dark:hover:text-gray-100 focus:outline-none transition">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                          clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Logout -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-400
                               hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800
                               focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800 focus:text-gray-500
                               transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }"
                              class="inline-flex"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }"
                              class="hidden"
                              stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">

        <!-- Responsive Nav Links -->
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('characters.index')" :active="request()->routeIs('characters.index')">
                {{ __('Characters') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">

            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <!-- Responsive Switch Character -->
            <div class="mt-3 space-y-1 px-4">
                @foreach (Auth::user()->characters as $character)
                    <form method="POST" action="{{ route('characters.switch', $character) }}">
                        @csrf
                        <x-responsive-nav-link :href="route('characters.switch', $character)"
                             onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ $character->name }}
                        </x-responsive-nav-link>
                    </form>
                @endforeach
            </div>

            <!-- Profile / Logout -->
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
