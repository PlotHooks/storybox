<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $room->name }} Profile | Storybox</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#050505] text-[#e9dcc2] antialiased">
        <div class="min-h-screen bg-[#050505]">
            <div class="pointer-events-none fixed inset-x-0 top-0 z-20 p-4 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="pointer-events-auto flex flex-wrap items-center gap-3">
                        <a href="{{ route('rooms.show', $room->slug) }}" class="inline-flex items-center rounded-full border border-[#5a431f] bg-[#141416]/95 px-4 py-2 text-sm font-medium text-[#f2dfb5] shadow-lg backdrop-blur hover:bg-[#191511]">
                            Back to Room
                        </a>
                        @if ($canManageRoom)
                            <a href="{{ route('rooms.profile.edit', $room->slug) }}" class="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-200 shadow-lg backdrop-blur hover:bg-amber-500/20">
                                Edit Profile
                            </a>
                        @endif
                    </div>

                    @if (session('status'))
                        <div class="pointer-events-auto rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200 shadow-lg backdrop-blur">
                            {{ session('status') }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="min-h-screen w-full" data-advanced-room-profile-viewport>
                <iframe
                    title="{{ $room->name }} profile"
                    src="{{ route('rooms.profile.frame', $room->slug) }}"
                    sandbox="allow-scripts"
                    referrerpolicy="no-referrer"
                    class="block min-h-screen w-full border-0 bg-transparent"
                ></iframe>
            </div>
        </div>
    </body>
</html>
