<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-[#f2dfb5]">Edit Character Profile</h2>
                <p class="text-sm text-[#8f8675]">{{ $character->name }} • {{ $character->public_handle }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('characters.profile.show', $character) }}" target="_blank" rel="noreferrer" class="inline-flex items-center rounded border border-[#5a431f] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5] hover:bg-[#191511]">View Public Profile</a>
                <a href="{{ route('characters.profile.revisions', $character) }}" class="inline-flex items-center rounded border border-[#5a431f] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5] hover:bg-[#191511]">Revision History</a>
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

            @if ($profile->custom_profile_disabled_by_admin)
                <div class="mb-4 rounded border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    Custom rendering is currently disabled by an administrator. You can keep editing, but the public profile will fall back to the default Storybox template until an admin re-enables custom rendering.
                </div>
            @endif

            <form method="POST" action="{{ route('characters.profile.update', $character) }}" class="space-y-6" id="character-profile-form">
                @csrf
                <input type="hidden" name="template_type" value="{{ old('template_type', $profile->template_type ?: \App\Models\CharacterProfile::TEMPLATE_STORYBOX) }}">

                <section class="rounded-2xl border border-[#2a241a] bg-[#0b0b0c] p-6">
                    <h3 class="text-lg font-semibold text-[#f2dfb5]">Basic Profile</h3>
                    <p class="mt-1 text-sm text-[#8f8675]">Public character showcase content. Images must be externally hosted URLs.</p>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="block text-sm text-[#d6c8ad]">
                            Avatar URL
                            <input type="url" name="avatar_url" value="{{ old('avatar_url', $profile->avatar_url) }}" maxlength="2048" placeholder="https://example.com/avatar.png" class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]">
                        </label>

                        <label class="block text-sm text-[#d6c8ad]">
                            Banner URL
                            <input type="url" name="banner_url" value="{{ old('banner_url', $profile->banner_url) }}" maxlength="2048" placeholder="https://example.com/banner.jpg" class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]">
                        </label>
                    </div>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Tagline
                        <input type="text" name="tagline" value="{{ old('tagline', $profile->tagline) }}" maxlength="255" class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]">
                    </label>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Biography
                        <textarea name="biography" rows="7" class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]">{{ old('biography', $profile->biography) }}</textarea>
                    </label>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Hooks / RP Information
                        <textarea name="hooks" rows="6" class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]">{{ old('hooks', $profile->hooks) }}</textarea>
                    </label>

                    @php
                        $linkRows = old('external_links', $externalLinks);
                    @endphp
                    <div class="mt-4" x-data="{ links: {{ \Illuminate\Support\Js::from($linkRows) }} }">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-[#f2dfb5]">External Links</h4>
                            <button type="button" @click="links.push({ label: '', url: '' })" class="rounded border border-[#5a431f] px-3 py-1.5 text-xs text-[#f2dfb5] hover:bg-[#191511]">Add Link</button>
                        </div>

                        <div class="mt-3 space-y-3">
                            <template x-for="(link, index) in links" :key="index">
                                <div class="grid gap-3 rounded-xl border border-[#2a241a] bg-[#111112] p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto]">
                                    <input :name="`external_links[${index}][label]`" x-model="link.label" type="text" maxlength="100" placeholder="Label" class="w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]">
                                    <input :name="`external_links[${index}][url]`" x-model="link.url" type="url" maxlength="2048" placeholder="https://example.com" class="w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 text-sm text-[#f2dfb5]">
                                    <button type="button" @click="links.splice(index, 1)" class="rounded border border-red-500/40 px-3 py-2 text-xs text-red-200 hover:bg-red-500/10">Remove</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-[#2a241a] bg-[#0b0b0c] p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-[#f2dfb5]">Advanced Profile</h3>
                            <p class="mt-1 text-sm text-[#8f8675]">Custom HTML, CSS, and JavaScript are rendered only inside a sandboxed iframe.</p>
                        </div>
                        <label class="inline-flex items-center gap-2 rounded-full border border-[#5a431f] bg-[#141416] px-4 py-2 text-sm text-[#f2dfb5]">
                            <input type="checkbox" name="custom_profile_enabled" value="1" class="rounded border-[#5a431f] bg-[#141416]" {{ old('custom_profile_enabled', $profile->custom_profile_enabled) ? 'checked' : '' }}>
                            Enable custom profile
                        </label>
                    </div>

                    <label class="mt-5 block text-sm text-[#d6c8ad]">
                        Custom HTML
                        <textarea name="custom_html" rows="10" class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 font-mono text-sm text-[#f2dfb5]">{{ old('custom_html', $profile->custom_html) }}</textarea>
                    </label>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Custom CSS
                        <textarea name="custom_css" rows="10" class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 font-mono text-sm text-[#f2dfb5]">{{ old('custom_css', $profile->custom_css) }}</textarea>
                    </label>

                    <label class="mt-4 block text-sm text-[#d6c8ad]">
                        Custom JavaScript
                        <textarea name="custom_js" rows="10" class="mt-1 w-full rounded border border-[#332817] bg-[#141416] px-3 py-2 font-mono text-sm text-[#f2dfb5]">{{ old('custom_js', $profile->custom_js) }}</textarea>
                    </label>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('characters.manage', $character) }}" class="text-sm text-[#b89f70] hover:text-[#f2dfb5]">Back to Character Management</a>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" formaction="{{ route('characters.profile.preview', $character) }}" formtarget="_blank" class="inline-flex items-center rounded border border-[#5a431f] bg-[#141416] px-4 py-2 text-sm text-[#f2dfb5] hover:bg-[#191511]">Preview</button>
                        <button type="submit" class="inline-flex items-center rounded border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-500/20">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
