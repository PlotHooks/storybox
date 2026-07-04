<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') === 'Laravel' ? 'Storybox' : config('app.name', 'Storybox') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
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
                <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(5,5,5,0.3)_0%,rgba(5,5,5,0.68)_42%,rgba(5,5,5,0.92)_72%,#050505_100%)]" aria-hidden="true"></div>
                <div class="absolute inset-x-0 bottom-0 h-40 bg-[linear-gradient(180deg,rgba(5,5,5,0)_0%,#050505_90%)]" aria-hidden="true"></div>

                <div class="relative mx-auto flex min-h-screen max-w-6xl items-center justify-center px-6 py-10 sm:px-8 sm:py-12 lg:px-10 lg:py-14">
                    <div class="w-full max-w-5xl">
                        <div class="mb-8 text-center sm:mb-10">
                            <a href="{{ route('landing') }}" class="inline-flex flex-col items-center justify-center text-center">
                                <x-application-logo class="h-16 w-auto sm:h-20" />
                                <span class="mt-6 text-4xl font-semibold tracking-[-0.05em] text-[#fff4d2] sm:text-5xl lg:text-6xl">Storybox</span>
                                <span class="mt-3 text-base font-medium tracking-[-0.02em] text-[#f2dfb5] sm:text-lg">Collaborative roleplaying, built for writers.</span>
                            </a>
                        </div>

                        <div class="mx-auto w-full max-w-lg rounded-[1.75rem] border border-[#3a2c19] bg-[#0b0b0c]/95 px-6 py-6 shadow-[0_30px_90px_rgba(0,0,0,0.55)] backdrop-blur sm:px-8 sm:py-8">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </body>
</html>
