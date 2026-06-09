@props([
    'title' => 'Edit Profile',
    'subtitle' => null,
    'closeHref',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} | Storybox</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#050505] text-[#d6c8ad] antialiased">
        <div class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(62,42,18,0.4),rgba(7,7,7,0.96)_46%,#050505_100%)]">
            <header class="border-b border-[#2a241a] bg-[#0b0b0c]/95 backdrop-blur">
                <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8 lg:py-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-[#b89f70]">Profile Editor</p>
                            <h1 class="mt-2 text-2xl font-semibold text-[#fff2cc] sm:text-3xl">{{ $title }}</h1>
                            @if (filled($subtitle))
                                <p class="mt-2 text-sm text-[#8f8675]">{{ $subtitle }}</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            {{ $actions ?? '' }}
                            <button
                                type="button"
                                onclick="if (window.opener || window.history.length <= 1) { window.close(); } if (!window.closed) { window.location.href = '{{ $closeHref }}'; }"
                                class="inline-flex items-center rounded border border-[#5a431f] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5] hover:bg-[#191511]"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="py-6">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
