<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-[#f2dfb5]">Edit Room Profile</h2>
                <p class="text-sm text-[#8f8675]">{{ $room->name }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('rooms.profile.show', $room->slug) }}" class="inline-flex items-center rounded border border-[#5a431f] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5] hover:bg-[#191511]">View Room Profile</a>
                <a href="{{ route('rooms.show', ['room' => $room->slug, 'tool' => 'settings']) }}" class="inline-flex items-center rounded border border-[#5a431f] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5] hover:bg-[#191511]">Back to Room</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php($selectedProfileMode = old('profile_mode', $room->profileMode()))

            <form method="POST" action="{{ route('rooms.profile.update', $room->slug) }}" class="space-y-6">
                @csrf
                @method('PATCH')
                <input type="hidden" name="character_id" value="{{ $activeCharacterId }}">

                <section class="rounded-2xl border border-[#2a241a] bg-[#0b0b0c] p-6">
                    <h3 class="text-lg font-semibold text-[#f2dfb5]">Profile Mode</h3>
                    <p class="mt-1 text-sm text-[#8f8675]">Choose whether this room uses the standard Storybox layout or a fullscreen advanced profile document.</p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#332817] bg-[#141416] p-4 text-sm text-[#d6c8ad]">
                            <input type="radio" name="profile_mode" value="{{ \App\Models\Room::PROFILE_MODE_STANDARD }}" class="mt-1 border-[#5a431f] bg-[#141416] text-amber-500 focus:ring-amber-500" {{ $selectedProfileMode === \App\Models\Room::PROFILE_MODE_STANDARD ? 'checked' : '' }}>
                            <span>
                                <span class="block font-semibold text-[#f2dfb5]">Standard Profile</span>
                                <span class="mt-1 block text-xs leading-relaxed text-[#8f8675]">Uses the existing banner, summary, joining information, and rules layout.</span>
                            </span>
                        </label>

                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#332817] bg-[#141416] p-4 text-sm text-[#d6c8ad]">
                            <input type="radio" name="profile_mode" value="{{ \App\Models\Room::PROFILE_MODE_ADVANCED }}" class="mt-1 border-[#5a431f] bg-[#141416] text-amber-500 focus:ring-amber-500" {{ $selectedProfileMode === \App\Models\Room::PROFILE_MODE_ADVANCED ? 'checked' : '' }}>
                            <span>
                                <span class="block font-semibold text-[#f2dfb5]">Advanced Profile</span>
                                <span class="mt-1 block text-xs leading-relaxed text-[#8f8675]">Renders custom HTML, CSS, and JavaScript inside the fullscreen room profile shell.</span>
                            </span>
                        </label>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#2a241a] bg-[#0b0b0c] p-6">
                    <h3 class="text-lg font-semibold text-[#f2dfb5]">Standard Profile</h3>
                    <p class="mt-1 text-sm text-[#8f8675]">Public-facing room information. Images must be externally hosted URLs.</p>

                    <label class="mt-5 block text-sm text-[#d6c8ad]">
                        Banner Image URL
                        <input
                            type="url"
                            name="profile_banner_url"
                            value="{{ old('profile_banner_url', $room->profile_banner_url) }}"
                            maxlength="2048"
                            placeholder="https://example.com/banner.jpg"
                            class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]"
                        >
                    </label>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Summary
                        <textarea
                            name="profile_summary"
                            rows="7"
                            maxlength="4000"
                            class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]"
                        >{{ old('profile_summary', $room->profile_summary) }}</textarea>
                    </label>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Joining Information
                        <textarea
                            name="profile_joining_information"
                            rows="7"
                            maxlength="4000"
                            class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]"
                        >{{ old('profile_joining_information', $room->profile_joining_information) }}</textarea>
                    </label>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Rules
                        <textarea
                            name="profile_rules"
                            rows="8"
                            maxlength="4000"
                            class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]"
                        >{{ old('profile_rules', $room->profile_rules) }}</textarea>
                    </label>
                </section>

                <section class="rounded-2xl border border-[#2a241a] bg-[#0b0b0c] p-6">
                    <h3 class="text-lg font-semibold text-[#f2dfb5]">Advanced Profile</h3>
                    <p class="mt-1 text-sm text-[#8f8675]">Custom code for fullscreen room profiles. This section is grouped so it can be collapsed or minimized in a follow-up pass without touching Room Settings.</p>

                    <label class="mt-5 block text-sm text-[#d6c8ad]">
                        Custom HTML
                        <textarea
                            name="profile_custom_html"
                            rows="10"
                            class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 font-mono text-sm text-[#f2dfb5]"
                        >{{ old('profile_custom_html', $room->profile_custom_html) }}</textarea>
                    </label>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Custom CSS
                        <textarea
                            name="profile_custom_css"
                            rows="10"
                            class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 font-mono text-sm text-[#f2dfb5]"
                        >{{ old('profile_custom_css', $room->profile_custom_css) }}</textarea>
                    </label>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Custom JavaScript
                        <textarea
                            name="profile_custom_js"
                            rows="10"
                            class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 font-mono text-sm text-[#f2dfb5]"
                        >{{ old('profile_custom_js', $room->profile_custom_js) }}</textarea>
                    </label>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('rooms.profile.show', $room->slug) }}" class="text-sm text-[#b89f70] hover:text-[#f2dfb5]">Cancel</a>
                    <button type="submit" class="inline-flex items-center rounded border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-500/20">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
