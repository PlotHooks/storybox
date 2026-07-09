{{-- resources/views/rooms/show.blade.php --}}
<x-app-layout>

    <style>
        .msg-rich-underline {
            text-decoration: underline;
        }

        .msg-rich-strike {
            text-decoration: line-through;
        }

        .msg-rich-small {
            font-size: 0.85em;
        }

        .msg-rich-large {
            font-size: 1.15em;
        }
    </style>

    <div class="box-border h-[calc(100dvh-6.5rem)] sm:h-[calc(100dvh-4rem)] min-h-0 overflow-hidden py-4 bg-[#070707]">
        <div class="max-w-none w-full mx-auto h-full min-h-0 overflow-hidden flex flex-col lg:flex-row gap-3 px-2 md:px-4">

            {{-- LEFT COLUMN --}}
            <div id="left-panel" class="w-full lg:w-72 min-h-0 bg-[#0b0b0c] text-[#d6c8ad] rounded-md shadow-2xl flex flex-col border border-[#2a241a] overflow-hidden">
                <div class="px-4 py-3 border-b border-[#2a241a] bg-[#101012]">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-amber-400">Context Dock</div>
                            <div class="mt-1 text-sm font-semibold text-[#f2dfb5]">Room tools</div>
                        </div>
                    </div>
                </div>
                <div class="border-b border-[#2a241a] bg-[#0b0b0c] p-2 space-y-2">
                    <div>
                        <div class="px-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Room Tools</div>
                        <div class="mt-1 grid grid-cols-2 gap-1 text-[11px] font-medium text-[#d6c8ad]">
                            <a href="{{ route('rooms.profile.show', $room->slug) }}" target="_blank" rel="noreferrer" class="rounded border border-[#332817] bg-[#141416] px-2 py-1.5 text-left text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Room Profile</a>
                            @if ($room->isPublicRoom())
                                <button type="button" id="open-rules-btn" class="room-window-tool-btn rounded border border-[#332817] bg-[#141416] px-2 py-1.5 text-left text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Rules</button>
                            @else
                                <button type="button" disabled aria-disabled="true" class="rounded border border-dashed border-[#332817] bg-[#101012] px-2 py-1.5 text-left text-[#6f675b] cursor-not-allowed opacity-70">Rules</button>
                            @endif
                            @if ($room->isPublicRoom())
                                <button type="button" id="open-world-book-btn" class="room-window-tool-btn rounded border border-[#332817] bg-[#141416] px-2 py-1.5 text-left text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]{{ !empty($roomToolIndicators['world_book']) ? ' room-tool-update-glow' : '' }}">World Book</button>
                            @else
                                <button type="button" disabled aria-disabled="true" class="rounded border border-dashed border-[#332817] bg-[#101012] px-2 py-1.5 text-left text-[#6f675b] cursor-not-allowed opacity-70">World Book</button>
                            @endif
                            @if ($room->isPublicRoom())
                                <button type="button" id="open-notice-board-btn" class="room-window-tool-btn rounded border border-[#332817] bg-[#141416] px-2 py-1.5 text-left text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]{{ !empty($roomToolIndicators['notice_board']) ? ' room-tool-update-glow' : '' }}">Notice Board</button>
                            @else
                                <button
                                    type="button"
                                    disabled
                                    aria-disabled="true"
                                    class="rounded border border-dashed border-[#332817] bg-[#101012] px-2 py-1.5 text-left text-[#6f675b] cursor-not-allowed opacity-70"
                                >
                                    Notice Board
                                </button>
                            @endif
                            @if ($room->isPublicRoom())
                                <button type="button" id="open-pinned-notes-btn" class="room-window-tool-btn rounded border border-[#332817] bg-[#141416] px-2 py-1.5 text-left text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]{{ !empty($roomToolIndicators['pinned_notes']) ? ' room-tool-update-glow' : '' }}">Pinned Notes</button>
                            @else
                                <button type="button" disabled aria-disabled="true" class="rounded border border-dashed border-[#332817] bg-[#101012] px-2 py-1.5 text-left text-[#6f675b] cursor-not-allowed opacity-70">Pinned Notes</button>
                            @endif
                            @if ($room->isPublicRoom())
                                <a href="{{ route('rooms.history.show', $room->slug) }}" target="_blank" rel="noreferrer" class="rounded border border-[#332817] bg-[#141416] px-2 py-1.5 text-left text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Room History</a>
                            @endif
                        </div>
                    </div>
                    @if ($room->isPublicRoom() && $canManageRoom && $activeCharacterId)
                        <div>
                            <div class="px-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Owner Tools</div>
                            <div class="mt-1 grid grid-cols-2 gap-1 text-[11px] font-medium text-[#d6c8ad]">
                                <button type="button" data-context-tool="settings" class="context-tool-btn rounded border border-[#332817] bg-[#141416] px-2 py-1.5 text-left text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Room Settings</button>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto px-4 py-4 text-xs text-[#d6c8ad]">
                    @if (session('status'))
                        <div class="mb-3 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-[11px] text-emerald-200">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any() && old('context_tool') === 'settings')
                        <div class="mb-3 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-[11px] text-red-200">
                            <div class="font-semibold uppercase tracking-[0.14em]">Room Settings Error</div>
                            <ul class="mt-2 space-y-1 text-[11px] text-red-100">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($room->isPublicRoom())
                        <div class="rounded-md border border-[#332817] bg-[#101012] p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-[#f2dfb5]">Follow Room</h3>
                                    <p class="mt-2 text-[11px] leading-relaxed text-[#8f8675]">
                                        Only followed rooms show unread indicators in your room list.
                                    </p>
                                </div>
                                <a href="{{ route('rooms.history.show', $room->slug) }}" target="_blank" rel="noreferrer" class="inline-flex items-center rounded border border-[#332817] bg-[#141416] px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">
                                    View History
                                </a>
                            </div>
                            <form method="POST" action="{{ route('rooms.follow', $room->slug) }}" class="mt-3">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="follow" value="0">
                                <label class="flex items-center gap-2 text-[11px] font-semibold text-[#d6c8ad]">
                                    <input
                                        type="checkbox"
                                        name="follow"
                                        value="1"
                                        {{ $isFollowingRoom ? 'checked' : '' }}
                                        onchange="this.form.submit()"
                                        class="rounded border-[#332817] bg-[#0b0b0c] text-amber-500 focus:ring-amber-500"
                                    >
                                    <span>Follow this room</span>
                                </label>
                            </form>
                        </div>
                    @endif
                    @if ($room->isPublicRoom() && $canManageRoom && $activeCharacterId)
                        <div data-context-panel="settings" class="context-tool-panel hidden space-y-3">
                            <div class="rounded-md border border-[#332817] bg-[#101012] p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-[#f2dfb5]">Room Settings</h3>
                                    <span class="text-[10px] uppercase tracking-[0.18em] text-amber-400">
                                        {{ $canManageModerators ? 'Owner/Admin' : 'Moderator' }}
                                    </span>
                                </div>

                                <div class="mt-3 text-[10px] uppercase tracking-[0.16em] text-[#8f8675]">Basic</div>
                                    <form method="POST" action="{{ route('rooms.update', ['room' => $room->slug, 'tool' => 'settings']) }}" class="mt-2 space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="character_id" value="{{ $activeCharacterId }}">
                                        <input type="hidden" name="context_tool" value="settings">
                                    <div>
                                        <label for="room-settings-name" class="block text-[11px] font-semibold text-[#d6c8ad]">Name</label>
                                        <input
                                            id="room-settings-name"
                                            name="name"
                                            type="text"
                                            maxlength="100"
                                            value="{{ old('name', $room->name) }}"
                                            class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"
                                        >
                                    </div>
                                    <div>
                                        <label for="room-settings-description" class="block text-[11px] font-semibold text-[#d6c8ad]">Description</label>
                                        <textarea
                                            id="room-settings-description"
                                            name="description"
                                            rows="3"
                                            maxlength="1000"
                                            class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"
                                        >{{ old('description', $room->description) }}</textarea>
                                    </div>
                                    <div>
                                        <label for="room-settings-visibility" class="block text-[11px] font-semibold text-[#d6c8ad]">Visibility</label>
                                        <select
                                            id="room-settings-visibility"
                                            name="visibility"
                                            class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"
                                        >
                                            @foreach ([\App\Models\Room::VISIBILITY_PUBLIC, \App\Models\Room::VISIBILITY_HIDDEN] as $visibilityOption)
                                                <option value="{{ $visibilityOption }}" {{ old('visibility', $room->visibility ?? \App\Models\Room::VISIBILITY_PUBLIC) === $visibilityOption ? 'selected' : '' }}>
                                                    {{ ucfirst($visibilityOption) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded border border-amber-500/50 bg-amber-500/10 px-3 py-1.5 text-[11px] font-semibold text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                                    >
                                        Save
                                    </button>
                                </form>
                            </div>

                            <div class="rounded-md border border-[#332817] bg-[#101012] p-3">
                                <div class="text-[10px] uppercase tracking-[0.16em] text-[#8f8675]">Access</div>
                                <p class="mt-2 text-[11px] leading-relaxed text-[#8f8675]">
                                    Public rooms are visible and joinable by default unless banned. Hidden rooms require owner, moderator, whitelist, or admin access.
                                </p>

                                <div class="mt-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <h4 class="text-sm font-semibold text-[#f2dfb5]">Whitelist</h4>
                                        <span class="text-[10px] text-[#8f8675]">{{ $roomWhitelist->count() }} entries</span>
                                    </div>
                                    <p class="mt-1 text-[11px] leading-relaxed text-[#8f8675]">
                                        Whitelist entries grant access to hidden rooms.
                                    </p>
                                    <form method="POST" action="{{ route('rooms.whitelist.store', ['room' => $room->slug, 'tool' => 'settings']) }}" class="mt-2 flex gap-2">
                                        @csrf
                                        <input type="hidden" name="character_id" value="{{ $activeCharacterId }}">
                                        <input type="hidden" name="context_tool" value="settings">
                                        <input
                                            name="target_character_handle"
                                            type="text"
                                            maxlength="120"
                                            placeholder="Name#ABCD"
                                            class="min-w-0 flex-1 rounded-md border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"
                                        >
                                        <button
                                            type="submit"
                                            class="rounded border border-amber-500/50 bg-amber-500/10 px-2 py-1 text-[11px] font-semibold text-amber-100 hover:bg-amber-500/20"
                                        >
                                            Add
                                        </button>
                                    </form>
                                    <div class="mt-2 space-y-1">
                                        @forelse ($roomWhitelist as $entry)
                                            <div class="flex items-center justify-between gap-2 rounded border border-[#2a241a] bg-[#0b0b0c] px-2 py-1.5">
                                                <div class="min-w-0">                                                    <div class="truncate text-[11px] font-semibold text-[#d6c8ad]">{{ $entry->character->name }}</div>
                                                    <div class="text-[10px] text-[#8f8675]">{{ $entry->character->public_handle }}</div>
                                                </div>
                                                <form method="POST" action="{{ route('rooms.whitelist.destroy', ['room' => $room->slug, 'character' => $entry->character, 'tool' => 'settings']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="character_id" value="{{ $activeCharacterId }}">
                                                    <input type="hidden" name="context_tool" value="settings">
                                                    <button type="submit" class="text-[10px] font-semibold text-red-300 hover:text-red-200">Remove</button>
                                                </form>
                                            </div>
                                        @empty
                                            <div class="rounded border border-dashed border-[#332817] bg-[#0b0b0c] px-2 py-2 text-[11px] text-[#8f8675]">
                                                No entries yet.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <h4 class="text-sm font-semibold text-[#f2dfb5]">Room Ban List</h4>
                                        <span class="text-[10px] text-[#8f8675]">{{ $roomBlacklist->count() }} entries</span>
                                    </div>
                                    <p class="mt-1 text-[11px] leading-relaxed text-[#8f8675]">
                                        Room bans deny access even to public rooms. Room bans always win over whitelist except for admin override.
                                    </p>
                                    <form method="POST" action="{{ route('rooms.blacklist.store', ['room' => $room->slug, 'tool' => 'settings']) }}" class="mt-2 flex gap-2">
                                        @csrf
                                        <input type="hidden" name="character_id" value="{{ $activeCharacterId }}">
                                        <input type="hidden" name="context_tool" value="settings">
                                        <input
                                            name="target_character_handle"
                                            type="text"
                                            maxlength="120"
                                            placeholder="Name#ABCD"
                                            class="min-w-0 flex-1 rounded-md border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"
                                        >
                                        <button
                                            type="submit"
                                            class="rounded border border-red-500/40 bg-red-500/10 px-2 py-1 text-[11px] font-semibold text-red-200 hover:bg-red-500/20"
                                        >
                                            Ban from Room
                                        </button>
                                    </form>
                                    <div class="mt-2 space-y-1">
                                        @forelse ($roomBlacklist as $entry)
                                            <div class="flex items-center justify-between gap-2 rounded border border-[#2a241a] bg-[#0b0b0c] px-2 py-1.5">
                                                <div class="min-w-0">
                                                    <div class="truncate text-[11px] font-semibold text-[#d6c8ad]">{{ $entry->character->name }}</div>
                                                    <div class="text-[10px] text-[#8f8675]">{{ $entry->character->public_handle }}</div>
                                                </div>
                                                <form method="POST" action="{{ route('rooms.blacklist.destroy', ['room' => $room->slug, 'character' => $entry->character, 'tool' => 'settings']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="character_id" value="{{ $activeCharacterId }}">
                                                    <input type="hidden" name="context_tool" value="settings">
                                                    <button type="submit" class="text-[10px] font-semibold text-red-300 hover:text-red-200">Unban from Room</button>
                                                </form>
                                            </div>
                                        @empty
                                            <div class="rounded border border-dashed border-[#332817] bg-[#0b0b0c] px-2 py-2 text-[11px] text-[#8f8675]">
                                                No entries yet.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            @if ($canManageModerators)
                                <div class="rounded-md border border-[#332817] bg-[#101012] p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <h4 class="text-sm font-semibold text-[#f2dfb5]">Moderators</h4>
                                        <span class="text-[10px] text-[#8f8675]">{{ $roomModerators->count() }} active</span>
                                    </div>
                                    <form method="POST" action="{{ route('rooms.moderators.store', ['room' => $room->slug, 'tool' => 'settings']) }}" class="mt-2 flex gap-2">
                                        @csrf
                                        <input type="hidden" name="character_id" value="{{ $activeCharacterId }}">
                                        <input type="hidden" name="context_tool" value="settings">
                                        <input
                                            name="target_character_handle"
                                            type="text"
                                            maxlength="120"
                                            placeholder="Name#ABCD"
                                            class="min-w-0 flex-1 rounded-md border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"
                                        >
                                        <button
                                            type="submit"
                                            class="rounded border border-amber-500/50 bg-amber-500/10 px-2 py-1 text-[11px] font-semibold text-amber-100 hover:bg-amber-500/20"
                                        >
                                            Add
                                        </button>
                                    </form>
                                    <div class="mt-2 space-y-1">
                                        @forelse ($roomModerators as $moderator)
                                            <div class="flex items-center justify-between gap-2 rounded border border-[#2a241a] bg-[#0b0b0c] px-2 py-1.5">
                                                <div class="min-w-0">
                                                    <div class="truncate text-[11px] font-semibold text-[#d6c8ad]">{{ $moderator->character->name }}</div>
                                                    <div class="text-[10px] text-[#8f8675]">{{ $moderator->character->public_handle }}</div>
                                                </div>
                                                <form method="POST" action="{{ route('rooms.moderators.destroy', ['room' => $room->slug, 'character' => $moderator->character, 'tool' => 'settings']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="character_id" value="{{ $activeCharacterId }}">
                                                    <input type="hidden" name="context_tool" value="settings">
                                                    <button type="submit" class="text-[10px] font-semibold text-red-300 hover:text-red-200">Remove</button>
                                                </form>
                                            </div>
                                        @empty
                                            <div class="rounded border border-dashed border-[#332817] bg-[#0b0b0c] px-2 py-2 text-[11px] text-[#8f8675]">
                                                No entries yet.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @endif

                            @if ($canDeleteRoom)
                                <div class="rounded-md border border-red-500/40 bg-[#101012] p-3">
                                    <div class="text-[10px] uppercase tracking-[0.16em] text-red-300">Danger Zone</div>
                                    <h4 class="mt-2 text-sm font-semibold text-[#f2dfb5]">Delete Room</h4>
                                    <p class="mt-1 text-[11px] leading-relaxed text-[#8f8675]">
                                        This action cannot be undone.
                                    </p>
                                    <form method="POST" action="{{ route('rooms.destroy', ['room' => $room->slug]) }}" class="mt-3 space-y-2">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="character_id" value="{{ $activeCharacterId }}">
                                        <input type="hidden" name="context_tool" value="settings">
                                        <div>
                                            <label for="room-delete-confirmation" class="block text-[11px] font-semibold text-[#d6c8ad]">
                                                Type DELETE to confirm:
                                            </label>
                                            <input
                                                id="room-delete-confirmation"
                                                name="delete_confirmation"
                                                type="text"
                                                value="{{ old('delete_confirmation') }}"
                                                class="mt-1 block w-full rounded-md border-red-500/30 bg-[#0b0b0c] text-xs text-[#d6c8ad] focus:border-red-500 focus:ring-red-500"
                                            >
                                        </div>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center rounded border border-red-500/50 bg-red-500/10 px-3 py-1.5 text-[11px] font-semibold text-red-200 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500/50"
                                        >
                                            Delete Room
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- CENTER --}}
            <div class="flex-1 min-h-0 bg-[#0b0b0c] rounded-md shadow-2xl flex flex-col border border-[#2a241a] overflow-hidden ring-1 ring-amber-500/10">

                {{-- Top bar --}}
                <div class="shrink-0 flex flex-col gap-3 border-b border-[#2a241a] bg-[#101012] px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        @if (! empty($characterSelectionNotice))
                            <div class="mb-3 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-[11px] text-amber-200">
                                {{ $characterSelectionNotice }}
                            </div>
                        @endif
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-sm bg-amber-400 shadow-[0_0_12px_rgba(245,158,11,0.55)]"></span>
                            <h1 class="truncate text-lg font-semibold text-[#f2dfb5] md:text-xl">{{ $room->name }}</h1>
                        </div>
                        @if (! empty($room->description))
                            <p class="mt-1 max-w-3xl truncate text-sm text-[#8f8675]">{{ $room->description }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-[#8f8675]">
                            <span class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1">
                                Owner <span class="font-medium text-[#d6c8ad]">{{ optional($room->ownerCharacter)->name ?? optional($room->creator)->name ?? 'Unknown' }}</span>
                            </span>
                            <span class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1">
                                Visibility <span class="font-medium text-[#d6c8ad]">{{ $room->visibility ?? \App\Models\Room::VISIBILITY_PUBLIC }}</span>
                            </span>
                            <span class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1">
                                Messages <span class="font-medium text-[#d6c8ad]">{{ $messages->count() }}</span>
                            </span>
                            <span id="room-active-count" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1">
                                Active <span class="font-medium text-[#8f8675]">syncing</span>
                            </span>
                        </div>
                    </div>

                    @php
                        $characters = Auth::user()->characters->where('is_active', true)->values();
                        $isAdminBlade = (bool) (Auth::user()->is_admin ?? false);
                        $viewerCharacterId = $characters->contains('id', (int) $activeCharacterId)
                            ? (int) $activeCharacterId
                            : null;
                    @endphp

                    @if ($characters->count() > 0)
                        <div class="flex flex-wrap items-center justify-end gap-2">

                            <button id="toggle-left" type="button"
                                class="rounded border border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] px-2 py-1 hover:border-amber-500/50 hover:bg-[#141416] hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                Toggle Left
                            </button>

                            <button id="toggle-right" type="button"
                                class="rounded border border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] px-2 py-1 hover:border-amber-500/50 hover:bg-[#141416] hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                Toggle Right
                            </button>

                            <span class="text-xs text-[#8f8675]">Posting as</span>

                            <select id="character-switcher"
                                class="rounded border-[#332817] bg-[#0b0b0c] text-xs text-[#f2dfb5] px-2 py-1 focus:border-amber-500 focus:ring-amber-500">
                                @foreach ($characters as $char)
                                    <option value="{{ $char->id }}" {{ $char->id == $activeCharacterId ? 'selected' : '' }}>
                                        {{ $char->name }}
                                    </option>
                                @endforeach
                            </select>

                            <button id="leave-room-btn" type="button"
                                class="rounded border border-red-500/40 bg-red-500/10 text-xs font-semibold text-red-200 px-2 py-1 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500/50">
                                Leave room
                            </button>

                        </div>
                    @else
                        <div class="text-xs text-red-400">
                            You need at least one character to post.
                        </div>
                    @endif
                </div>

                {{-- Messages --}}
                <div id="message-container" class="flex-1 min-h-0 overflow-y-auto bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.035),transparent_34rem)] px-3 py-3 md:px-4">
                    @php
                        $messageList = $messages instanceof \Illuminate\Support\Collection
                            ? $messages->values()
                            : collect($messages)->values();
                    @endphp

                    @foreach ($messageList as $message)
                        @php
                            $prev = $loop->index > 0 ? $messageList->get($loop->index - 1) : null;
                            $messageCharacterId = (int) ($message->character_id ?? 0);
                            $prevCharacterId = (int) ($prev?->character_id ?? 0);
                            $isGrouped = $messageCharacterId > 0 && $prevCharacterId === $messageCharacterId;

                            $c = $message->character;
                            $name = optional($c)->name ?? optional($message->user)->name ?? 'Unknown';

                            $s = $c->settings ?? [];
                            if (is_string($s)) { $s = json_decode($s, true) ?: []; }

                            $c1 = $s['text_color_1'] ?? '#D8F3FF';
                            $c2 = $s['text_color_2'] ?? null;
                            $c3 = $s['text_color_3'] ?? null;
                            $c4 = $s['text_color_4'] ?? null;

                            $fadeMsg  = (bool) ($s['fade_message'] ?? false);
                            $fadeName = (bool) ($s['fade_name'] ?? false);

                            $nameStyleJson = json_encode([
                                'c1' => $c1, 'c2' => $c2, 'c3' => $c3, 'c4' => $c4, 'fade' => $fadeName,
                            ], JSON_UNESCAPED_SLASHES);

                            $bodyStyleJson = json_encode([
                                'c1' => $c1, 'c2' => $c2, 'c3' => $c3, 'c4' => $c4, 'fade' => $fadeMsg,
                            ], JSON_UNESCAPED_SLASHES);

                            $isOwner = (int)$message->user_id === (int)Auth::id();
                            $isDice = ($message->type ?? \App\Models\Message::TYPE_NORMAL) === \App\Models\Message::TYPE_DICE;
                            $canEdit = ! $isDice && ($isOwner || $isAdminBlade);

                            $isDeleted = false;
                            if (method_exists($message, 'trashed')) {
                                $isDeleted = $message->trashed();
                            } elseif (!empty($message->deleted_at)) {
                                $isDeleted = true;
                            }

                            $text = $message->content ?? $message->body ?? '';
                            $displayText = trim((string) $text);
                            $isEmote = ($message->type ?? \App\Models\Message::TYPE_NORMAL) === \App\Models\Message::TYPE_EMOTE;
                            $inlineMessage = $isEmote || $isDice;
                            $isBlockedByViewer = (bool) ($message->is_blocked_by_viewer ?? false);
                            $blockLabel = $isBlockedByViewer ? 'Blocked' : 'Block';
                            $blockClass = $isBlockedByViewer
                                ? 'text-[#8f8675] hover:text-[#d6c8ad]'
                                : 'text-red-400 hover:text-red-300';
                            $avatar = $c?->externalAvatarUrl();
                            $initial = strtoupper(substr($name, 0, 1));
                        @endphp

                        <div class="group relative flex flex-none gap-2 px-2 {{ $isGrouped ? 'border-0 rounded-none py-0' : 'border-t border-[#16120c] py-0.5' }} msg-row {{ $isBlockedByViewer && ! $isAdminBlade ? 'opacity-70' : '' }}"
                             data-message-id="{{ $message->id }}"
                             data-character-id="{{ $messageCharacterId ?: '' }}"
                             data-can-edit="{{ $canEdit ? '1' : '0' }}"
                             data-deleted="{{ $isDeleted ? '1' : '0' }}"
                             data-message-type="{{ $message->type ?? 'normal' }}"
                             data-blocked-by-viewer="{{ $isBlockedByViewer && ! $isAdminBlade ? '1' : '0' }}">

                            <div class="w-7 shrink-0">
                                @unless ($isGrouped || $inlineMessage)
                                    @if ($avatar)
                                        <img src="{{ $avatar }}"
                                             alt="{{ $name }} avatar"
                                             loading="lazy"
                                             referrerpolicy="no-referrer"
                                             class="h-7 w-7 rounded-full object-cover">
                                    @else
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full border border-[#332817] bg-[#0b0b0c] text-xs font-semibold text-[#8f8675]">
                                            {{ $initial }}
                                        </div>
                                    @endif
                                @endunless
                            </div>

                            <div class="min-w-0 flex-1 pr-28" data-body-raw="{{ e($message->body ?? '') }}">
                                @unless ($isGrouped || $inlineMessage)
                                    <div class="mb-0 flex items-baseline gap-2">
                                        <button type="button"
                                            class="char-trigger msg-name text-base font-bold leading-none text-left cursor-pointer hover:underline focus:outline-none focus:ring-2 focus:ring-amber-500/50 rounded-sm"
                                            data-style='{!! $nameStyleJson !!}'
                                            data-character-id="{{ $c?->id ?? '' }}"
                                            data-character-name="{{ e($name) }}"
                                            data-character-handle="{{ e($c?->public_handle ?? '') }}"
                                            data-character-avatar="{{ e($avatar ?? '') }}">
                                            {{ $name }}
                                        </button>

                                        <span class="text-[10px] text-[#8f8675] ml-2">{{ $message->created_at->diffForHumans() }}</span>
                                        <span class="msg-edited text-[10px] text-[#8f8675] ml-2 hidden">(edited)</span>
                                        <span class="msg-deleted text-[10px] text-[#8f8675] ml-2 {{ $isDeleted ? '' : 'hidden' }}">(deleted)</span>
                                    </div>
                                @endunless

                                @if ($isBlockedByViewer && ! $isAdminBlade)
                                    <div class="msg-blocked-notice text-xs text-[#8f8675] mt-1">
                                        Message hidden from a blocked character.
                                    </div>
                                @endif

                                <div class="msg-body-wrapper mt-0 text-sm leading-snug {{ $isBlockedByViewer && ! $isAdminBlade ? 'hidden msg-blocked-body' : '' }}">
                                    @if ($inlineMessage && ! $isDeleted)
                                        <span class="leading-snug">
                                            <span
                                                role="button"
                                                tabindex="0"
                                                class="char-trigger msg-name text-sm font-bold leading-snug cursor-pointer align-baseline hover:underline focus:outline-none focus:ring-2 focus:ring-amber-500/50 rounded-sm"
                                                data-style='{!! $nameStyleJson !!}'
                                                data-character-id="{{ $c?->id ?? '' }}"
                                                data-character-name="{{ e($name) }}"
                                                data-character-handle="{{ e($c?->public_handle ?? '') }}"
                                                data-character-avatar="{{ e($avatar ?? '') }}">{{ $name }}</span>&nbsp;<span class="msg-body text-sm text-[#d6c8ad] leading-snug whitespace-pre-line" data-style='{!! $bodyStyleJson !!}'>{!! $message->rendered_body_html !!}</span>@if ($isDice)<span class="text-[10px] text-[#8f8675] ml-2">{{ $message->created_at->diffForHumans() }}</span><span class="msg-deleted text-[10px] text-[#8f8675] ml-2 {{ $isDeleted ? '' : 'hidden' }}">(deleted)</span>@endif
                                        </span>
                                    @else
                                        <span class="msg-body text-sm text-[#d6c8ad] leading-snug whitespace-pre-line" data-style='{!! $bodyStyleJson !!}'>{!! $message->rendered_body_html !!}</span>
                                    @endif
                                </div>

                                @if ($canEdit)
                                    <div class="msg-editbox hidden mt-2">
                                        <textarea class="msg-edit-textarea w-full rounded border border-[#332817] bg-[#0b0b0c] text-base text-[#d6c8ad] leading-relaxed p-2 focus:border-amber-500 focus:ring-amber-500"
                                                  rows="3"></textarea>
                                        <div class="mt-2 flex gap-2 justify-end">
                                            <button type="button"
                                                class="msg-cancel-btn rounded border border-[#332817] bg-[#141416] px-2 py-1 text-[#d6c8ad] hover:border-amber-500/50 hover:bg-[#191511] focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                                Cancel
                                            </button>
                                            <button type="button"
                                                class="msg-save-btn rounded border border-amber-500/50 bg-amber-500/10 px-2 py-1 text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                                Save
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="msg-actions absolute right-2 top-1 flex items-center gap-1 text-[10px] opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                <button type="button"
                                    class="msg-report-btn rounded border border-[#332817] bg-[#0b0b0c]/90 px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] disabled:opacity-40"
                                    {{ $isDeleted ? 'disabled' : '' }}>
                                    Report
                                </button>

                                @if (! $isAdminBlade && $viewerCharacterId && $message->character_id && (int) $message->character_id !== $viewerCharacterId)
                                    <button type="button"
                                        class="text-xs {{ $blockClass }} ml-1"
                                        onclick="setCharacterBlock({{ $viewerCharacterId }}, {{ (int) $message->character_id }}, {{ $isBlockedByViewer ? 'false' : 'true' }})">
                                        {{ $blockLabel }}
                                    </button>
                                @endif

                                @if ($canEdit)
                                    <button type="button"
                                        class="msg-edit-btn rounded border border-[#332817] bg-[#0b0b0c]/90 px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] disabled:opacity-40"
                                        {{ $isDeleted ? 'disabled' : '' }}>
                                        Edit
                                    </button>
                                    <button type="button"
                                        class="msg-del-btn rounded border border-[#332817] bg-[#0b0b0c]/90 px-2 py-1 text-[#8f8675] hover:border-red-500/50 hover:bg-red-500/10 hover:text-red-200 disabled:opacity-40"
                                        {{ $isDeleted ? 'disabled' : '' }}>
                                        Delete
                                    </button>
                                @endif
                            </div>

                        </div>
                    @endforeach
                </div>

                {{-- Send --}}
                <div class="shrink-0 border-t border-[#2a241a] bg-[#101012] p-3">
                    @php
                        $canPostInRoom = (int) ($activeCharacterId ?? 0) > 0;
                    @endphp

                    <div
                        id="missing-character-notice"
                        class="{{ $canPostInRoom ? 'hidden ' : '' }}mb-3 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-3 text-sm text-amber-100"
                        role="alert"
                    >
                        <div class="font-medium">You need to create and select a character before posting in chat.</div>
                        <button
                            type="button"
                            id="open-characters-panel-from-notice"
                            data-open-characters-panel
                            class="mt-3 inline-flex items-center rounded border border-amber-500/50 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                        >
                            Open Characters
                        </button>
                    </div>

                    <div
                        id="message-error-notice"
                        class="hidden mb-3 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-3 text-sm text-red-200"
                        role="alert"
                    ></div>

                    <form method="POST" action="{{ route('rooms.messages.store', $room) }}" id="message-form">
                        @csrf

                        <input type="hidden" name="character_id" id="character-id-input" value="{{ $activeCharacterId }}">
                        <input type="hidden" name="room_participation_token" id="room-participation-token-input" value="{{ $roomParticipationTokens[$activeCharacterId] ?? '' }}">
                        <input type="hidden" name="content" id="content-mirror" value="">

                        <div class="mt-2 flex flex-wrap items-center gap-1 text-[11px]">
                            <button type="button" class="rich-text-btn rounded border border-[#332817] bg-[#141416] px-2 py-1 font-semibold text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]" data-rich-target="body" data-rich-tag="b">B</button>
                            <button type="button" class="rich-text-btn rounded border border-[#332817] bg-[#141416] px-2 py-1 italic text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]" data-rich-target="body" data-rich-tag="i">I</button>
                            <button type="button" class="rich-text-btn rounded border border-[#332817] bg-[#141416] px-2 py-1 underline text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]" data-rich-target="body" data-rich-tag="u">U</button>
                            <button type="button" class="rich-text-btn rounded border border-[#332817] bg-[#141416] px-2 py-1 line-through text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]" data-rich-target="body" data-rich-tag="s">S</button>
                            <button type="button" class="rich-text-btn rounded border border-[#332817] bg-[#141416] px-2 py-1 text-[10px] text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]" data-rich-target="body" data-rich-tag="small">small</button>
                            <button type="button" class="rich-text-btn rounded border border-[#332817] bg-[#141416] px-2 py-1 text-xs text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]" data-rich-target="body" data-rich-tag="large">large</button>
                        </div>

                        <textarea
                            id="body"
                            name="body"
                            rows="3"
                            required
                            placeholder="Enter to send. Shift+Enter for newline."
                            @disabled(! $canPostInRoom)
                            aria-disabled="{{ $canPostInRoom ? 'false' : 'true' }}"
                            class="mt-1 block w-full resize-none rounded-md border-[#332817] bg-[#0b0b0c] text-[#d6c8ad] placeholder:text-[#6f675a] shadow-inner focus:border-amber-500 focus:ring-amber-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >{{ old('body') }}</textarea>

                        <div class="mt-2 flex items-center justify-between gap-3">
                            <div id="message-composer-status" class="text-[10px] uppercase tracking-[0.18em] {{ $canPostInRoom ? 'text-amber-500/70' : 'text-amber-300' }}">{{ $canPostInRoom ? 'Transmission ready' : 'Character required' }}</div>
                            <button
                                type="submit"
                                @disabled(! $canPostInRoom)
                                aria-disabled="{{ $canPostInRoom ? 'false' : 'true' }}"
                                class="inline-flex items-center rounded-md border border-amber-400 bg-amber-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-[#120b02] hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#101012] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Send
                            </button>
                        </div>
                    </form>
                </div>

            </div>

            {{-- RIGHT --}}
            <div id="right-panel" class="w-full lg:w-80 min-h-0 bg-[#0b0b0c] text-[#d6c8ad] rounded-md shadow-2xl flex flex-col border border-[#2a241a] overflow-hidden">

                <div class="border-b border-[#2a241a] bg-[#101012] px-3 py-3">
                    <div class="mb-2">
                        <div class="text-[10px] font-semibold uppercase tracking-[0.2em] text-amber-400">Nexus</div>
                    </div>
                    <div class="grid grid-cols-2 gap-1 text-xs font-semibold">
                        <button id="tab-rooms" type="button" class="rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-amber-200">Rooms</button>
                        <button id="tab-users" type="button" class="rounded border border-[#332817] px-2 py-1.5 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]">Users</button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 text-xs">

                    <div id="panel-rooms" class="flex h-full min-h-0 flex-col">
                        <div class="shrink-0 space-y-2 border-b border-[#2a241a] bg-[#0b0b0c] p-2">
                            <button
                                id="open-create-room-modal"
                                type="button"
                                class="w-full rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-left text-sm font-semibold text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                            >
                                + Create Room
                            </button>



                            <div>
                                <label for="room-filter-input" class="sr-only">Filter rooms</label>
                                <input
                                    id="room-filter-input"
                                    type="text"
                                    value="{{ old('room_filter', '') }}"
                                    placeholder="Filter rooms"
                                    class="block w-full rounded-md border-[#332817] bg-[#101012] px-3 py-2 text-sm text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                                >
                            </div>

                            <div class="pt-1">
                                <div class="h-px bg-gradient-to-r from-transparent via-amber-500/40 to-transparent"></div>
                            </div>
                        </div>

                        <div id="room-list" class="flex-1 min-h-0 overflow-y-auto p-2">
                            @php
                                $favoriteRooms = $sidebarRooms->filter(fn ($sidebarRoom) => (bool) $sidebarRoom->is_following)->values();
                                $allRooms = $sidebarRooms->reject(fn ($sidebarRoom) => (bool) $sidebarRoom->is_following)->values();
                            @endphp

                            @if ($favoriteRooms->isNotEmpty())
                                <section data-room-section="favorites" class="room-list-section mb-3">
                                    <div class="mb-2 flex items-center gap-2 px-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-300">
                                        <span aria-hidden="true">★</span>
                                        <span>Favorites</span>
                                    </div>
                                    <div class="space-y-2">
                                        @foreach ($favoriteRooms as $r)
                                            @php
                                                $unreadCount = (int) ($r->unread_count ?? 0);
                                                $unreadLabel = $unreadCount > 99 ? '99+' : (string) $unreadCount;
                                                $isCurrentRoom = (int) $r->id === (int) $room->id;
                                            @endphp
                                            <button type="button"
                                                data-room-id="{{ $r->id }}"
                                                data-room-name="{{ \Illuminate\Support\Str::lower($r->name) }}"
                                                data-room-description="{{ \Illuminate\Support\Str::lower((string) ($r->description ?? '')) }}"
                                                onclick="window.location.href='{{ route('rooms.show', $r->slug) }}'"
                                                class="room-list-item w-full rounded border px-3 py-2 text-left flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-amber-500/50 {{ $isCurrentRoom ? 'border-amber-500/40 bg-amber-500/10 text-amber-100 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.10)]' : 'border-[#332817] bg-[#101012] text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]' }}">
                                                <span aria-hidden="true" class="shrink-0 text-amber-300">★</span>
                                                <span class="min-w-0 flex-1 truncate font-medium">{{ $r->name }}</span>
                                                <span
                                                    data-room-unread-badge="{{ $r->id }}"
                                                    data-unread-count="{{ $unreadCount }}"
                                                    class="{{ $unreadCount > 0 ? '' : 'hidden' }} shrink-0 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                                                    {{ $unreadLabel }}
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                </section>
                            @endif

                            <section data-room-section="all" class="room-list-section">
                                <div class="mb-2 flex items-center justify-between gap-2 px-1">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#8f8675]">All Rooms</span>
                                    <label for="all-rooms-sort" class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#8f8675]">
                                        <span>Sort by</span>
                                        <select
                                            id="all-rooms-sort"
                                            class="rounded border border-[#332817] bg-[#101012] px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"
                                        >
                                            <option value="recent" selected>Recent Activity</option>
                                            <option value="name">Name</option>
                                            <option value="population">Active Population</option>
                                        </select>
                                    </label>
                                </div>
                                <div id="all-rooms-list" class="space-y-2">
                                    @foreach ($allRooms as $r)
                                        @php
                                            $unreadCount = (int) ($r->unread_count ?? 0);
                                            $unreadLabel = $unreadCount > 99 ? '99+' : (string) $unreadCount;
                                            $isCurrentRoom = (int) $r->id === (int) $room->id;
                                        @endphp
                                        <button type="button"
                                            data-room-id="{{ $r->id }}"
                                            data-room-name="{{ \Illuminate\Support\Str::lower($r->name) }}"
                                            data-room-description="{{ \Illuminate\Support\Str::lower((string) ($r->description ?? '')) }}"
                                            data-room-updated-at="{{ $r->updated_at }}"
                                            data-room-active-users="{{ (int) ($r->active_users ?? 0) }}"
                                            onclick="window.location.href='{{ route('rooms.show', $r->slug) }}'"
                                            class="room-list-item w-full rounded border px-3 py-2 text-left flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-amber-500/50 {{ $isCurrentRoom ? 'border-amber-500/40 bg-amber-500/10 text-amber-100 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.10)]' : 'border-[#332817] bg-[#101012] text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]' }}">
                                            <span class="min-w-0 flex-1 truncate font-medium">{{ $r->name }}</span>
                                            <span
                                                data-room-unread-badge="{{ $r->id }}"
                                                data-unread-count="{{ $unreadCount }}"
                                                class="{{ $unreadCount > 0 ? '' : 'hidden' }} shrink-0 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                                                {{ $unreadLabel }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </section>
                        </div>

                        <div id="room-list-empty" class="hidden px-3 py-4 text-[#8f8675]">
                            No rooms match this filter.
                        </div>

                        @if ($showRecoveryLink)
                            <div class="shrink-0 border-t border-[#2a241a] bg-[#0b0b0c] p-2">
                                <a
                                    href="{{ route('rooms.recovery') }}"
                                    class="flex items-center justify-between rounded border border-[#332817] bg-[#101012] px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5] focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                                >
                                    <span>Recoverable Rooms</span>
                                    @if ($recoverableRoomCount > 0)
                                        <span class="shrink-0 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold normal-case tracking-normal text-amber-200">{{ $recoverableRoomCount }}</span>
                                    @endif
                                </a>
                            </div>
                        @endif
                    </div>

                    <div id="panel-users" class="hidden h-full min-h-0 overflow-y-auto px-3 py-3">
                        <div id="user-list" class="space-y-2 text-[#d6c8ad]">
                            <div class="text-[#8f8675]">Loading...</div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    {{-- Popover (must be before script so querySelector finds it) --}}
    <div id="char-popover"
         class="hidden fixed z-[9999] w-64 rounded-lg border border-[#332817] bg-[#101012] shadow-xl">
        <div class="p-3">
            <div class="flex items-start gap-3">
                <div id="char-popover-avatar" class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#332817] bg-[#0b0b0c] text-2xl font-semibold text-[#8f8675]"></div>
                <div class="min-w-0">
                    <div id="char-popover-title" class="font-semibold text-[#f2dfb5] text-sm"></div>
                    <div id="char-popover-sub" class="text-[10px] text-[#8f8675] mt-1">ID verification</div>
                </div>
            </div>

            <div class="mt-3 flex gap-2 justify-end">
                <a id="char-popover-profile"
                   href="#"
                   target="_blank"
                   rel="noreferrer noopener"
                   class="rounded border border-[#332817] bg-[#141416] px-2 py-1 text-xs text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]">
                    View Profile
                </a>

                <button id="char-popover-dm"
                        type="button"
                        class="rounded border border-[#332817] bg-[#141416] px-2 py-1 text-xs text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]">
                    DM
                </button>
            </div>

            <div id="char-popover-moderation" class="mt-3 hidden border-t border-[#2a241a] pt-3">
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Room Moderation</div>
                <div id="char-popover-moderation-status" class="mt-2 text-[10px] text-[#8f8675]"></div>
                <div id="char-popover-moderation-actions" class="mt-3 flex flex-wrap gap-2"></div>
            </div>
        </div>
    </div>

    <div id="message-report-modal"
         class="hidden fixed inset-0 z-[10000] bg-black/70 flex items-center justify-center px-4">
        <form id="message-report-form"
              class="w-full max-w-md rounded-lg border border-[#332817] bg-[#101012] p-4 shadow-xl">
            <h3 class="text-sm font-semibold text-[#f2dfb5]">Report message</h3>
            <textarea id="message-report-reason"
                      class="mt-3 w-full rounded border border-[#332817] bg-[#0b0b0c] p-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"
                      rows="4"
                      maxlength="1000"
                      required
                      placeholder="What should moderators review?"></textarea>
            <div class="mt-3 flex justify-end gap-2">
                <button type="button"
                        id="message-report-cancel"
                        class="rounded border border-[#332817] bg-[#141416] px-2 py-1 text-xs text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]">
                    Cancel
                </button>
                <button type="submit"
                        id="message-report-submit"
                        class="rounded border border-amber-500/50 bg-amber-500/10 px-2 py-1 text-xs text-amber-100 hover:bg-amber-500/20">
                    Submit
                </button>
            </div>
        </form>
    </div>

    <div
        id="create-room-modal"
        class="{{ $errors->has('name') || $errors->has('description') ? '' : 'hidden' }} fixed inset-0 z-[10000] flex items-center justify-center bg-black/70 px-4"
    >
        <div class="w-full max-w-md rounded-lg border border-[#332817] bg-[#101012] p-4 shadow-xl">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-[#f2dfb5]">Create Room</h3>
                <button
                    id="close-create-room-modal"
                    type="button"
                    class="rounded border border-[#332817] bg-[#141416] px-2 py-1 text-xs text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]"
                >
                    Close
                </button>
            </div>

            @if ($errors->has('name') || $errors->has('description'))
                <div class="mt-3 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-[11px] text-red-200">
                    <div class="font-semibold uppercase tracking-[0.14em]">Create Room Error</div>
                    <ul class="mt-2 space-y-1 text-red-100">
                        @error('name')
                            <li>{{ $message }}</li>
                        @enderror
                        @error('description')
                            <li>{{ $message }}</li>
                        @enderror
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('rooms.store') }}" class="mt-3 space-y-4">
                @csrf

                <div>
                    <label for="create-room-name" class="block text-sm font-medium text-[#d6c8ad]">
                        Name
                    </label>
                    <input
                        id="create-room-name"
                        name="name"
                        type="text"
                        required
                        maxlength="100"
                        value="{{ old('name') }}"
                        class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                        placeholder="GOOC, Wormwood Tavern, etc."
                    >
                </div>

                <div>
                    <label for="create-room-description" class="block text-sm font-medium text-[#d6c8ad]">
                        Description
                    </label>
                    <textarea
                        id="create-room-description"
                        name="description"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                        placeholder="Short blurb about the room."
                    >{{ old('description') }}</textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        id="cancel-create-room-modal"
                        class="rounded border border-[#332817] bg-[#141416] px-3 py-2 text-xs font-semibold text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded border border-amber-400 bg-amber-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-[#120b02] hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-[#101012]"
                    >
                        Create Room
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .char-row { position: relative; }
    </style>

    @php
        $ownedRoomCharactersJson = $characters
            ->map(function ($character) {
                return [
                    'id' => (int) $character->id,
                    'name' => $character->name,
                    'avatar' => $character->avatar,
                    'public_handle' => $character->public_handle,
                    'settings' => $character->settings,
                ];
            })
            ->keyBy('id')
            ->all();
    @endphp

    <script>
        let lastMessageId = {{ $messages->last()?->id ?? 0 }};
        const conversationId = {{ (int) $room->id }};
        const conversationChannelName = `private-conversation.${conversationId}`;
        const roomSlug = @json($room->slug);
        const csrf = @json(csrf_token());
        const disableChatPolling = @json(config('app.disable_chat_polling'));
        const currentCharacterUrl = @json(route('rooms.current-character'));
        const isAdmin = {{ (int) ((Auth::user()->is_admin ?? false) ? 1 : 0) }};
        const ownedCharacterIds = @json($characters->pluck('id')->map(fn ($id) => (int) $id)->values());
        const ownedRoomCharacters = @json($ownedRoomCharactersJson);
        const roomParticipationTokens = @json($roomParticipationTokens ?? []);
        const seenMessageIds = new Set();
        const pendingRoomMessages = new Map();
        let nextPendingRoomMessageId = 1;

        const container  = document.getElementById('message-container');
        const form       = document.getElementById('message-form');
        const textarea   = document.getElementById('body');
        const contentMirror = document.getElementById('content-mirror');
        const submitButton = form?.querySelector('button[type="submit"], [type="submit"]');
        const missingCharacterNotice = document.getElementById('missing-character-notice');
        const composerErrorNotice = document.getElementById('message-error-notice');
        const composerStatus = document.getElementById('message-composer-status');
        let isSubmittingMessage = false;

        const switcher   = document.getElementById('character-switcher');
        const hiddenChar = document.getElementById('character-id-input');
        const participationTokenInput = document.getElementById('room-participation-token-input');

        const leftPanel  = document.getElementById('left-panel');
        const rightPanel = document.getElementById('right-panel');

        const tabRooms   = document.getElementById('tab-rooms');
        const tabUsers   = document.getElementById('tab-users');

        const panelRooms = document.getElementById('panel-rooms');
        const panelUsers = document.getElementById('panel-users');
        const roomFilterInput = document.getElementById('room-filter-input');
        const roomList = document.getElementById('room-list');
        const roomListEmpty = document.getElementById('room-list-empty');
        const allRoomsSortInput = document.getElementById('all-rooms-sort');
        const allRoomsList = document.getElementById('all-rooms-list');
        const createRoomModal = document.getElementById('create-room-modal');
        const openCreateRoomModalButton = document.getElementById('open-create-room-modal');
        const closeCreateRoomModalButton = document.getElementById('close-create-room-modal');
        const cancelCreateRoomModalButton = document.getElementById('cancel-create-room-modal');
        const createRoomNameInput = document.getElementById('create-room-name');

        const roomListSections = Array.from(document.querySelectorAll('[data-room-section]'));
        const userListEl = document.getElementById('user-list');
        const activeCountEl = document.getElementById('room-active-count');

        const reportModal = document.getElementById('message-report-modal');
        const reportForm = document.getElementById('message-report-form');
        const reportReason = document.getElementById('message-report-reason');
        const reportSubmit = document.getElementById('message-report-submit');
        const reportCancel = document.getElementById('message-report-cancel');
        let reportMessageId = null;

        function parseUnreadCount(value) {
            const n = parseInt(value || '0', 10);
            return Number.isFinite(n) && n > 0 ? n : 0;
        }

        function parseBool(value) {
            return value === true || value === 1 || value === '1';
        }

        function formatUnreadCount(count) {
            return count > 99 ? '99+' : String(count);
        }

        function setUnreadBadge(badge, count) {
            if (!badge) return;
            const normalized = parseUnreadCount(count);
            badge.dataset.unreadCount = String(normalized);
            badge.textContent = formatUnreadCount(normalized);
            badge.classList.toggle('hidden', normalized <= 0);
        }

        function clearRoomUnreadBadge(roomId) {
            const badge = document.querySelector(`[data-room-unread-badge="${roomId}"]`);
            setUnreadBadge(badge, 0);
        }

        function parseRoomSortNumber(value) {
            const n = Number(value || 0);
            return Number.isFinite(n) ? n : 0;
        }

        function parseRoomSortDate(value) {
            const timestamp = Date.parse(value || '');
            return Number.isFinite(timestamp) ? timestamp : 0;
        }

        function compareRoomNames(a, b) {
            const nameA = a.dataset.roomName || '';
            const nameB = b.dataset.roomName || '';

            if (nameA < nameB) return -1;
            if (nameA > nameB) return 1;

            return parseRoomSortNumber(a.dataset.roomId) - parseRoomSortNumber(b.dataset.roomId);
        }

        function sortAllRooms() {
            if (!allRoomsList) return;

            const mode = allRoomsSortInput?.value || 'recent';
            const items = Array.from(allRoomsList.querySelectorAll('.room-list-item'));

            items.sort((a, b) => {
                if (mode === 'name') {
                    return compareRoomNames(a, b);
                }

                if (mode === 'population') {
                    const populationDelta = parseRoomSortNumber(b.dataset.roomActiveUsers) - parseRoomSortNumber(a.dataset.roomActiveUsers);

                    if (populationDelta !== 0) {
                        return populationDelta;
                    }

                    return compareRoomNames(a, b);
                }

                const recentDelta = parseRoomSortDate(b.dataset.roomUpdatedAt) - parseRoomSortDate(a.dataset.roomUpdatedAt);

                if (recentDelta !== 0) {
                    return recentDelta;
                }

                return compareRoomNames(a, b);
            });

            items.forEach((item) => allRoomsList.appendChild(item));
        }

        function openCreateRoomModal() {
            if (!createRoomModal) return;
            createRoomModal.classList.remove('hidden');
            window.setTimeout(() => createRoomNameInput?.focus(), 0);
        }

        function closeCreateRoomModal() {
            createRoomModal?.classList.add('hidden');
        }

        function applyRoomFilter() {
            if (!roomFilterInput) return;

            const term = roomFilterInput.value.trim().toLowerCase();
            let visibleCount = 0;

            roomListSections.forEach((section) => {
                const sectionItems = Array.from(section.querySelectorAll('.room-list-item'));
                let visibleInSection = 0;

                sectionItems.forEach((item) => {
                    const roomName = item.dataset.roomName || '';
                    const roomDescription = item.dataset.roomDescription || '';
                    const matches = term === '' || roomName.includes(term) || roomDescription.includes(term);

                    item.classList.toggle('hidden', !matches);
                    if (matches) {
                        visibleCount += 1;
                        visibleInSection += 1;
                    }
                });

                section.classList.toggle('hidden', visibleInSection === 0);
            });

            roomList?.classList.toggle('hidden', visibleCount === 0);
            roomListEmpty?.classList.toggle('hidden', visibleCount > 0);
        }

        document.querySelectorAll('[data-message-id]').forEach((row) => {
            const id = parseInt(row.dataset.messageId, 10);
            if (id) seenMessageIds.add(id);
        });

        if (!window.__roomMessageActionsBound) {
            window.__roomMessageActionsBound = true;
            document.addEventListener('click', handleMessageActionClick, true);
        }

        document.getElementById('toggle-left')?.addEventListener('click', () => leftPanel?.classList.toggle('hidden'));
        document.getElementById('toggle-right')?.addEventListener('click', () => rightPanel?.classList.toggle('hidden'));
        openCreateRoomModalButton?.addEventListener('click', openCreateRoomModal);
        closeCreateRoomModalButton?.addEventListener('click', closeCreateRoomModal);
        cancelCreateRoomModalButton?.addEventListener('click', closeCreateRoomModal);
        createRoomModal?.addEventListener('click', (event) => {
            if (event.target === createRoomModal) closeCreateRoomModal();
        });
        roomFilterInput?.addEventListener('input', applyRoomFilter);
        allRoomsSortInput?.addEventListener('change', () => {
            sortAllRooms();
            applyRoomFilter();
        });
        sortAllRooms();
        applyRoomFilter();

        const rulesToolButton = document.getElementById('open-rules-btn');
        const worldBookToolButton = document.getElementById('open-world-book-btn');
        const pinnedNotesToolButton = document.getElementById('open-pinned-notes-btn');
        const noticeBoardToolButton = document.getElementById('open-notice-board-btn');
        const roomToolButtons = {
            rules: rulesToolButton,
            world_book: worldBookToolButton,
            pinned_notes: pinnedNotesToolButton,
            notice_board: noticeBoardToolButton,
        };
        const roomToolIndicatorState = Object.assign({
            rules: false,
            world_book: false,
            pinned_notes: false,
            notice_board: false,
        }, @json($roomToolIndicators ?? []));
        const roomToolOpenState = {
            rules: false,
            world_book: false,
            pinned_notes: false,
            notice_board: false,
        };
        const roomToolReadRequests = new Set();
        const roomToolReadUrlBase = @json("/rooms/{$room->slug}/tool-reads");
        const roomToolCsrf = @json(csrf_token());

        function roomWindowToolClass(isActive, hasUpdate) {
            if (isActive) {
                return 'room-window-tool-btn rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-left text-amber-200 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.12)]';
            }

            return `room-window-tool-btn rounded border border-[#332817] bg-[#141416] px-2 py-1.5 text-left text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]${hasUpdate ? ' room-tool-update-glow' : ''}`;
        }

        function renderRoomWindowToolButton(tool) {
            const button = roomToolButtons[tool];
            if (!button) return;
            button.className = roomWindowToolClass(!!roomToolOpenState[tool], !!roomToolIndicatorState[tool]);
        }

        async function markRoomToolSeen(tool) {
            if (!roomToolButtons[tool] || roomToolReadRequests.has(tool)) return;

            roomToolReadRequests.add(tool);
            const previousState = !!roomToolIndicatorState[tool];
            roomToolIndicatorState[tool] = false;
            renderRoomWindowToolButton(tool);

            try {
                const response = await fetch(`${roomToolReadUrlBase}/${encodeURIComponent(tool)}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': roomToolCsrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Could not mark room tool as seen.');
                }
            } catch (error) {
                roomToolIndicatorState[tool] = previousState;
                renderRoomWindowToolButton(tool);
            } finally {
                roomToolReadRequests.delete(tool);
            }
        }

        function setRoomWindowToolOpenState(tool, isOpen) {
            roomToolOpenState[tool] = isOpen;
            renderRoomWindowToolButton(tool);
        }

        Object.keys(roomToolButtons).forEach(renderRoomWindowToolButton);

        rulesToolButton?.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('open-rules-window'));
        });

        worldBookToolButton?.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('open-world-book-window'));
        });

        pinnedNotesToolButton?.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('open-pinned-notes-window'));
        });

        noticeBoardToolButton?.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('open-notice-board-window'));
        });

        window.addEventListener('room-tool-opened', (event) => {
            const tool = event.detail?.tool;
            if (!tool || !(tool in roomToolButtons)) return;
            markRoomToolSeen(tool);
        });

        window.addEventListener('rules-window-state', (event) => {
            setRoomWindowToolOpenState('rules', !!event.detail?.open);
        });

        window.addEventListener('world-book-window-state', (event) => {
            setRoomWindowToolOpenState('world_book', !!event.detail?.open);
        });

        window.addEventListener('pinned-notes-window-state', (event) => {
            setRoomWindowToolOpenState('pinned_notes', !!event.detail?.open);
        });

        window.addEventListener('notice-board-window-state', (event) => {
            setRoomWindowToolOpenState('notice_board', !!event.detail?.open);
        });

        const contextToolButtons = Array.from(document.querySelectorAll('[data-context-tool]'));
        const contextToolPanels = Array.from(document.querySelectorAll('[data-context-panel]'));
        const initialContextTool = @json(request()->query('tool', $errors->any() ? 'settings' : null));
        function showContextTool(tool) {
            contextToolButtons.forEach((button) => {
                const isActiveTool = button.dataset.contextTool === tool;
                button.className = isActiveTool
                    ? 'context-tool-btn rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-left text-amber-200 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.12)]'
                    : 'context-tool-btn rounded border border-[#332817] bg-[#141416] px-2 py-1.5 text-left text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]';
            });
            contextToolPanels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.contextPanel !== tool);
            });
        }
        contextToolButtons.forEach((button) => {
            button.addEventListener('click', () => showContextTool(button.dataset.contextTool));
        });
        showContextTool(initialContextTool);

        function escAttr(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function escHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function isSafeAvatarUrl(url) {
            if (!url) return false;

            try {
                const parsed = new URL(url, window.location.origin);
                return parsed.protocol === 'http:' || parsed.protocol === 'https:';
            } catch (e) {
                return false;
            }
        }

        function avatarInitial(name) {
            return (String(name || '?').trim().charAt(0) || '?').toUpperCase();
        }

        function avatarHtml(url, name, sizeClass = 'h-7 w-7', shapeClass = 'rounded-full') {
            if (isSafeAvatarUrl(url)) {
                return `<img src="${escAttr(url)}" alt="${escAttr(name)} avatar" loading="lazy" referrerpolicy="no-referrer" class="${sizeClass} shrink-0 ${shapeClass} object-cover">`;
            }

            return `<div class="flex ${sizeClass} shrink-0 items-center justify-center ${shapeClass} border border-[#332817] bg-[#0b0b0c] text-xs font-semibold text-[#8f8675]">${escHtml(avatarInitial(name))}</div>`;
        }

        function setCharacterBlock(blockerId, blockedId, shouldBlock) {
            const action = shouldBlock ? "Block this character?" : "Unblock this character?";
            if (!confirm(action)) return;

            const token = document.querySelector('meta[name="csrf-token"]').content;

            fetch(`/characters/${blockerId}/blocks/${blockedId}`, {
                method: shouldBlock ? 'POST' : 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error();
                return res.json();
            })
            .then(() => location.reload())
            .catch(() => alert(shouldBlock ? "Failed to block character." : "Failed to unblock character."));
        }

        function shortSigil(id) {
            const n = Number.isFinite(id) ? id : 0;
            return Math.abs((n * 2654435761) % 0xFFFFFFFF)
                .toString(16)
                .toUpperCase()
                .slice(0, 4);
        }

        /* Popover behavior */
        const pop = document.getElementById('char-popover');
        const popTitle = document.getElementById('char-popover-title');
        const popAvatar = document.getElementById('char-popover-avatar');
        const popProfile = document.getElementById('char-popover-profile');
        const popDm = document.getElementById('char-popover-dm');
        const popModeration = document.getElementById('char-popover-moderation');
        const popModerationStatus = document.getElementById('char-popover-moderation-status');
        const popModerationActions = document.getElementById('char-popover-moderation-actions');

        let popState = { characterId: null };

        function hidePopover() {
            if (!pop) return;
            pop.classList.add('hidden');
            popState = { characterId: null };
            resetPopoverModeration();
        }

        function currentRoomParticipationToken() {
            const characterId = getTabCharacterId();
            return roomParticipationTokens[String(characterId)] || roomParticipationTokens[characterId] || '';
        }

        function syncRoomParticipationToken() {
            if (participationTokenInput) participationTokenInput.value = currentRoomParticipationToken();
        }

        function resetPopoverModeration() {
            if (popModeration) popModeration.classList.add('hidden');
            if (popModerationStatus) popModerationStatus.textContent = '';
            if (popModerationActions) popModerationActions.innerHTML = '';
        }

        function positionPopoverNear(el) {
            if (!pop || !el) return;
            const r = el.getBoundingClientRect();

            let top = r.bottom + 8;
            let left = r.left;

            const pad = 8;
            const w = pop.offsetWidth || 256;
            const h = pop.offsetHeight || 140;

            if (left + w > window.innerWidth - pad) left = window.innerWidth - w - pad;
            if (top + h > window.innerHeight - pad) top = r.top - h - 8;

            if (left < pad) left = pad;
            if (top < pad) top = pad;

            pop.style.left = `${left}px`;
            pop.style.top = `${top}px`;
        }

        function openPopoverFromTrigger(triggerEl) {
            if (!pop || !triggerEl) return;

            const characterId = (triggerEl.dataset.characterId || '').trim();
            const numericCharacterId = characterId ? parseInt(characterId, 10) : 0;

            const characterName = (triggerEl.dataset.characterName || triggerEl.textContent || '').trim();
            const characterHandle = (triggerEl.dataset.characterHandle || '').trim();
            const avatar = (triggerEl.dataset.characterAvatar || '').trim();

            const fallbackHandle = characterId ? `${characterName}#${shortSigil(parseInt(characterId, 10))}` : characterName;

            if (popTitle) popTitle.textContent = characterHandle || fallbackHandle;
            if (popAvatar) {
                popAvatar.innerHTML = avatarHtml(avatar, characterName, 'h-20 w-20', 'rounded-lg');
            }
          
            if (popProfile) {
                const hasCharacter = Boolean(characterId);
                popProfile.href = hasCharacter ? `/characters/${characterId}/profile` : '#';
                popProfile.classList.toggle('hidden', !hasCharacter);
            }

            const isOwnedCharacter = numericCharacterId > 0 && ownedCharacterIds.includes(numericCharacterId);

            if (popDm) {
                if (numericCharacterId > 0 && !isOwnedCharacter) {
                    popDm.classList.remove('hidden');
                    popDm.disabled = false;
                    popState.characterId = characterId;
                } else {
                    popDm.classList.add('hidden');
                    popDm.disabled = true;
                    popState.characterId = characterId;
                }
            }

            resetPopoverModeration();
            if (characterId) loadPopoverModerationState(characterId);

            pop.classList.remove('hidden');
            positionPopoverNear(triggerEl);
        }

        document.addEventListener('click', function(e) {
            const target = e.target instanceof Element ? e.target : e.target?.parentElement;
            const trigger = target?.closest('.char-trigger');
            const clickedInsidePopover = pop && e.target instanceof Node && pop.contains(e.target);

            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                openPopoverFromTrigger(trigger);
                return;
            }

            if (!clickedInsidePopover) hidePopover();
        });
/* */
        window.addEventListener('resize', () => hidePopover());
        window.addEventListener('scroll', () => hidePopover(), true);

        function renderPopoverModerationAction(label, tone, onClick) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `rounded border px-2 py-1 text-xs ${tone}`;
            button.textContent = label;
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();

                try {
                    await onClick();
                } catch (error) {
                    window.alert(error instanceof Error ? error.message : 'Room moderation action failed.');
                }
            });
            return button;
        }

        async function submitRoomModerationAction(url, method, payload, failureMessage) {
            const response = await fetch(url, {
                method,
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(failureMessage);
            }

            window.location.reload();
        }

        async function loadPopoverModerationState(characterId) {
            if (!popModeration || !characterId) return;

            try {
                const response = await fetch(`/rooms/${roomSlug}/moderation/characters/${characterId}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    resetPopoverModeration();
                    return;
                }

                const payload = await response.json();
                const target = payload.target || {};
                const actions = payload.actions || {};
                const statusParts = [];

                if (target.is_owner) statusParts.push('Room owner');
                if (target.is_moderator) statusParts.push('Moderator');
                if (target.is_whitelisted) statusParts.push('Whitelisted');
                if (target.is_character_banned) statusParts.push('Character banned from room');
                if (target.is_account_banned) statusParts.push('Account banned from room');

                popModeration.classList.remove('hidden');
                if (popModerationStatus) {
                    popModerationStatus.textContent = statusParts.length ? statusParts.join(' • ') : 'No room-specific moderation state.';
                }

                if (!popModerationActions) return;
                popModerationActions.innerHTML = '';

                const actorCharacterId = getTabCharacterId();
                const basePayload = {
                    character_id: actorCharacterId,
                    target_character_id: parseInt(characterId, 10),
                };

                if (actions.can_kick && !target.is_character_banned && !target.is_account_banned) {
                    popModerationActions.appendChild(renderPopoverModerationAction(
                        'Kick from Room',
                        'border-red-500/40 bg-red-500/10 text-red-200 hover:bg-red-500/20',
                        async () => {
                            const reasonInput = window.prompt('Optional kick reason', '');

                            if (reasonInput === null) {
                                return;
                            }

                            await submitRoomModerationAction(`/rooms/${roomSlug}/kick`, 'POST', { ...basePayload, reason: reasonInput }, 'Failed to kick character from room.');
                        }
                    ));
                }

                if (actions.can_ban_character) {
                    if (target.is_character_banned) {
                        popModerationActions.appendChild(renderPopoverModerationAction(
                            'Unban Character from Room',
                            'border-emerald-500/30 bg-emerald-500/10 text-emerald-200 hover:bg-emerald-500/20',
                            async () => {
                                await submitRoomModerationAction(`/rooms/${roomSlug}/blacklist/${characterId}`, 'DELETE', { character_id: actorCharacterId }, 'Failed to unban character from room.');
                            }
                        ));
                    } else {
                        popModerationActions.appendChild(renderPopoverModerationAction(
                            'Ban Character from Room',
                            'border-red-500/40 bg-red-500/10 text-red-200 hover:bg-red-500/20',
                            async () => {
                                await submitRoomModerationAction(`/rooms/${roomSlug}/blacklist`, 'POST', basePayload, 'Failed to ban character from room.');
                            }
                        ));
                    }
                }

                if (actions.can_ban_account) {
                    if (target.is_account_banned) {
                        popModerationActions.appendChild(renderPopoverModerationAction(
                            'Unban Account from Room',
                            'border-emerald-500/30 bg-emerald-500/10 text-emerald-200 hover:bg-emerald-500/20',
                            async () => {
                                await submitRoomModerationAction(`/rooms/${roomSlug}/account-blacklist/${characterId}`, 'DELETE', { character_id: actorCharacterId }, 'Failed to unban account from room.');
                            }
                        ));
                    } else {
                        popModerationActions.appendChild(renderPopoverModerationAction(
                            'Ban Account from Room',
                            'border-red-500/40 bg-red-500/10 text-red-200 hover:bg-red-500/20',
                            async () => {
                                await submitRoomModerationAction(`/rooms/${roomSlug}/account-blacklist`, 'POST', basePayload, 'Failed to ban account from room.');
                            }
                        ));
                    }
                }

                if (actions.can_ban_character) {
                    if (target.is_whitelisted) {
                        popModerationActions.appendChild(renderPopoverModerationAction(
                            'Remove from Whitelist',
                            'border-[#332817] bg-[#141416] text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]',
                            async () => {
                                await submitRoomModerationAction(`/rooms/${roomSlug}/whitelist/${characterId}`, 'DELETE', { character_id: actorCharacterId }, 'Failed to remove whitelist entry.');
                            }
                        ));
                    } else {
                        popModerationActions.appendChild(renderPopoverModerationAction(
                            'Whitelist Character',
                            'border-amber-500/40 bg-amber-500/10 text-amber-100 hover:bg-amber-500/20',
                            async () => {
                                await submitRoomModerationAction(`/rooms/${roomSlug}/whitelist`, 'POST', basePayload, 'Failed to whitelist character.');
                            }
                        ));
                    }
                }

                if (actions.can_manage_moderator_role) {
                    if (target.is_moderator) {
                        popModerationActions.appendChild(renderPopoverModerationAction(
                            'Remove Moderator',
                            'border-[#332817] bg-[#141416] text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]',
                            async () => {
                                await submitRoomModerationAction(`/rooms/${roomSlug}/moderators/${characterId}`, 'DELETE', { character_id: actorCharacterId }, 'Failed to remove moderator.');
                            }
                        ));
                    } else {
                        popModerationActions.appendChild(renderPopoverModerationAction(
                            'Add Moderator',
                            'border-amber-500/40 bg-amber-500/10 text-amber-100 hover:bg-amber-500/20',
                            async () => {
                                await submitRoomModerationAction(`/rooms/${roomSlug}/moderators`, 'POST', basePayload, 'Failed to add moderator.');
                            }
                        ));
                    }
                }

                if (!popModerationActions.children.length) {
                    const empty = document.createElement('div');
                    empty.className = 'text-[10px] text-[#8f8675]';
                    empty.textContent = 'No room actions available for this character.';
                    popModerationActions.appendChild(empty);
                }
            } catch (error) {
                console.error('Room moderation state error:', error);
                resetPopoverModeration();
            }
        }

        popDm?.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!popState.characterId) return;

            fetch('/dms/start', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    other_character_id: parseInt(popState.characterId, 10),
                    my_character_id: getTabCharacterId()
                })

            })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.slug) return;

                window.dispatchEvent(new CustomEvent('open-dm-window', {
                    detail: {
                        slug: data.slug,
                        my_character_id: getTabCharacterId(),
                        other_character_id: parseInt(popState.characterId, 10),
                        is_blocked_by_viewer: false
                    }
                }));
            })
            .catch(() => {});
        });




        /* fade styles */
        function buildStops(s) {
            const stops = [];
            if (s.c1) stops.push(s.c1);
            if (s.c2) stops.push(s.c2);
            if (s.c3) stops.push(s.c3);
            if (s.c4) stops.push(s.c4);
            return stops.filter(Boolean);
        }
        function applyGradientText(el, stops) {
            el.style.backgroundImage = `linear-gradient(90deg, ${stops.join(',')})`;
            el.style.webkitBackgroundClip = 'text';
            el.style.backgroundClip = 'text';
            el.style.color = 'transparent';

            const isInlineBody = el.classList.contains('msg-body') && !!el.closest('[data-message-type="emote"], [data-message-type="dice"]');
            el.style.display = isInlineBody ? 'inline' : 'inline-block';
        }
        function applySolidText(el, color) {
            el.style.backgroundImage = '';
            el.style.webkitBackgroundClip = '';
            el.style.backgroundClip = '';
            el.style.color = color || '#D8F3FF';
        }
        function applyStyleFromDataset(el) {
            if (!el) return;
            let s = {};
            try { s = JSON.parse(el.dataset.style || '{}'); } catch(e) { s = {}; }
            const stops = buildStops(s);
            const shouldFade = !!s.fade && stops.length >= 2;
            if (shouldFade) applyGradientText(el, stops);
            else applySolidText(el, s.c1);
        }
        function applyStylesIn(root) {
            (root || document).querySelectorAll('.msg-name, .msg-body').forEach(applyStyleFromDataset);
        }
        applyStylesIn(document);

        /* active character */
        const serverActiveCharacterId = {{ (int) ($activeCharacterId ?? 0) }};

        function getTabCharacterId() {
            const v = sessionStorage.getItem('active_character_id');
            return v ? parseInt(v, 10) : 0;
        }
        function getViewerCharacterId() {
            const id = getTabCharacterId();
            return ownedCharacterIds.includes(id) ? id : 0;
        }
        function setTabCharacterId(id) {
            sessionStorage.setItem('active_character_id', String(id));
            if (hiddenChar) hiddenChar.value = String(id);
            syncRoomParticipationToken();
        }


        function syncCurrentCharacter(id) {
            if (!id) return Promise.resolve(false);

            return fetch(currentCharacterUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ character_id: id }),
            })
            .then((response) => response.ok)
            .catch(() => false);
        }

        window.Storybox = window.Storybox || {};
        window.Storybox.activeCharacterId = () => getTabCharacterId();
        window.StoryboxChannelCharacters = window.StoryboxChannelCharacters || {};

        /* room per character (client-side snapping) */
        function setLastRoomForCharacter(characterId, slug) {
            if (!characterId || !slug) return;
            localStorage.setItem('char_room_' + characterId, slug);
        }
        function getLastRoomForCharacter(characterId) {
            return localStorage.getItem('char_room_' + characterId) || '';
        }

        (function initActiveCharacterPerTab() {
            if (!switcher) return;

            const preferred = serverActiveCharacterId || parseInt(switcher.value, 10) || getTabCharacterId();

            if (preferred) {
                switcher.value = String(preferred);
                setTabCharacterId(preferred);
                syncCurrentCharacter(preferred);
            }

            const cid = getTabCharacterId();
            if (cid) setLastRoomForCharacter(cid, roomSlug);
            updateComposerAvailability();
        })();

        switcher?.addEventListener('change', function() {
            const newId = parseInt(this.value, 10);
            if (!newId) return;

            const oldId = getTabCharacterId();
            if (oldId) setLastRoomForCharacter(oldId, roomSlug);

            setTabCharacterId(newId);
            updateComposerAvailability();

            syncCurrentCharacter(newId).then((ok) => {
                if (!ok) return;

                const target = getLastRoomForCharacter(newId);
                if (target && target !== roomSlug) {
                    window.location.href = `/rooms/${target}`;
                    return;
                }

                sendPresencePing();
            });
        });

        /* presence */
        function sendPresencePing() {
            const characterId = getTabCharacterId();
            if (!characterId) return Promise.resolve();

            return fetch(`/rooms/${roomSlug}/presence`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    character_id: characterId,
                    room_participation_token: currentRoomParticipationToken(),
                }),
            })
            .then((response) => {
                if (response.ok) {
                    clearRoomUnreadBadge(conversationId);
                } else {
                    console.warn('Room presence heartbeat failed', {
                        roomSlug,
                        characterId,
                        status: response.status,
                    });
                }

                return response;
            })
            .catch((error) => {
                console.warn('Room presence heartbeat request failed', {
                    roomSlug,
                    characterId,
                    error,
                });
            });
        }
        sendPresencePing().finally(() => startRoomRealtime());
        if (!disableChatPolling) {
            setInterval(sendPresencePing, 30000);
        }

        /* leave room */
        function leaveRoom() {
            const characterId = getTabCharacterId();
            if (!characterId) return Promise.resolve();

            return fetch(`/rooms/${roomSlug}/leave`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                keepalive: true,
                body: JSON.stringify({
                    character_id: characterId,
                    room_participation_token: currentRoomParticipationToken(),
                }),
            }).catch(() => {});
        }

        document.getElementById('leave-room-btn')?.addEventListener('click', () => {
            const cid = getTabCharacterId();
            if (cid) setLastRoomForCharacter(cid, roomSlug);
            leaveRoom().finally(() => window.location.href = '/chat');
        });

        window.addEventListener('beforeunload', () => leaveRoom());

        /* send */
        function syncContentMirror() {
            if (contentMirror && textarea) contentMirror.value = textarea.value;
        }
        textarea?.addEventListener('input', syncContentMirror);

        function wrapTextareaSelection(target, tag) {
            if (!target || target.disabled) return;

            const openTag = `[${tag}]`;
            const closeTag = `[/${tag}]`;
            const start = target.selectionStart ?? target.value.length;
            const end = target.selectionEnd ?? target.value.length;
            const selected = target.value.slice(start, end);
            const replacement = `${openTag}${selected}${closeTag}`;

            target.setRangeText(replacement, start, end, 'end');

            const cursorStart = start + openTag.length;
            const cursorEnd = cursorStart + selected.length;
            target.focus();
            target.setSelectionRange(cursorStart, cursorEnd);
            syncContentMirror();
        }

        document.querySelectorAll('.rich-text-btn[data-rich-target="body"]').forEach((button) => {
            button.addEventListener('click', () => wrapTextareaSelection(textarea, button.dataset.richTag || 'b'));
        });

        function showMissingCharacterNotice(message = 'You need to create and select a character before posting in chat.') {
            if (missingCharacterNotice) {
                const messageEl = missingCharacterNotice.querySelector('div');
                if (messageEl) messageEl.textContent = message;
                missingCharacterNotice.classList.remove('hidden');
            }

            if (composerStatus) {
                composerStatus.textContent = 'Character required';
                composerStatus.classList.remove('text-amber-500/70');
                composerStatus.classList.add('text-amber-300');
            }
        }

        function showComposerError(message = 'Could not send message.') {
            if (!composerErrorNotice) return;
            composerErrorNotice.textContent = message;
            composerErrorNotice.classList.remove('hidden');
        }

        function clearComposerError() {
            if (!composerErrorNotice) return;
            composerErrorNotice.textContent = '';
            composerErrorNotice.classList.add('hidden');
        }

        function updateComposerAvailability() {
            const canPost = getTabCharacterId() > 0;

            if (textarea) {
                textarea.disabled = !canPost;
            }

            if (submitButton) {
                submitButton.disabled = !canPost || isSubmittingMessage;
                submitButton.setAttribute('aria-disabled', submitButton.disabled ? 'true' : 'false');
            }

            if (composerStatus) {
                composerStatus.textContent = canPost ? 'Transmission ready' : 'Character required';
                composerStatus.classList.toggle('text-amber-500/70', canPost);
                composerStatus.classList.toggle('text-amber-300', !canPost);
            }

            if (missingCharacterNotice) {
                missingCharacterNotice.classList.toggle('hidden', canPost);
            }
        }

        function isSlashCommand(body) {
            return String(body || '').trim().startsWith('/');
        }

        function nextPendingMessageId() {
            const id = `pending-room-${nextPendingRoomMessageId}`;
            nextPendingRoomMessageId += 1;
            return id;
        }

        function parseCharacterSettings(settings) {
            if (!settings || typeof settings !== 'object') {
                if (typeof settings === 'string') {
                    try {
                        const parsed = JSON.parse(settings);
                        return parsed && typeof parsed === 'object' ? parsed : {};
                    } catch (error) {
                        return {};
                    }
                }

                return {};
            }

            return settings;
        }

        function getLastMessageRow() {
            if (!container) return null;
            return container.querySelector('.msg-row:last-of-type');
        }

        function buildRoomMessageRow(message, options = {}) {
            const pendingId = options.pendingId || null;
            const state = options.state || 'confirmed';
            const isPending = state === 'pending';
            const isFailed = state === 'failed';
            const messageId = parseInt(message?.id, 10) || 0;

            const name = (message.character && message.character.name)
                ? message.character.name
                : 'Unknown';
            const avatar = message.character?.avatar || '';
            const settings = parseCharacterSettings(message.character?.settings);

            const c1 = settings.text_color_1 || '#D8F3FF';
            const c2 = settings.text_color_2 || null;
            const c3 = settings.text_color_3 || null;
            const c4 = settings.text_color_4 || null;

            const fadeMsg = !!settings.fade_message;
            const fadeName = !!settings.fade_name;

            const isDeleted = !!message.deleted_at || !!message.is_deleted || (message.body === '[deleted]') || (message.content === '[deleted]');
            const messageType = message.type || 'normal';
            const isEmote = messageType === 'emote';
            const isDice = messageType === 'dice';
            const isInlineMessage = isEmote || isDice;
            const text = isDeleted ? '[deleted]' : String(message.content ?? message.body ?? '').trim();
            const isBlockedByViewer = !isAdmin && parseBool(message.is_blocked_by_viewer);

            const canEdit = !isPending && !isFailed && !isDice && (!!isAdmin || parseBool(message.can_edit));
            const viewerCharacterId = getViewerCharacterId();
            const messageCharacterId = parseInt(message.character?.id ?? message.character_id ?? 0, 10) || 0;
            const previousCharacterId = options.previousCharacterId ?? (parseInt(getLastMessageRow()?.dataset.characterId || '0', 10) || 0);
            const isGrouped = !isPending && !isFailed && messageCharacterId > 0 && previousCharacterId === messageCharacterId;
            const blockLabel = isBlockedByViewer ? 'Blocked' : 'Block';
            const blockClass = isBlockedByViewer ? 'text-[#8f8675] hover:text-[#d6c8ad]' : 'text-red-400 hover:text-red-300';
            const blockButtonHtml = (!isPending && !isFailed && !isAdmin && viewerCharacterId && messageCharacterId && messageCharacterId !== viewerCharacterId)
                ? `<button type="button" class="text-xs ${blockClass} ml-1" onclick="setCharacterBlock(${viewerCharacterId}, ${messageCharacterId}, ${isBlockedByViewer ? 'false' : 'true'})">${blockLabel}</button>`
                : '';

            const row = document.createElement('div');
            row.className = `group relative flex flex-none gap-2 px-2 ${isGrouped ? 'border-0 rounded-none py-0' : 'border-t border-[#16120c] py-0.5'} msg-row` + (isBlockedByViewer ? ' opacity-70' : '');
            if (isPending) {
                row.className += ' opacity-80';
            }
            if (isFailed) {
                row.className += ' border-red-500/30 bg-red-500/5';
            }

            if (messageId) {
                row.dataset.messageId = String(messageId);
            }
            row.dataset.characterId = messageCharacterId ? String(messageCharacterId) : '';
            row.dataset.canEdit = canEdit ? '1' : '0';
            row.dataset.deleted = isDeleted ? '1' : '0';
            row.dataset.messageType = messageType;
            row.dataset.blockedByViewer = isBlockedByViewer ? '1' : '0';
            row.dataset.pendingState = state;
            if (pendingId) {
                row.dataset.pendingId = pendingId;
            }

            const safeNameAttr = escAttr(name);
            const safeNameHtml = escHtml(name);
            const renderedBodyHtml = typeof message.rendered_body_html === 'string' && message.rendered_body_html.length
                ? message.rendered_body_html
                : escHtml(text);
            const safeAvatarAttr = escAttr(avatar);
            const safeCreatedAt = escHtml(message.created_at_human ?? (isPending ? 'sending...' : isFailed ? 'send failed' : ''));
            const safeHandleAttr = escAttr(message.character?.public_handle ?? (messageCharacterId ? `${name}#${shortSigil(messageCharacterId)}` : name));
            const nameStyle = escAttr(JSON.stringify({ c1, c2, c3, c4, fade: fadeName }));
            const bodyStyle = escAttr(JSON.stringify({ c1, c2, c3, c4, fade: fadeMsg }));
            const avatarMarkup = (isGrouped || isInlineMessage) ? `
                        <div class="w-7 shrink-0"></div>
                    ` : `<div class="w-7 shrink-0">${avatarHtml(avatar, name, 'h-7 w-7')}</div>`;
            const pendingBadge = isPending
                ? '<span class="ml-2 rounded border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 text-[9px] uppercase tracking-[0.16em] text-amber-200/80">Sending</span>'
                : '';
            const failedBadge = isFailed
                ? '<span class="ml-2 rounded border border-red-500/30 bg-red-500/10 px-1.5 py-0.5 text-[9px] uppercase tracking-[0.16em] text-red-200">Failed</span>'
                : '';
            const nameMarkup = (isGrouped || isInlineMessage) ? '' : `
                        <div class="mb-0 flex items-baseline gap-2">
                            <button type="button"
                                class="char-trigger msg-name text-base font-bold leading-none text-left cursor-pointer hover:underline focus:outline-none focus:ring-2 focus:ring-amber-500/50 rounded-sm"
                                data-style='${nameStyle}'
                                data-character-id="${messageCharacterId || ''}"
                                data-character-name="${safeNameAttr}"
                                data-character-handle="${safeHandleAttr}"
                                data-character-avatar="${safeAvatarAttr}">
                                ${safeNameHtml}
                            </button>

                            <span class="text-[10px] text-[#8f8675] ml-2">${safeCreatedAt}</span>
                            ${pendingBadge}
                            ${failedBadge}
                            <span class="msg-edited text-[10px] text-[#8f8675] ml-2 hidden">(edited)</span>
                            <span class="msg-deleted text-[10px] text-[#8f8675] ml-2 ${isDeleted ? '' : 'hidden'}">(deleted)</span>
                        </div>
                    `;
            const retryActions = isFailed ? `
                                <button type="button" class="msg-retry-btn rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1 text-amber-100 hover:bg-amber-500/20">Retry</button>
                                <button type="button" class="msg-restore-btn rounded border border-[#332817] bg-[#0b0b0c]/90 px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]">Restore</button>
                            ` : '';

            row.innerHTML = `
                        ${avatarMarkup}

                        <div class="min-w-0 flex-1 pr-28" data-body-raw="${escAttr(String(message.body ?? message.content ?? ''))}">
                            ${nameMarkup}

                            ${isBlockedByViewer ? `
                                <div class="msg-blocked-notice text-xs text-[#8f8675] mt-1">
                                    Message hidden from a blocked character.
                                </div>
                            ` : ''}

                            <div class="msg-body-wrapper mt-0 text-sm leading-snug ${isBlockedByViewer ? 'hidden msg-blocked-body' : ''}">${isInlineMessage && !isDeleted ? `
                                <span class="leading-snug">
                                    <span
                                        role="button"
                                        tabindex="0"
                                        class="char-trigger msg-name text-sm font-bold leading-snug cursor-pointer align-baseline hover:underline focus:outline-none focus:ring-2 focus:ring-amber-500/50 rounded-sm"
                                        data-style='${nameStyle}'
                                        data-character-id="${messageCharacterId || ''}"
                                        data-character-name="${safeNameAttr}"
                                        data-character-handle="${safeHandleAttr}"
                                        data-character-avatar="${safeAvatarAttr}">${safeNameHtml}</span>&nbsp;<span class="msg-body text-sm text-[#d6c8ad] leading-snug whitespace-pre-line" data-style='${bodyStyle}'>${renderedBodyHtml}</span>${isDice ? `<span class="text-[10px] text-[#8f8675] ml-2">${safeCreatedAt}</span><span class="msg-deleted text-[10px] text-[#8f8675] ml-2 ${isDeleted ? '' : 'hidden'}">(deleted)</span>` : ''}
                                </span>
                            ` : `<span class="msg-body text-sm text-[#d6c8ad] leading-snug whitespace-pre-line" data-style='${bodyStyle}'>${renderedBodyHtml}</span>`}</div>

                            ${canEdit ? `
                                <div class="msg-editbox hidden mt-2">
                                    <textarea class="msg-edit-textarea w-full rounded border border-[#332817] bg-[#0b0b0c] text-base text-[#d6c8ad] leading-relaxed p-2 focus:border-amber-500 focus:ring-amber-500" rows="3"></textarea>
                                    <div class="mt-2 flex gap-2 justify-end">
                                        <button type="button" class="msg-cancel-btn rounded border border-[#332817] bg-[#141416] px-2 py-1 text-[#d6c8ad] hover:border-amber-500/50 hover:bg-[#191511] focus:outline-none focus:ring-2 focus:ring-amber-500/50">Cancel</button>
                                        <button type="button" class="msg-save-btn rounded border border-amber-500/50 bg-amber-500/10 px-2 py-1 text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50">Save</button>
                                    </div>
                                </div>
                            ` : ''}
                        </div>

                        <div class="msg-actions absolute right-2 top-1 flex items-center gap-1 text-[10px] opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                            ${isPending ? '<span class="rounded border border-amber-500/20 bg-amber-500/10 px-2 py-1 text-amber-100/80">Sending...</span>' : ''}
                            ${retryActions}
                            ${!isPending && !isFailed ? `<button type="button" class="msg-report-btn rounded border border-[#332817] bg-[#0b0b0c]/90 px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] disabled:opacity-40" ${isDeleted ? 'disabled' : ''}>Report</button>` : ''}
                            ${blockButtonHtml}
                            ${canEdit ? `
                                <button type="button" class="msg-edit-btn rounded border border-[#332817] bg-[#0b0b0c]/90 px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] disabled:opacity-40" ${isDeleted ? 'disabled' : ''}>Edit</button>
                                <button type="button" class="msg-del-btn rounded border border-[#332817] bg-[#0b0b0c]/90 px-2 py-1 text-[#8f8675] hover:border-red-500/50 hover:bg-red-500/10 hover:text-red-200 disabled:opacity-40" ${isDeleted ? 'disabled' : ''}>Delete</button>
                            ` : ''}
                        </div>
                    `;

            return row;
        }

        function appendRoomMessageRow(message, options = {}) {
            if (!container) return null;

            const row = buildRoomMessageRow(message, options);
            container.appendChild(row);
            applyStylesIn(row);

            const messageId = parseInt(message?.id, 10) || 0;
            if (!options.skipSeenId && messageId > 0) {
                seenMessageIds.add(messageId);
                if (messageId > lastMessageId) lastMessageId = messageId;
            }

            return row;
        }

        function replaceRoomMessageRow(targetRow, message, options = {}) {
            if (!container || !targetRow) return null;

            const previousCharacterId = parseInt(targetRow.previousElementSibling?.dataset?.characterId || '0', 10) || 0;
            const nextRow = buildRoomMessageRow(message, {
                ...options,
                previousCharacterId,
            });
            targetRow.replaceWith(nextRow);
            applyStylesIn(nextRow);

            const messageId = parseInt(message?.id, 10) || 0;
            if (!options.skipSeenId && messageId > 0) {
                seenMessageIds.add(messageId);
                if (messageId > lastMessageId) lastMessageId = messageId;
            }

            return nextRow;
        }

        function buildOptimisticRoomMessage(body, characterId) {
            const ownedCharacter = ownedRoomCharacters[String(characterId)] || null;
            const switcherOption = switcher?.selectedOptions?.[0];
            const characterName = ownedCharacter?.name || switcherOption?.textContent?.trim() || 'You';
            const characterAvatar = ownedCharacter?.avatar || '';
            const characterHandle = ownedCharacter?.public_handle || '';
            const characterSettings = parseCharacterSettings(ownedCharacter?.settings || {});

            return {
                id: 0,
                character_id: characterId,
                type: 'normal',
                body,
                content: body,
                rendered_body_html: escHtml(body),
                created_at_human: 'sending...',
                is_deleted: false,
                is_blocked_by_viewer: false,
                can_edit: false,
                character: {
                    id: characterId,
                    name: characterName,
                    avatar: characterAvatar,
                    settings: characterSettings,
                    public_handle: characterHandle,
                },
            };
        }

        function createPendingRoomMessage(body, characterId) {
            const pendingId = nextPendingMessageId();
            const pendingMessage = {
                id: pendingId,
                body,
                characterId,
                state: 'pending',
            };
            pendingRoomMessages.set(pendingId, pendingMessage);

            const row = appendRoomMessageRow(buildOptimisticRoomMessage(body, characterId), {
                pendingId,
                state: 'pending',
                skipSeenId: true,
            });

            pendingMessage.row = row;
            if (container) container.scrollTop = container.scrollHeight;

            return pendingMessage;
        }

        function markPendingRoomMessageFailed(pendingId, errorMessage = '') {
            const pendingMessage = pendingRoomMessages.get(pendingId);
            if (!pendingMessage || !pendingMessage.row) return;

            pendingMessage.state = 'failed';
            pendingMessage.errorMessage = errorMessage;
            pendingMessage.row = replaceRoomMessageRow(
                pendingMessage.row,
                {
                    ...buildOptimisticRoomMessage(pendingMessage.body, pendingMessage.characterId),
                    created_at_human: 'send failed',
                },
                {
                    pendingId,
                    state: 'failed',
                    skipSeenId: true,
                }
            );
        }

        function resolvePendingRoomMessage(pendingId, message) {
            const pendingMessage = pendingRoomMessages.get(pendingId);
            pendingRoomMessages.delete(pendingId);

            const confirmedId = parseInt(message?.id, 10) || 0;
            const existingConfirmedRow = confirmedId > 0
                ? container?.querySelector(`.msg-row[data-message-id="${confirmedId}"]`)
                : null;

            if (existingConfirmedRow) {
                pendingMessage?.row?.remove();
                seenMessageIds.add(confirmedId);
                if (confirmedId > lastMessageId) lastMessageId = confirmedId;
                return existingConfirmedRow;
            }

            if (!pendingMessage?.row) {
                return appendRoomMessageRow(message);
            }

            return replaceRoomMessageRow(pendingMessage.row, message);
        }

        function restoreFailedRoomMessageToComposer(pendingId) {
            const pendingMessage = pendingRoomMessages.get(pendingId);
            if (!pendingMessage || !textarea) return;

            textarea.value = pendingMessage.body;
            syncContentMirror();
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        }

        async function sendRoomMessage(body, characterId, pendingId = null) {
            const data = await fetchJson(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    character_id: characterId,
                    body,
                    room_participation_token: currentRoomParticipationToken(),
                }),
            }, 'Send message');

            if (data?.command === 'cls') {
                clearActiveRoomMessagePane();
                if (pendingId) pendingRoomMessages.delete(pendingId);
                return data;
            }

            if (pendingId) {
                resolvePendingRoomMessage(pendingId, data);
            } else {
                await fetchNewMessages();
            }

            return data;
        }

        textarea?.addEventListener('input', clearComposerError);

        textarea?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                syncContentMirror();
                form?.requestSubmit();
            }
        });

        form?.addEventListener('submit', async function(e) {
            clearComposerError();
            syncContentMirror();
            const id = getTabCharacterId();
            if (hiddenChar) hiddenChar.value = String(id);

            if (isSubmittingMessage) {
                e.preventDefault();
                return;
            }

            const body = (textarea?.value || '').trim();
            if (!id) {
                e.preventDefault();
                showMissingCharacterNotice();
                updateComposerAvailability();
                return;
            }

            if (!body) {
                return;
            }

            e.preventDefault();
            isSubmittingMessage = true;
            if (submitButton) submitButton.disabled = true;

            const isOptimisticEligible = !isSlashCommand(body);
            let pendingId = null;

            if (isOptimisticEligible) {
                pendingId = createPendingRoomMessage(body, id).id;
                if (textarea) textarea.value = '';
                syncContentMirror();
            }

            try {
                await sendRoomMessage(body, id, pendingId);
                if (!isOptimisticEligible) {
                    if (textarea) textarea.value = '';
                    syncContentMirror();
                }
            } catch (error) {
                console.error('Send message error:', error);

                if (error?.status === 422 && error?.data?.code === 'missing_character') {
                    if (pendingId) {
                        pendingRoomMessages.get(pendingId)?.row?.remove();
                        pendingRoomMessages.delete(pendingId);
                    }
                    showMissingCharacterNotice(error.data.message);
                    updateComposerAvailability();
                    return;
                }

                const bodyMessage = Array.isArray(error?.data?.errors?.body) ? error.data.errors.body[0] : null;
                const isClsCommand = body.toLowerCase() === '/cls';
                const fallbackMessage = isClsCommand && error?.status === 403
                    ? 'Only room owners, moderators, or admins can use /cls.'
                    : (isClsCommand && error?.status === 422
                        ? 'The /cls command is only available in rooms.'
                        : 'Could not send message.');

                if (pendingId) {
                    markPendingRoomMessageFailed(pendingId, bodyMessage || error?.data?.message || fallbackMessage);
                } else {
                    if (textarea) textarea.value = body;
                    syncContentMirror();
                }

                showComposerError(bodyMessage || error?.data?.message || fallbackMessage);
                return;
            } finally {
                isSubmittingMessage = false;
                updateComposerAvailability();
            }
        });

        async function fetchJson(url, options, label) {
            const response = await fetch(url, options);
            const text = await response.text();
            let data = null;

            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (error) {
                    console.error(`${label} returned invalid JSON`, {
                        status: response.status,
                        body: text,
                        error,
                    });
                    throw error;
                }
            }

            if (!response.ok) {
                console.error(`${label} failed`, {
                    status: response.status,
                    data,
                });

                const error = new Error(`${label} failed with status ${response.status}`);
                error.status = response.status;
                error.data = data;
                throw error;
            }

            return data;
        }

        function openReportModal(row) {
            if (!row || row.dataset.deleted === '1') return;
            reportMessageId = row.dataset.messageId || null;
            if (!reportMessageId || !reportModal || !reportReason) return;

            reportReason.value = '';
            reportModal.classList.remove('hidden');
            reportReason.focus();
        }

        function closeReportModal() {
            reportMessageId = null;
            reportModal?.classList.add('hidden');
            if (reportReason) reportReason.value = '';
            if (reportSubmit) reportSubmit.disabled = false;
        }

        reportCancel?.addEventListener('click', closeReportModal);
        reportModal?.addEventListener('click', (e) => {
            if (e.target === reportModal) closeReportModal();
        });

        reportForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!reportMessageId || !reportReason) return;

            const reason = reportReason.value.trim();
            if (!reason) {
                reportReason.focus();
                return;
            }

            if (reportSubmit) reportSubmit.disabled = true;

            try {
                const data = await fetchJson(`/messages/${reportMessageId}/reports`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ reason }),
                }, 'Report message');

                if (!data || !data.ok) {
                    console.error('Report message returned an unexpected response', data);
                    if (reportSubmit) reportSubmit.disabled = false;
                    return;
                }

                const row = Array.from(container?.querySelectorAll('.msg-row') || [])
                    .find((candidate) => candidate.dataset.messageId === reportMessageId);
                const reportBtn = row?.querySelector('.msg-report-btn');
                if (reportBtn) {
                    reportBtn.textContent = 'Reported';
                    reportBtn.disabled = true;
                }

                closeReportModal();
            } catch (error) {
                console.error('Report message error:', error);
                if (reportSubmit) reportSubmit.disabled = false;
            }
        });

        /* report/edit/delete */
        function canEditMessageRow(row) {
            if (!row) return false;
            if (row.dataset.canEdit === '1') return true;
            return !!isAdmin;
        }

        async function handleMessageActionClick(e) {
            const target = e.target instanceof Element ? e.target : e.target?.parentElement;
            const actionBtn = target?.closest('.msg-report-btn, .msg-edit-btn, .msg-del-btn, .msg-save-btn, .msg-cancel-btn');
            if (!actionBtn || !container || !container.contains(actionBtn)) return;

            const row = actionBtn.closest('.msg-row');
            if (!row) return;

            const editBtn = row.querySelector('.msg-edit-btn');
            const delBtn  = row.querySelector('.msg-del-btn');
            const reportBtn = row.querySelector('.msg-report-btn');
            const bodyEl  = row.querySelector('.msg-body');
            const editBox = row.querySelector('.msg-editbox');
            const ta      = row.querySelector('.msg-edit-textarea');
            const editedTag = row.querySelector('.msg-edited');
            const deletedTag = row.querySelector('.msg-deleted');
            const messageContent = row.querySelector('[data-body-raw]');

            const id = row.dataset.messageId;
            const isDeleted = row.dataset.deleted === '1';

            if (actionBtn.classList.contains('msg-retry-btn')) {
                const pendingId = row.dataset.pendingId || '';
                const pendingMessage = pendingRoomMessages.get(pendingId);
                if (!pendingMessage || isSubmittingMessage) return;

                clearComposerError();
                isSubmittingMessage = true;
                updateComposerAvailability();

                pendingMessage.state = 'pending';
                pendingMessage.row = replaceRoomMessageRow(
                    row,
                    buildOptimisticRoomMessage(pendingMessage.body, pendingMessage.characterId),
                    {
                        pendingId,
                        state: 'pending',
                        skipSeenId: true,
                    }
                );

                try {
                    await sendRoomMessage(pendingMessage.body, pendingMessage.characterId, pendingId);
                } catch (error) {
                    console.error('Retry send message error:', error);
                    const bodyMessage = Array.isArray(error?.data?.errors?.body) ? error.data.errors.body[0] : null;
                    const fallbackMessage = error?.data?.message || 'Could not send message.';
                    markPendingRoomMessageFailed(pendingId, bodyMessage || fallbackMessage);
                    showComposerError(bodyMessage || fallbackMessage);
                } finally {
                    isSubmittingMessage = false;
                    updateComposerAvailability();
                }
                return;
            }

            if (actionBtn.classList.contains('msg-restore-btn')) {
                const pendingId = row.dataset.pendingId || '';
                restoreFailedRoomMessageToComposer(pendingId);
                return;
            }

            if (actionBtn.classList.contains('msg-report-btn')) {
                openReportModal(row);
                return;
            }

            if (!canEditMessageRow(row)) return;

            if (isDeleted) {
                if (editBtn) editBtn.disabled = true;
                if (delBtn) delBtn.disabled = true;
                if (reportBtn) reportBtn.disabled = true;
                return;
            }

            if (actionBtn.classList.contains('msg-edit-btn')) {
                if (!bodyEl || !editBox || !ta) return;
                ta.value = messageContent?.dataset.bodyRaw ?? bodyEl.textContent ?? '';
                editBox.classList.remove('hidden');
                if (editBtn) editBtn.disabled = true;
                if (delBtn) delBtn.disabled = true;
                return;
            }

            if (actionBtn.classList.contains('msg-cancel-btn')) {
                if (!editBox) return;
                editBox.classList.add('hidden');
                if (editBtn) editBtn.disabled = false;
                if (delBtn) delBtn.disabled = false;
                return;
            }

            if (actionBtn.classList.contains('msg-save-btn')) {
                if (!ta || !bodyEl || !id) return;
                const newBody = ta.value;

                try {
                    const data = await fetchJson(`/messages/${id}`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ body: newBody }),
                    }, 'Edit message');

                    if (!data || !data.ok) {
                        console.error('Edit message returned an unexpected response', data);
                        return;
                    }

                    if (messageContent) messageContent.dataset.bodyRaw = data.message?.body ?? data.message?.content ?? newBody;
                    bodyEl.innerHTML = data.message?.rendered_body_html ?? escHtml(data.message?.body ?? data.message?.content ?? newBody);
                    applyStylesIn(bodyEl.closest('.msg-row') || row);
                    editedTag?.classList.remove('hidden');

                    editBox?.classList.add('hidden');
                    if (editBtn) editBtn.disabled = false;
                    if (delBtn) delBtn.disabled = false;
                } catch (error) {
                    console.error('Edit message error:', error);
                    if (editBtn) editBtn.disabled = false;
                    if (delBtn) delBtn.disabled = false;
                }
                return;
            }

            if (actionBtn.classList.contains('msg-del-btn')) {
                if (!id || !confirm('Delete this message?')) return;

                try {
                    const data = await fetchJson(`/messages/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                    }, 'Delete message');

                    if (!data || !data.ok) {
                        console.error('Delete message returned an unexpected response', data);
                        return;
                    }

                    row.dataset.deleted = '1';
                    if (messageContent) messageContent.dataset.bodyRaw = '[deleted]';
                    if (bodyEl) bodyEl.innerHTML = '[deleted]';
                    deletedTag?.classList.remove('hidden');

                    if (editBtn) editBtn.disabled = true;
                    if (delBtn) delBtn.disabled = true;
                    if (reportBtn) reportBtn.disabled = true;
                    editBox?.classList.add('hidden');
                } catch (error) {
                    console.error('Delete message error:', error);
                }
            }
        }

        /* fetch new messages */
        function fetchNewMessages() {
            fetch(`/rooms/${roomSlug}/messages?after=${lastMessageId}&character_id=${getTabCharacterId()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (!Array.isArray(data) || data.length === 0) return;
                if (!container) return;

                const wasNearBottom =
                    container.scrollHeight - container.scrollTop - container.clientHeight < 80;

                data.forEach(msg => {
                    const mid = parseInt(msg.id, 10);
                    if (!mid || seenMessageIds.has(mid)) return;
                    appendRoomMessageRow(msg);
                });

                if (wasNearBottom) container.scrollTop = container.scrollHeight;
            })
            .catch(() => {});
        }

        function clearActiveRoomMessagePane() {
            if (!container) return;
            container.innerHTML = '';
        }

        function startRoomRealtime() {
            if (!window.Echo || !conversationId) return;

            window.StoryboxChannelCharacters[conversationChannelName] = getTabCharacterId();

            window.Echo.private(`conversation.${conversationId}`)
                .listen('.message.created', (event) => {
                    if (seenMessageIds.has(parseInt(event.id, 10))) return;
                    fetchNewMessages();
                    sendPresencePing();
                })
                .listen('.room.display-cleared', (event) => {
                    const eventRoomId = parseInt(event.room_id ?? 0, 10) || 0;

                    if (!eventRoomId || eventRoomId !== conversationId) {
                        return;
                    }

                    clearActiveRoomMessagePane();
                })
                .listen('.room.character-kicked', (event) => {
                    const targetCharacterId = parseInt(event.target_character_id ?? 0, 10) || 0;

                    if (!targetCharacterId || targetCharacterId !== getTabCharacterId()) {
                        return;
                    }

                    delete roomParticipationTokens[String(targetCharacterId)];
                    delete roomParticipationTokens[targetCharacterId];
                    delete window.StoryboxChannelCharacters[conversationChannelName];
                    window.Echo.leave(`conversation.${conversationId}`);

                    leaveRoom().finally(() => {
                        const reason = (event.reason || '').trim();
                        window.alert(reason ? `You were kicked from this room. Reason: ${reason}` : 'You were kicked from this room.');
                        window.location.href = event.destination || '/chat';
                    });
                });

            window.Echo.connector?.pusher?.connection?.bind('connected', () => fetchNewMessages());
        }

        function refreshActiveRoomSession() {
            if (disableChatPolling) return Promise.resolve();

            return sendPresencePing().finally(() => {
                fetchNewMessages();
                if (panelUsers && !panelUsers.classList.contains('hidden')) refreshUserList();
            });
        }

        if (!disableChatPolling) {
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) refreshActiveRoomSession();
            });

            window.addEventListener('focus', () => refreshActiveRoomSession());
            window.addEventListener('pageshow', () => refreshActiveRoomSession());
        }

        if (!disableChatPolling) {
            setInterval(fetchNewMessages, 2500);
        }

        /* roster */
        function refreshUserList() {
            if (!userListEl) return;

            fetch(`/rooms/${roomSlug}/roster`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                const roster = Array.isArray(data.roster) ? data.roster : [];
                userListEl.innerHTML = '';
                if (activeCountEl) {
                    activeCountEl.innerHTML = `Active <span class="font-medium text-[#d6c8ad]">${roster.length}</span>`;
                }

                if (!roster.length) {
                    userListEl.innerHTML = `<div class="text-[#8f8675]">Nobody here.</div>`;
                    return;
                }

                roster.forEach(p => {
                    let s = p.settings || {};
                    if (typeof s === 'string') { try { s = JSON.parse(s); } catch(e) { s = {}; } }

                    const c1 = s.text_color_1 || '#D8F3FF';
                    const c2 = s.text_color_2 || null;
                    const c3 = s.text_color_3 || null;
                    const c4 = s.text_color_4 || null;
                    const fadeName = !!s.fade_name;

                    const charId = parseInt(p.character_id, 10) || 0;
                    const displayName = (p.character_name ?? ('#' + charId));
                    const displayHandle = (p.character_handle ?? `${displayName}#${shortSigil(charId)}`);
                    const avatar = p.avatar || '';

                    const row = document.createElement('div');
                    row.className = 'char-row rounded border border-[#332817] bg-[#101012] px-3 py-2';

                    const safeNameAttr = escAttr(displayName);
                    const safeAvatarAttr = escAttr(avatar);
                    const safeDisplayName = escHtml(displayName);
                    const nameStyle = escAttr(JSON.stringify({c1,c2,c3,c4,fade:fadeName}));

                    row.innerHTML = `
                        <div class="flex items-center gap-2">
                            ${avatarHtml(avatar, displayName, 'h-8 w-8')}
                            <div class="min-w-0">
                                <button type="button"
                                    class="char-trigger msg-name text-base font-bold leading-none hover:underline text-left cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-500/50 rounded-sm"
                                    data-style='${nameStyle}'
                                    data-character-id="${p.character_id ?? ''}"
                                    data-character-name="${safeNameAttr}"
                                    data-character-handle="${escAttr(displayHandle)}"
                                    data-character-avatar="${safeAvatarAttr}">
                                    ${safeDisplayName}
                                </button>

                                <div class="mt-1 text-[10px] text-[#8f8675]">${escHtml(displayHandle)}</div>
                            </div>
                        </div>
                    `;

                    userListEl.appendChild(row);
                });

                applyStylesIn(userListEl);
            })
            .catch((err) => {
                console.error('Roster error:', err);
                userListEl.innerHTML = `<div class="text-red-400">Roster error</div>`;
                if (activeCountEl) {
                    activeCountEl.innerHTML = `Active <span class="font-medium text-[#8f8675]">unavailable</span>`;
                }
            });
        }
        /* Refresh DMs */
        const dmListEl = document.getElementById('panel-dms');

        /* tabs */
function showRoomsTab() {
    tabRooms.className = 'rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-amber-200';
    tabUsers.className = 'rounded border border-[#332817] px-2 py-1.5 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]';

    panelRooms.classList.remove('hidden');
    panelUsers.classList.add('hidden');
}

function showUsersTab() {
    tabUsers.className = 'rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-amber-200';
    tabRooms.className = 'rounded border border-[#332817] px-2 py-1.5 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]';

    panelRooms.classList.add('hidden');
    panelUsers.classList.remove('hidden');

    refreshUserList();
}

tabRooms?.addEventListener('click', showRoomsTab);
tabUsers?.addEventListener('click', showUsersTab);
showRoomsTab();
refreshUserList();

setInterval(() => {
    if (panelUsers && !panelUsers.classList.contains('hidden')) refreshUserList();
}, 5000);


        if (container) container.scrollTop = container.scrollHeight;
    </script>

    @if ($room->isPublicRoom())
        <x-rules-window :room="$room" />
        <x-world-book-window :room="$room" />
        <x-pinned-notes-window :room="$room" />
        <x-notice-board-window :room="$room" />
    @endif
</x-app-layout>
