@php
    $panelMode = $panelMode ?? false;
    $returnTo = request()->routeIs('rooms.*')
        ? request()->fullUrlWithQuery(['characters' => 1])
        : route('characters.index');
@endphp

@if (session('status'))
    <div class="mb-4 rounded border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-200">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-200">
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 gap-6 {{ $panelMode ? 'xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]' : 'lg:grid-cols-2' }}">
    <div class="rounded-lg border border-gray-800 bg-gray-900 p-4">
        <h3 class="mb-3 text-lg font-semibold text-gray-100">Create Character</h3>

        <form method="POST" action="{{ route('characters.store') }}">
            @csrf
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
            <input
                name="name"
                value="{{ old('name') }}"
                placeholder="Character name"
                class="w-full rounded bg-gray-950 border border-gray-800 px-3 py-2 text-gray-100"
                required
            />
            @error('name')
                <div class="mt-2 text-sm text-red-400">{{ $message }}</div>
            @enderror

            <input
                type="url"
                name="avatar"
                maxlength="2048"
                placeholder="External avatar URL (https preferred)"
                value="{{ old('avatar') }}"
                class="mt-3 w-full rounded bg-gray-950 border border-gray-800 px-3 py-2 text-gray-100 placeholder:text-gray-600"
            />
            <div class="mt-1 text-xs text-gray-500">Externally hosted image only. No uploads.</div>
            @error('avatar')
                <div class="mt-2 text-sm text-red-400">{{ $message }}</div>
            @enderror

            <div class="mt-3">
                <x-primary-button>Create</x-primary-button>
            </div>
        </form>
    </div>

    <div class="rounded-lg border border-gray-800 bg-gray-900 p-4">
        <h3 class="mb-3 text-lg font-semibold text-gray-100">Your Characters</h3>

        <div class="space-y-4">
            @forelse ($characters as $char)
                @php
                    $s = $char->settings ?? [];
                    $c1 = $s['text_color_1'] ?? '#D8F3FF';
                    $c2 = $s['text_color_2'] ?? '#000000';
                    $c3 = $s['text_color_3'] ?? '#000000';
                    $c4 = $s['text_color_4'] ?? '#000000';
                    $fadeMsg = (bool) ($s['fade_message'] ?? false);
                    $fadeName = (bool) ($s['fade_name'] ?? false);
                    $avatar = $char->externalAvatarUrl();
                    $isActiveCharacter = (int) $activeId === (int) $char->id;
                @endphp

                <div class="rounded-lg border {{ $isActiveCharacter ? 'border-amber-500/40 bg-amber-500/5' : 'border-gray-800' }} p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            @if ($avatar)
                                <img src="{{ $avatar }}"
                                     alt="{{ $char->name }} avatar"
                                     loading="lazy"
                                     referrerpolicy="no-referrer"
                                     class="h-14 w-14 shrink-0 rounded-lg object-cover">
                            @else
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg border border-gray-800 bg-gray-950 text-lg font-semibold text-gray-500">
                                    {{ strtoupper(substr($char->name, 0, 1)) }}
                                </div>
                            @endif

                            <div class="min-w-0">
                                <div class="truncate text-gray-100 font-semibold">
                                    {{ $char->name }}
                                    @if ($isActiveCharacter)
                                        <span class="ml-2 rounded border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[11px] text-amber-200">Active</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-gray-500">{{ $char->public_handle }}</div>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if (! $isActiveCharacter)
                                <form method="POST" action="{{ route('characters.switch', $char) }}">
                                    @csrf
                                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                    <x-primary-button>Use</x-primary-button>
                                </form>
                            @endif

                            @if (! $isActiveCharacter)
                                <form method="POST" action="{{ route('characters.destroy', $char) }}" onsubmit="return confirm('Delete this character? This also removes their messages, presence, DM participation, and related room links.');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                    <button type="submit" class="inline-flex items-center rounded border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs font-semibold text-red-200 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500/40">
                                        Delete
                                    </button>
                                </form>
                            @else
                                <span class="text-[11px] text-gray-500">Switch away to delete</span>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('characters.style', $char) }}" class="mt-3 border-t border-gray-800 pt-3">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ $returnTo }}">

                        <div class="mb-2 text-xs text-gray-400">
                            Character details and style.
                        </div>

                        <label class="mb-3 block text-xs text-gray-300">
                            Name
                            <input type="text"
                                   name="name"
                                   maxlength="100"
                                   value="{{ old('name', $char->name) }}"
                                   class="mt-1 w-full rounded bg-gray-950 border border-gray-800 px-3 py-2 text-sm text-gray-100" />
                        </label>

                        <label class="mb-3 block text-xs text-gray-300">
                            External avatar URL
                            <input type="url"
                                   name="avatar"
                                   maxlength="2048"
                                   value="{{ old('avatar', $char->avatar) }}"
                                   placeholder="https://example.com/avatar.png"
                                   class="mt-1 w-full rounded bg-gray-950 border border-gray-800 px-3 py-2 text-sm text-gray-100 placeholder:text-gray-600" />
                            <span class="mt-1 block text-[11px] text-gray-500">Use an externally hosted image URL. HTTPS is preferred.</span>
                        </label>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <label class="text-xs text-gray-300">
                                Color 1
                                <input type="color" name="text_color_1" value="{{ $c1 }}" class="mt-1 h-10 w-full rounded border border-gray-800 bg-gray-950" />
                            </label>

                            <label class="text-xs text-gray-300">
                                Color 2
                                <input type="color" name="text_color_2" value="{{ $c2 }}" class="mt-1 h-10 w-full rounded border border-gray-800 bg-gray-950" />
                            </label>

                            <label class="text-xs text-gray-300">
                                Color 3
                                <input type="color" name="text_color_3" value="{{ $c3 }}" class="mt-1 h-10 w-full rounded border border-gray-800 bg-gray-950" />
                            </label>

                            <label class="text-xs text-gray-300">
                                Color 4
                                <input type="color" name="text_color_4" value="{{ $c4 }}" class="mt-1 h-10 w-full rounded border border-gray-800 bg-gray-950" />
                            </label>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-200">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="fade_message" value="1" class="rounded border-gray-700 bg-gray-950" {{ $fadeMsg ? 'checked' : '' }}>
                                Fade message text
                            </label>

                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="fade_name" value="1" class="rounded border-gray-700 bg-gray-950" {{ $fadeName ? 'checked' : '' }}>
                                Fade name
                            </label>
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-3">
                            <a href="{{ route('characters.show', $char) }}" class="text-xs text-amber-300 hover:text-amber-200">Open full profile page</a>
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="rounded border border-dashed border-gray-800 px-3 py-4 text-sm text-gray-500">
                    No characters yet.
                </div>
            @endforelse
        </div>
    </div>
</div>
