<x-guest-layout>
    <div class="mb-6 text-center">
        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-400/80">Welcome Back</div>
        <h1 class="mt-3 text-3xl font-semibold tracking-[-0.04em] text-[#fff4d2] sm:text-[2.1rem]">Log in to Storybox</h1>
        <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-[#b9ab8d] sm:text-base">
            Pick up where you left off, return to your rooms, and keep writing.
        </p>
    </div>

    <div class="mb-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
        @if (Route::has('register'))
            <a
                href="{{ route('register') }}"
                class="inline-flex items-center justify-center rounded-md border border-amber-400 bg-amber-500 px-5 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-[#120b02] transition duration-150 ease-in-out hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#050505]"
            >
                {{ __('Create Account') }}
            </a>
        @endif

        <a
            href="{{ route('landing') }}"
            class="inline-flex items-center justify-center rounded-md border border-[#332817] bg-[#141416] px-5 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-[#d6c8ad] transition duration-150 ease-in-out hover:border-amber-500/50 hover:bg-[#191511] hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#050505]"
        >
            {{ __('Back to Landing') }}
        </a>
    </div>

    <x-auth-session-status class="mb-4 rounded-lg border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-center" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm text-red-300" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm text-red-300" />
        </div>

        <div class="flex items-center justify-center">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-[#d6c8ad]">
                <input id="remember_me" type="checkbox" class="rounded border-[#4a3821] bg-[#080808] text-amber-500 shadow-sm focus:ring-amber-500 focus:ring-offset-0" name="remember">
                <span>{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex flex-col items-center gap-4 pt-2 text-center">
            <div class="text-sm text-[#b9ab8d]">
                @if (Route::has('register'))
                    {{ __('New here?') }}
                    <a class="rounded-md font-medium text-amber-300 underline decoration-amber-500/60 underline-offset-4 transition hover:text-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#050505]" href="{{ route('register') }}">
                        {{ __('Create an account') }}
                    </a>
                @endif
            </div>

            <div class="flex flex-col items-center gap-3 pt-1">
                @if (Route::has('password.request'))
                    <a class="rounded-md text-sm font-medium text-[#d6c8ad] underline decoration-amber-500/50 underline-offset-4 transition hover:text-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#050505]" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-primary-button class="justify-center whitespace-nowrap px-5">
                    {{ __('Log in') }}
                </x-primary-button>
            </div>
        </div>
    </form>
</x-guest-layout>
