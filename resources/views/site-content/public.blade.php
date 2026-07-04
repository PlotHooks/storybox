<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} | {{ config('app.name') === 'Laravel' ? 'Storybox' : config('app.name', 'Storybox') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#050505] font-sans text-[#d6c8ad] antialiased">
        <div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.12),_transparent_30%),linear-gradient(180deg,_#14110d_0%,_#050505_38%,_#050505_100%)] px-4 py-10 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl">
                <div class="mb-8 flex items-center justify-between gap-4">
                    <a href="{{ route('landing') }}" class="inline-flex items-center gap-3 text-[#f2dfb5] transition hover:text-amber-200">
                        <x-storybox-logo class="h-12 w-auto" />
                        <span class="text-sm font-medium uppercase tracking-[0.28em] text-[#b9ab8d]">Storybox</span>
                    </a>

                    <a href="{{ route('login') }}" class="rounded-md border border-[#332817] bg-[#111113] px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-[#d6c8ad] transition hover:border-amber-500/40 hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#050505]">
                        Login
                    </a>
                </div>

                <div class="rounded-[2rem] border border-[#3a2c19] bg-[#0b0b0c]/95 px-6 py-8 shadow-[0_22px_60px_rgba(0,0,0,0.58)] backdrop-blur sm:px-8 lg:px-12 lg:py-12">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-[#8f8675]">Storybox</p>
                        <h1 class="mt-4 text-3xl font-semibold tracking-[-0.03em] text-[#f6e7bf] sm:text-4xl">{{ $headline }}</h1>
                    </div>

                    <div class="mt-10 space-y-10">
                        @foreach ($documents as $document)
                            <article class="border-t border-[#2a2115] pt-8 first:border-t-0 first:pt-0">
                                @if (count($documents) > 1)
                                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300/80">
                                        {{ $document['category_label'] ?? $headline }}
                                    </p>
                                @endif

                                <h2 class="mt-3 text-2xl font-semibold tracking-[-0.02em] text-[#f2dfb5]">
                                    {{ $document['title'] }}
                                </h2>

                                <div class="site-content-document-body mt-5 text-sm text-[#d6c8ad] sm:text-base">
                                    {!! $document['rendered_body_html'] !!}
                                </div>

                                @if (! empty($document['updated_at']))
                                    <p class="mt-5 text-xs text-[#8f8675]">
                                        Updated {{ $document['updated_at'] }}
                                    </p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
