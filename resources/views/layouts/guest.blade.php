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
        <div class="min-h-screen flex flex-col items-center justify-center bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.14),_transparent_34%),linear-gradient(180deg,_#14110d_0%,_#050505_45%,_#050505_100%)] px-4 py-8 sm:px-6">
            <div class="mb-4 sm:mb-5">
                <a href="/" class="inline-flex items-center justify-center">
                    <x-application-logo class="h-28 sm:h-36 lg:h-40 w-auto text-amber-400 drop-shadow-[0_0_28px_rgba(245,158,11,0.18)]" />
                </a>
            </div>

            <div class="w-full sm:max-w-lg rounded-2xl border border-[#3a2c19] bg-[#0b0b0c]/95 px-6 py-5 shadow-[0_22px_60px_rgba(0,0,0,0.58)] overflow-hidden backdrop-blur sm:px-8 sm:py-6">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
