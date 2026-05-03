<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-200 leading-tight">
            Characters
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-none w-full mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 rounded bg-gray-900 border border-gray-800 px-4 py-2 text-sm text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Create character --}}
                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-100 mb-3">Create Character</h3>

                    <form method="POST" action="{{ route('characters.store') }}">
                        @csrf
                        <input
                            name="name"
                            placeholder="Character name"
                            class="w-full rounded bg-gray-950 border border-gray-800 px-3 py-2 text-gray-100"
                            required
                        />
                        @error('name')
                            <div class="text-sm text-red-400 mt-2">{{ $message }}</div>
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
                            <div class="text-sm text-red-400 mt-2">{{ $message }}</div>
                        @enderror

                        <div class="mt-3">
                            <x-primary-button>Create</x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- List --}}
                <div class="bg-gray-900 border border-gray-800 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-100 mb-3">Your Characters</h3>

                    <div class="space-y-4">
                        @foreach ($characters as $char)
                            @php
                                $s = $char->settings ?? [];
                                $c1 = $s['text_color_1'] ?? '#D8F3FF';
                                $c2 = $s['text_color_2'] ?? '#000000';
                                $c3 = $s['text_color_3'] ?? '#000000';
                                $c4 = $s['text_color_4'] ?? '#000000';
                                $fadeMsg = (bool) ($s['fade_message'] ?? false);
                                $fadeName = (bool) ($s['fade_name'] ?? false);
                                $avatar = $char->externalAvatarUrl();
                            @endphp

                            <div class="border border-gray-800 rounded-lg p-3">
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
                                                @if ($activeId === $char->id)
                                                    <span class="ml-2 text-xs text-teal-300">(active)</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('characters.switch', $char) }}">
                                        @csrf
                                        <x-primary-button>Use</x-primary-button>
                                    </form>
                                </div>

                                {{-- Style editor --}}
                                <form method="POST" action="{{ route('characters.style', $char) }}" class="mt-3">
                                    @csrf

                                    <div class="text-xs text-gray-400 mb-2">
                                        Style (Color 1 required. Color 2+ enable fades.)
                                    </div>

                                    <label class="mb-3 block text-xs text-gray-300">
                                        External avatar URL
                                        <input type="url"
                                               name="avatar"
                                               maxlength="2048"
                                               value="{{ old('avatar', $char->avatar) }}"
                                               placeholder="https://example.com/avatar.png"
                                               class="mt-1 w-full rounded bg-gray-950 border border-gray-800 px-3 py-2 text-sm text-gray-100 placeholder:text-gray-600" />
                                        <span class="mt-1 block text-[11px] text-gray-500">Use an externally hosted image URL. HTTPS is preferred.</span>
                                        @error('avatar')
                                            <span class="mt-1 block text-sm text-red-400">{{ $message }}</span>
                                        @enderror
                                    </label>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <label class="text-xs text-gray-300">
                                            Color 1
                                            <input type="color" name="text_color_1"
                                                   value="{{ $c1 }}"
                                                   class="mt-1 w-full h-10 rounded bg-gray-950 border border-gray-800" />
                                        </label>

                                        <label class="text-xs text-gray-300">
                                            Color 2
                                            <input type="color" name="text_color_2"
                                                   value="{{ $c2 }}"
                                                   class="mt-1 w-full h-10 rounded bg-gray-950 border border-gray-800" />
                                        </label>

                                        <label class="text-xs text-gray-300">
                                            Color 3
                                            <input type="color" name="text_color_3"
                                                   value="{{ $c3 }}"
                                                   class="mt-1 w-full h-10 rounded bg-gray-950 border border-gray-800" />
                                        </label>

                                        <label class="text-xs text-gray-300">
                                            Color 4
                                            <input type="color" name="text_color_4"
                                                   value="{{ $c4 }}"
                                                   class="mt-1 w-full h-10 rounded bg-gray-950 border border-gray-800" />
                                        </label>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-200">
                                        <label class="inline-flex items-center gap-2">
                                            <input type="checkbox" name="fade_message" value="1"
                                                   class="rounded border-gray-700 bg-gray-950"
                                                   {{ $fadeMsg ? 'checked' : '' }}>
                                            Fade message text
                                        </label>

                                        <label class="inline-flex items-center gap-2">
                                            <input type="checkbox" name="fade_name" value="1"
                                                   class="rounded border-gray-700 bg-gray-950"
                                                   {{ $fadeName ? 'checked' : '' }}>
                                            Fade name
                                        </label>
                                    </div>

                                    <div class="mt-3">
                                        <x-primary-button>Save Style</x-primary-button>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
