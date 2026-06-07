<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-[#f2dfb5]">Profile Revisions</h2>
                <p class="text-sm text-[#8f8675]">{{ $character->name }} • {{ $character->public_handle }}</p>
            </div>
            <a href="{{ route('characters.profile.edit', $character) }}" class="inline-flex items-center rounded border border-[#5a431f] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5] hover:bg-[#191511]">Back to Editor</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
            @endif

            <div class="space-y-4">
                @forelse ($revisions as $revision)
                    <div class="rounded-2xl border border-[#2a241a] bg-[#0b0b0c] p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.25em] text-[#b89f70]">Saved {{ $revision->created_at?->format('M j, Y g:i A') }}</h3>
                                <p class="mt-2 text-sm text-[#8f8675]">HTML {{ filled($revision->custom_html) ? 'present' : 'empty' }} • CSS {{ filled($revision->custom_css) ? 'present' : 'empty' }} • JS {{ filled($revision->custom_js) ? 'present' : 'empty' }}</p>
                            </div>

                            <form method="POST" action="{{ route('characters.profile.revisions.restore', [$character, $revision]) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-500/20">Restore Revision</button>
                            </form>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-3">
                            <div>
                                <h4 class="mb-2 text-sm font-semibold text-[#f2dfb5]">HTML</h4>
                                <pre class="max-h-64 overflow-auto rounded-xl border border-[#2a241a] bg-[#111112] p-3 text-xs text-[#d6c8ad]">{{ $revision->custom_html ?: '—' }}</pre>
                            </div>
                            <div>
                                <h4 class="mb-2 text-sm font-semibold text-[#f2dfb5]">CSS</h4>
                                <pre class="max-h-64 overflow-auto rounded-xl border border-[#2a241a] bg-[#111112] p-3 text-xs text-[#d6c8ad]">{{ $revision->custom_css ?: '—' }}</pre>
                            </div>
                            <div>
                                <h4 class="mb-2 text-sm font-semibold text-[#f2dfb5]">JavaScript</h4>
                                <pre class="max-h-64 overflow-auto rounded-xl border border-[#2a241a] bg-[#111112] p-3 text-xs text-[#d6c8ad]">{{ $revision->custom_js ?: '—' }}</pre>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#2a241a] bg-[#0b0b0c] px-5 py-8 text-sm text-[#8f8675]">
                        No custom profile revisions have been saved yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
