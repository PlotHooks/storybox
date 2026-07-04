<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') === 'Laravel' ? 'Storybox' : config('app.name', 'Storybox') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#050505] font-sans text-[#d6c8ad] antialiased">
        <div class="min-h-screen bg-[#050505]">
            <section class="relative isolate overflow-hidden">
                <div
                    class="absolute inset-0 bg-cover bg-center"
                    style="background-image: url('{{ asset('images/landingpg1.png') }}');"
                    aria-hidden="true"
                ></div>
                <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(5,5,5,0.3)_0%,rgba(5,5,5,0.62)_42%,rgba(5,5,5,0.9)_72%,#050505_100%)]" aria-hidden="true"></div>
                <div class="absolute inset-x-0 bottom-0 h-40 bg-[linear-gradient(180deg,rgba(5,5,5,0)_0%,#050505_90%)]" aria-hidden="true"></div>

                <div class="relative mx-auto flex min-h-[56vh] max-w-6xl items-center justify-center px-6 py-10 sm:px-8 sm:py-12 lg:px-10 lg:py-14">
                    <div class="mx-auto flex max-w-2xl flex-col items-center text-center">
                        <x-storybox-logo class="h-16 w-auto sm:h-20" />

                        <h1 class="mt-8 text-5xl font-semibold tracking-[-0.05em] text-[#fff4d2] sm:text-6xl lg:text-7xl">
                            Storybox
                        </h1>

                        <p class="mt-5 text-xl font-medium tracking-[-0.02em] text-[#f2dfb5] sm:text-2xl">
                            Collaborative roleplaying, built for writers.
                        </p>

                        <p class="mt-4 max-w-xl text-base leading-7 text-[#d6c8ad] sm:text-lg">
                            Create characters. Join rooms. Tell stories.
                        </p>

                        <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:items-center sm:justify-center">
                            <a
                                href="{{ route('register') }}"
                                class="inline-flex items-center justify-center rounded-md border border-amber-400 bg-amber-500 px-5 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-[#120b02] transition duration-150 ease-in-out hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#050505]"
                            >
                                Create Account
                            </a>
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center justify-center rounded-md border border-[#332817] bg-[#141416] px-5 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-[#d6c8ad] transition duration-150 ease-in-out hover:border-amber-500/50 hover:bg-[#191511] hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#050505]"
                            >
                                Login
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="relative z-10 -mt-28 px-6 sm:-mt-32 sm:px-8 lg:px-10">
                <div class="mx-auto max-w-5xl">
                    <img
                        src="{{ asset('images/welcomescreenshot.png') }}"
                        alt="Storybox application screenshot"
                        class="mx-auto w-full rounded-[1.75rem] border border-[#2d2418] bg-[#0b0b0c] shadow-[0_30px_90px_rgba(0,0,0,0.55)]"
                    >
                </div>
            </section>

            <section class="px-6 pb-12 pt-12 sm:px-8 sm:pb-14 sm:pt-14 lg:px-10 lg:pb-16">
                <div class="mx-auto max-w-3xl text-center">
                    <h2 class="text-3xl font-semibold tracking-[-0.04em] text-[#fff4d2] sm:text-4xl">
                        Everything you need for collaborative roleplay.
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-[#b9ab8d] sm:text-lg">
                        Focus on your characters, your stories, and your world.
                    </p>
                </div>
            </section>

            <footer class="border-t border-[#221a10] px-6 py-6 sm:px-8 sm:py-7 lg:px-10">
                <div class="mx-auto flex max-w-6xl flex-col gap-4 text-sm text-[#8f8675] sm:flex-row sm:flex-wrap sm:items-center sm:justify-center sm:gap-8">
                    <a href="{{ route('public.about') }}" class="transition hover:text-[#f2dfb5]">About</a>
                    <a href="{{ route('public.rules-faq') }}" class="transition hover:text-[#f2dfb5]">Rules / FAQ</a>
                    <a href="{{ route('public.privacy-policy') }}" class="transition hover:text-[#f2dfb5]">Privacy Policy</a>
                    <a href="{{ route('public.terms-of-service') }}" class="transition hover:text-[#f2dfb5]">Terms of Service</a>
                </div>
            </footer>
        </div>
    </body>
</html>
