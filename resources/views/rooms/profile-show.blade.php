<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $room->name }} Profile | Storybox</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#050505] text-[#e9dcc2] antialiased">
        @php
            $profileBannerUrl = trim((string) ($room->profile_banner_url ?? ''));
            $profileSummary = trim((string) ($room->profile_summary ?? ''));
            $profileJoiningInformation = trim((string) ($room->profile_joining_information ?? ''));
            $roomRules = $room->roomRules ?? collect();
            $hasRoomProfile = $profileBannerUrl !== ''
                || $profileSummary !== ''
                || $profileJoiningInformation !== ''
                || $roomRules->isNotEmpty();
        @endphp

        <div class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(62,42,18,0.55),rgba(7,7,7,0.96)_45%,#050505_100%)]">
            <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-10">
                <div class="mx-auto mb-5 flex w-full max-w-none flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('rooms.show', $room->slug) }}" class="inline-flex items-center rounded-full border border-[#5a431f] bg-[#141416]/95 px-4 py-2 text-sm font-medium text-[#f2dfb5] hover:bg-[#191511]">
                            Back to Room
                        </a>
                        @if ($canManageRoom)
                            <a href="{{ route('rooms.profile.edit', $room->slug) }}" class="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-500/20">
                                Edit Profile
                            </a>
                        @endif
                    </div>

                    @if (session('status'))
                        <div class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">
                            {{ session('status') }}
                        </div>
                    @endif
                </div>

                <section class="w-full overflow-hidden border-y border-[#3a2f1e] bg-[#0b0b0c]/95 shadow-[0_30px_80px_rgba(0,0,0,0.45)] sm:rounded-[2rem] sm:border sm:shadow-[0_30px_80px_rgba(0,0,0,0.45)]">
                    <div class="relative min-h-[18rem] bg-[#15100c] sm:min-h-[22rem] lg:min-h-[26rem]">
                        @if ($profileBannerUrl !== '')
                            <img
                                src="{{ $profileBannerUrl }}"
                                alt="{{ $room->name }} banner"
                                class="absolute inset-0 h-full w-full object-cover"
                                loading="lazy"
                            >
                            <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(11,11,12,0.1)_0%,rgba(11,11,12,0.48)_38%,rgba(11,11,12,0.92)_100%)]"></div>
                        @else
                            <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(251,191,36,0.15),rgba(120,53,15,0.28),rgba(5,5,5,0.92))]"></div>
                        @endif

                        <div class="relative flex min-h-[18rem] flex-col justify-end gap-6 p-6 sm:min-h-[22rem] sm:p-8 lg:min-h-[26rem] lg:px-12 lg:py-12 xl:px-16">
                            <div class="max-w-4xl">
                                <p class="text-xs uppercase tracking-[0.35em] text-[#c8ac75]">Storybox Room</p>
                                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-[#fff2cc] sm:text-4xl lg:text-5xl">{{ $room->name }}</h1>

                                @if ($profileSummary !== '')
                                    <div class="mt-5 max-w-3xl text-base leading-7 text-[#f0e4cb] sm:text-lg sm:leading-8 lg:text-[1.18rem]">
                                        {{ $profileSummary }}
                                    </div>
                                @else
                                    <p class="mt-5 max-w-3xl text-base leading-7 text-[#d2c4a6] sm:text-lg sm:leading-8">
                                        This room profile has not been configured yet. Check back later, or add the first summary and joining information from the profile editor and manage official rules from the Rules tool if you run this room.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($hasRoomProfile)
                        <div class="grid gap-8 border-t border-[#3a2f1e] px-6 py-8 sm:px-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:px-12 lg:py-10 xl:px-16">
                            @if ($profileJoiningInformation !== '')
                                <section>
                                    <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-[#b89f70]">Joining Information</h2>
                                    <div class="mt-4 whitespace-pre-line text-[15px] leading-7 text-[#ebdfc6] sm:text-base sm:leading-8">
                                        {{ $profileJoiningInformation }}
                                    </div>
                                </section>
                            @endif

                            @if ($roomRules->isNotEmpty())
                                <section>
                                    <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-[#b89f70]">Rules</h2>
                                    <div class="mt-4 space-y-5">
                                        @foreach ($roomRules as $index => $rule)
                                            <article class="pb-5 {{ $loop->last ? '' : 'border-b border-[#3a2f1e]' }}">
                                                <div class="flex items-start gap-4">
                                                    <div class="pt-0.5 text-base font-semibold text-[#d0ae68] sm:text-lg">{{ $index + 1 }}.</div>
                                                    <div class="min-w-0 flex-1">
                                                        <h3 class="text-lg font-semibold text-[#f7e8c1] sm:text-xl">{{ $rule->title }}</h3>
                                                        <div class="mt-3 whitespace-pre-line text-[15px] leading-7 text-[#dbcdb2] sm:text-base sm:leading-8">{{ $rule->body }}</div>
                                                    </div>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                </section>
                            @endif
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </body>
</html>
