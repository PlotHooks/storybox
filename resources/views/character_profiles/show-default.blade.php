<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $character->name }} Profile | Storybox</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#050505] text-[#e9dcc2] antialiased">
        @php
            $avatarUrl = $profile->avatar_url ?: $character->externalAvatarUrl();
            $bannerUrl = $profile->banner_url;
            $externalLinks = $profile->external_links ?? [];
            $canEdit = auth()->check() && (auth()->user()->is_admin || Gate::allows('own-character', $character));
        @endphp

        <div class="min-h-screen bg-[radial-gradient(circle_at_top,#2b1f10_0%,#050505_55%)]">
            <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-3xl border border-[#3a2f1e] bg-[#0b0b0c]/90 shadow-[0_30px_80px_rgba(0,0,0,0.45)]">
                    <div class="relative min-h-[220px] border-b border-[#3a2f1e] bg-[#15100c]">
                        @if ($bannerUrl)
                            <img src="{{ $bannerUrl }}" alt="{{ $character->name }} banner" class="absolute inset-0 h-full w-full object-cover" referrerpolicy="no-referrer">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0b0b0c] via-[#0b0b0c]/60 to-[#0b0b0c]/20"></div>
                        @else
                            <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(251,191,36,0.15),rgba(120,53,15,0.28),rgba(5,5,5,0.92))]"></div>
                        @endif

                        <div class="relative flex h-full min-h-[220px] flex-col justify-end gap-6 p-6 sm:p-8">
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                                <div class="flex items-end gap-4">
                                    @if ($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="{{ $character->name }} avatar" class="h-24 w-24 rounded-2xl border border-[#5a431f] object-cover shadow-lg sm:h-28 sm:w-28" referrerpolicy="no-referrer">
                                    @else
                                        <div class="flex h-24 w-24 items-center justify-center rounded-2xl border border-[#5a431f] bg-[#120f0c] text-3xl font-semibold text-[#f2dfb5] sm:h-28 sm:w-28">
                                            {{ strtoupper(substr($character->name, 0, 1)) }}
                                        </div>
                                    @endif

                                    <div>
                                        <p class="text-xs uppercase tracking-[0.35em] text-[#b89f70]">Character Profile</p>
                                        <h1 class="mt-2 text-3xl font-semibold text-[#fff2cc] sm:text-4xl">{{ $character->name }}</h1>
                                        <p class="mt-2 text-sm text-[#d2c4a6]">{{ $profile->tagline ?: 'A Storybox character showcase.' }}</p>
                                        <p class="mt-2 text-xs text-[#9d8c6b]">{{ $character->public_handle }}</p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    @if ($isPreview ?? false)
                                        <span class="rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-200">Previewing unsaved changes</span>
                                    @endif
                                    @if ($canEdit)
                                        <a href="{{ route('characters.profile.edit', $character) }}" class="inline-flex items-center rounded-full border border-[#5a431f] bg-[#171311] px-4 py-2 text-sm text-[#f2dfb5] hover:bg-[#1d1713]">Edit Profile</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-8 px-6 py-8 sm:px-8 lg:grid-cols-[minmax(0,2fr)_minmax(16rem,1fr)]">
                        <div class="space-y-8">
                            <section>
                                <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-[#b89f70]">Biography</h2>
                                <div class="mt-3 whitespace-pre-line text-[15px] leading-7 text-[#ebdfc6]">
                                    {{ $profile->biography ?: 'No biography has been written for this character yet.' }}
                                </div>
                            </section>

                            <section>
                                <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-[#b89f70]">Hooks / RP Information</h2>
                                <div class="mt-3 whitespace-pre-line text-[15px] leading-7 text-[#d7c8aa]">
                                    {{ $profile->hooks ?: 'No hooks have been listed yet.' }}
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-6">
                            <section class="rounded-2xl border border-[#2f2518] bg-[#120f0d] p-5">
                                <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-[#b89f70]">Storybox</h2>
                                <dl class="mt-4 space-y-3 text-sm">
                                    <div>
                                        <dt class="text-[#8f8675]">Character</dt>
                                        <dd class="text-[#f2dfb5]">{{ $character->name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#8f8675]">Handle</dt>
                                        <dd class="text-[#f2dfb5]">{{ $character->public_handle }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[#8f8675]">Template</dt>
                                        <dd class="text-[#f2dfb5]">{{ ucfirst($profile->template_type ?: 'storybox') }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="rounded-2xl border border-[#2f2518] bg-[#120f0d] p-5">
                                <h2 class="text-sm font-semibold uppercase tracking-[0.3em] text-[#b89f70]">External Links</h2>
                                <div class="mt-4 space-y-3">
                                    @forelse ($externalLinks as $link)
                                        <a href="{{ $link['url'] }}" target="_blank" rel="noreferrer noopener" class="block rounded-xl border border-[#3a2f1e] bg-[#171311] px-4 py-3 text-sm text-[#f2dfb5] hover:border-amber-500/50 hover:bg-[#1d1713]">
                                            <div class="font-medium">{{ $link['label'] }}</div>
                                            <div class="mt-1 truncate text-xs text-[#9d8c6b]">{{ $link['url'] }}</div>
                                        </a>
                                    @empty
                                        <p class="text-sm text-[#8f8675]">No external links listed.</p>
                                    @endforelse
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
