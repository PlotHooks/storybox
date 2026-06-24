@php
    $dmOwnedCharacters = auth()->check()
        ? auth()->user()->characters()->orderBy('name')->get(['id', 'name', 'avatar'])
        : collect();
    $dmDefaultFromCharacter = $dmOwnedCharacters->firstWhere('id', (int) session('active_character_id', 0))
        ?? $dmOwnedCharacters->first();
    $dmOwnedCharacterOptions = $dmOwnedCharacters->map(fn ($character) => [
        'id' => (int) $character->id,
        'name' => $character->name,
        'avatar' => $character->avatar,
        'handle' => $character->public_handle,
    ])->values();
@endphp

<div
    id="dm-window"
    class="hidden fixed z-50 bg-[#0b0b0c] border border-[#2a241a] rounded-md shadow-2xl flex flex-col overflow-hidden ring-1 ring-amber-500/10"
    style="
        width: 420px;
        height: 520px;
        top: 120px;
        right: 40px;
    "
>

    <!-- HEADER (drag handle) -->
    <div
        id="dm-drag-handle"
        class="cursor-move flex items-center justify-between px-3 py-2 border-b border-[#2a241a] bg-[#101012]"
    >
        <div>
            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-400">Private Link</div>
            <div class="text-sm text-[#f2dfb5] font-semibold">
                Direct Messages
            </div>
        </div>

        <div class="flex gap-2">
            <button
                id="dm-refresh-btn"
                class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm"
                type="button"
                title="Refresh"
            >
                ↻
            </button>

            <button
                id="dm-close-btn"
                class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm"
                type="button"
                title="Close"
            >
                ✕
            </button>
        </div>
    </div>

    <!-- BODY -->
    <div class="flex-1 flex overflow-hidden">

        <!-- LEFT: conversation list -->
        <div class="w-44 border-r border-[#2a241a] bg-[#0b0b0c] text-xs text-[#d6c8ad] flex flex-col overflow-hidden">
            <div class="p-2 border-b border-[#2a241a] text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">
                Conversations
            </div>

            <div class="p-2 border-b border-[#2a241a] space-y-2">
                <button
                    id="dm-new-btn"
                    type="button"
                    class="w-full rounded border border-amber-500/40 bg-amber-500/10 px-2.5 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                >
                    + New DM
                </button>

                <label for="dm-convo-filter" class="sr-only">Filter conversations</label>
                <input
                    id="dm-convo-filter"
                    type="text"
                    placeholder="Filter conversations"
                    class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-2.5 py-2 text-[11px] text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                >
            </div>

            <div id="dm-convo-list" class="flex-1 overflow-y-auto p-2 space-y-3">
                <div class="text-[#8f8675]">Loading...</div>
            </div>
        </div>

        <!-- RIGHT: message area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <div class="px-3 py-2 border-b border-[#2a241a] bg-[#101012] text-xs text-[#d6c8ad] flex items-center justify-between gap-2">
                <div id="dm-thread-header" class="min-w-0 truncate">
                    Select a conversation.
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <button
                        id="dm-archive-active-btn"
                        type="button"
                        class="hidden rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-xs text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]"
                    >
                        Close DM
                    </button>

                    <button
                        id="dm-block-toggle"
                        type="button"
                        class="hidden shrink-0 text-xs text-red-400 hover:text-red-300"
                    >
                        Block
                    </button>
                </div>
            </div>

            <div id="dm-thread" class="flex-1 bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.035),transparent_22rem)] p-3 text-sm text-[#d6c8ad] overflow-y-auto space-y-2">
                <div class="text-[#8f8675]">No conversation selected.</div>
            </div>

            <div id="dm-thread-footer" class="border-t border-[#2a241a] bg-[#101012] p-2">
                <div class="flex gap-2">
                    <textarea
                        id="dm-input"
                        class="flex-1 resize-none rounded bg-[#0b0b0c] border-[#332817] text-[#d6c8ad] text-sm placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                        rows="2"
                        placeholder="Message..."
                        disabled
                    ></textarea>

                    <button
                        id="dm-send-btn"
                        type="button"
                        class="rounded border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-100 hover:bg-amber-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled
                    >
                        Send
                    </button>
                </div>
                <div class="text-[10px] text-amber-500/70 mt-1">
                    Enter sends. Shift+Enter newline.
                </div>
            </div>
        </div>

    </div>

    <!-- RESIZE HANDLE -->
    <div
        id="dm-resize"
        class="absolute bottom-0 right-0 w-4 h-4 cursor-se-resize"
        title="Resize"
    ></div>
</div>

<div id="dm-char-popover"
    class="hidden fixed z-[9999] w-64 rounded-lg border border-[#332817] bg-[#101012] shadow-xl">
    <div class="p-3">
        <div class="flex items-start gap-3">
            <div id="dm-char-popover-avatar" class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#332817] bg-[#0b0b0c] text-2xl font-semibold text-[#8f8675]"></div>
            <div class="min-w-0">
                <div id="dm-char-popover-title" class="font-semibold text-[#f2dfb5] text-sm"></div>
                <div class="text-[10px] text-[#8f8675] mt-1">ID verification</div>
            </div>
        </div>

        <div class="mt-3 flex gap-2 justify-end">
            <a id="dm-char-popover-profile"
               href="#"
               target="_blank"
               rel="noreferrer noopener"
               class="rounded border border-[#332817] bg-[#141416] px-2 py-1 text-xs text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#191511] hover:text-[#f2dfb5]">
                View Profile
            </a>
        </div>
    </div>
</div>


<script>
(function () {
    const dmWindow = document.getElementById('dm-window');
    if (!dmWindow) return;

    const csrf = @json(csrf_token());
    const dmTargetsUrl = @json(route('dms.targets'));
    const dmStartUrl = @json(route('dms.start'));
    const ownedDmCharacters = (Array.isArray(@json($dmOwnedCharacterOptions)) ? @json($dmOwnedCharacterOptions) : [])
        .map((character) => ({
            id: parseInt(character.id || 0, 10) || 0,
            name: character.name || 'Character',
            avatar: character.avatar || '',
            handle: character.handle || '',
        }))
        .filter((character) => character.id > 0);
    const defaultDmFromCharacterId = parseInt(@json((int) ($dmDefaultFromCharacter?->id ?? 0)), 10) || 0;

    const listEl = document.getElementById('dm-convo-list');
    const convoFilterInput = document.getElementById('dm-convo-filter');
    const globalUnreadBadge = document.getElementById('dm-unread-badge');
    const refreshBtn = document.getElementById('dm-refresh-btn');
    const closeBtn = document.getElementById('dm-close-btn');
    const newDmBtn = document.getElementById('dm-new-btn');

    const threadHeader = document.getElementById('dm-thread-header');
    const archiveActiveBtn = document.getElementById('dm-archive-active-btn');
    const blockToggleBtn = document.getElementById('dm-block-toggle');
    const threadEl = document.getElementById('dm-thread');
    const inputEl = document.getElementById('dm-input');
    const sendBtn = document.getElementById('dm-send-btn');
    const threadFooter = document.getElementById('dm-thread-footer');

    let refreshListTimer = null;
    let pollDmTimer = null;
    let pollInFlight = false;
    let roomsLoaded = false;
    let dmComposeSearchTimer = null;
    let dmComposeSearchController = null;

    let activeDm = {
        slug: null,
        conversationId: 0,
        lastId: 0,
        displayName: null,
        profileUrl: '',
        avatar: '',
        handle: '',
        otherUserId: 0,
        myCharacterId: 0,
        otherCharacterId: 0,
        isBlockedByViewer: false,
    };

    let activeRealtimeConversationId = 0;
    let dmReconnectHandlerBound = false;
    let dmConversationFilter = '';
    let archivedSectionExpanded = false;
    const dmListRealtimeConversationIds = new Set();
    const dmRoomsBySlug = new Map();
    const dmMessageCache = new Map();
    const lastDmSlugStorageKey = 'storybox_last_dm_slug';
    const dmComposerState = {
        active: false,
        fromCharacterId: defaultDmFromCharacterId,
        query: '',
        results: [],
        error: '',
        loading: false,
        starting: false,
    };


    const dmCharPopover = document.getElementById('dm-char-popover');
    const dmCharPopoverTitle = document.getElementById('dm-char-popover-title');
    const dmCharPopoverAvatar = document.getElementById('dm-char-popover-avatar');
    const dmCharPopoverProfile = document.getElementById('dm-char-popover-profile');

    function hideDmCharPopover() {
        if (!dmCharPopover) return;
        dmCharPopover.classList.add('hidden');
        syncDmCharPopoverState(false);
    }

    function positionDmCharPopoverNear(el) {
        if (!dmCharPopover || !el) return;
        const r = el.getBoundingClientRect();
        let top = r.bottom + 8;
        let left = r.left;
        const pad = 8;
        const w = dmCharPopover.offsetWidth || 256;
        const h = dmCharPopover.offsetHeight || 140;

        if (left + w > window.innerWidth - pad) left = window.innerWidth - w - pad;
        if (top + h > window.innerHeight - pad) top = r.top - h - 8;
        if (left < pad) left = pad;
        if (top < pad) top = pad;

        dmCharPopover.style.left = `${left}px`;
        dmCharPopover.style.top = `${top}px`;
    }

    function openDmCharPopover(triggerEl) {
        if (!dmCharPopover || !triggerEl) return;

        const characterId = (triggerEl.dataset.characterId || '').trim();
        const characterName = (triggerEl.dataset.characterName || triggerEl.textContent || '').trim();
        const characterHandle = (triggerEl.dataset.characterHandle || '').trim();
        const avatar = (triggerEl.dataset.characterAvatar || '').trim();
        const fallbackHandle = characterId ? `${characterName}#${shortSigil(parseInt(characterId, 10))}` : characterName;

        if (dmCharPopoverTitle) dmCharPopoverTitle.textContent = characterHandle || fallbackHandle;
        if (dmCharPopoverAvatar) dmCharPopoverAvatar.innerHTML = avatarHtml(avatar, characterName, 'h-20 w-20', 'rounded-lg');
        if (dmCharPopoverProfile) {
            const hasCharacter = Boolean(characterId);
            dmCharPopoverProfile.href = hasCharacter ? `/characters/${characterId}/profile` : '#';
            dmCharPopoverProfile.classList.toggle('hidden', !hasCharacter);
        }

        dmCharPopover.classList.remove('hidden');
        syncDmCharPopoverState(true);
        positionDmCharPopoverNear(triggerEl);
    }

    let dmCharPopoverOpen = false;

    function syncDmCharPopoverState(open) {
        dmCharPopoverOpen = open;
    }

    window.StoryboxChannelCharacters = window.StoryboxChannelCharacters || {};

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : event.target?.parentElement;
        const trigger = target?.closest('.dm-char-trigger');
        const clickedInsidePopover = dmCharPopover && event.target instanceof Node && dmCharPopover.contains(event.target);

        if (trigger) {
            event.preventDefault();
            event.stopPropagation();
            openDmCharPopover(trigger);
            return;
        }

        if (!clickedInsidePopover && dmCharPopoverOpen) {
            hideDmCharPopover();
        }
    });

    window.addEventListener('resize', () => hideDmCharPopover());
    window.addEventListener('scroll', () => hideDmCharPopover(), true);

    function isOpen() {
        return !dmWindow.classList.contains('hidden');
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttr(s) {
        return escapeHtml(s);
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

    function avatarHtml(url, name, sizeClass = 'h-8 w-8', shapeClass = 'rounded-full') {
        if (isSafeAvatarUrl(url)) {
            return `<img src="${escapeAttr(url)}" alt="${escapeAttr(name)} avatar" loading="lazy" referrerpolicy="no-referrer" class="${sizeClass} shrink-0 ${shapeClass} object-cover">`;
        }

        return `<div class="flex ${sizeClass} shrink-0 items-center justify-center ${shapeClass} border border-[#332817] bg-[#0b0b0c] text-xs font-semibold text-[#8f8675]">${escapeHtml(avatarInitial(name))}</div>`;
    }


    function characterTriggerHtml({ characterId = 0, name = '', handle = '', avatar = '', contentHtml = '', extraClass = '' }) {
        if (!characterId) return contentHtml;

        return `<button
            type="button"
            class="dm-char-trigger ${extraClass}"
            data-character-id="${characterId}"
            data-character-name="${escapeAttr(name)}"
            data-character-handle="${escapeAttr(handle || name)}"
            data-character-avatar="${escapeAttr(avatar)}"
        >${contentHtml}</button>`;
    }

    function setThreadEnabled(enabled) {
        if (inputEl) inputEl.disabled = !enabled;
        if (sendBtn) sendBtn.disabled = !enabled;
    }

    function setThreadFooterVisible(visible) {
        if (!threadFooter) return;
        threadFooter.classList.toggle('hidden', !visible);
    }

    function resolveDmFromCharacterId(candidate = 0) {
        const normalized = parseInt(candidate || 0, 10) || 0;
        if (ownedDmCharacters.some((character) => character.id === normalized)) {
            return normalized;
        }

        return ownedDmCharacters[0]?.id || 0;
    }

    function normalizeDmTarget(raw) {
        const id = parseInt(raw?.id || 0, 10) || 0;
        if (!id) return null;

        return {
            id,
            name: raw?.name || 'Character',
            avatar: raw?.avatar || '',
            handle: raw?.handle || '',
        };
    }

    function abortDmTargetSearch() {
        if (dmComposeSearchTimer) {
            clearTimeout(dmComposeSearchTimer);
            dmComposeSearchTimer = null;
        }

        if (dmComposeSearchController) {
            dmComposeSearchController.abort();
            dmComposeSearchController = null;
        }
    }

    function resetDmComposerState() {
        abortDmTargetSearch();
        dmComposerState.active = false;
        dmComposerState.fromCharacterId = resolveDmFromCharacterId(defaultDmFromCharacterId);
        dmComposerState.query = '';
        dmComposerState.results = [];
        dmComposerState.error = '';
        dmComposerState.loading = false;
        dmComposerState.starting = false;
    }

    function updateNewDmComposerResults() {
        if (!dmComposerState.active || !threadEl) return;

        const fromSelect = threadEl.querySelector('#dm-compose-from');
        const searchInput = threadEl.querySelector('#dm-compose-search');
        const resultsEl = threadEl.querySelector('#dm-compose-results');
        if (!resultsEl) return;

        if (fromSelect) {
            fromSelect.value = String(resolveDmFromCharacterId(dmComposerState.fromCharacterId));
        }

        if (searchInput && searchInput.value !== dmComposerState.query) {
            searchInput.value = dmComposerState.query;
        }

        const hasFromCharacter = !!resolveDmFromCharacterId(dmComposerState.fromCharacterId);
        let markup = '';

        if (!hasFromCharacter) {
            markup = '<div class="text-[#8f8675]">You need at least one character to start a DM.</div>';
        } else if (dmComposerState.error) {
            markup = `<div class="text-red-400">${escapeHtml(dmComposerState.error)}</div>`;
        } else if (dmComposerState.starting) {
            markup = '<div class="text-amber-200">Opening DM...</div>';
        } else if (dmComposerState.loading) {
            markup = '<div class="text-[#8f8675]">Searching...</div>';
        } else if (dmComposerState.query.trim() === '') {
            markup = '<div class="text-[#8f8675]">Type a character or user name to search.</div>';
        } else if (dmComposerState.results.length === 0) {
            markup = '<div class="text-[#8f8675]">No matching characters available.</div>';
        } else {
            markup = dmComposerState.results.map((target) => `
                <button
                    type="button"
                    data-dm-target-id="${target.id}"
                    class="w-full rounded border border-[#332817] bg-[#0b0b0c] px-2.5 py-2 text-left text-[#d6c8ad] hover:border-amber-500/40 hover:bg-[#141416] focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                >
                    <div class="flex items-center gap-2">
                        ${avatarHtml(target.avatar, target.name, 'h-8 w-8')}
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-[#f2dfb5]">${escapeHtml(target.name)}</div>
                            <div class="truncate text-[11px] text-[#8f8675]">${escapeHtml(target.handle)}</div>
                        </div>
                    </div>
                </button>
            `).join('');
        }

        resultsEl.innerHTML = markup;
        resultsEl.querySelectorAll('[data-dm-target-id]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = parseInt(button.dataset.dmTargetId || '0', 10) || 0;
                if (targetId) {
                    startDmFromComposer(targetId);
                }
            });
        });
    }

    function requestDmTargets() {
        const fromCharacterId = resolveDmFromCharacterId(dmComposerState.fromCharacterId);
        const query = dmComposerState.query.trim();

        if (!dmComposerState.active) return;

        if (!fromCharacterId || query === '') {
            dmComposerState.loading = false;
            dmComposerState.results = [];
            updateNewDmComposerResults();
            return;
        }

        abortDmTargetSearch();
        dmComposeSearchController = new AbortController();

        const url = new URL(dmTargetsUrl, window.location.origin);
        url.searchParams.set('from_character_id', String(fromCharacterId));
        url.searchParams.set('query', query);

        fetch(url.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            signal: dmComposeSearchController.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    let message = 'Could not search characters.';
                    try {
                        const data = await response.json();
                        if (typeof data?.message === 'string' && data.message) {
                            message = data.message;
                        }
                    } catch (error) {
                        // Ignore parse errors and fall back to the default message.
                    }

                    throw new Error(message);
                }

                return response.json();
            })
            .then((data) => {
                dmComposeSearchController = null;
                if (!dmComposerState.active) return;

                dmComposerState.loading = false;
                dmComposerState.error = '';
                dmComposerState.results = Array.isArray(data?.targets)
                    ? data.targets.map(normalizeDmTarget).filter(Boolean)
                    : [];
                updateNewDmComposerResults();
            })
            .catch((error) => {
                if (error?.name === 'AbortError') return;

                dmComposeSearchController = null;
                if (!dmComposerState.active) return;

                dmComposerState.loading = false;
                dmComposerState.results = [];
                dmComposerState.error = error?.message || 'Could not search characters.';
                updateNewDmComposerResults();
            });
    }

    function renderNewDmComposer() {
        if (!threadEl) return;

        dmComposerState.active = true;
        dmComposerState.fromCharacterId = resolveDmFromCharacterId(dmComposerState.fromCharacterId || defaultDmFromCharacterId);

        if (threadHeader) {
            threadHeader.textContent = 'New Direct Message';
        }

        blockToggleBtn?.classList.add('hidden');
        setThreadEnabled(false);
        setThreadFooterVisible(false);

        const hasFromCharacter = !!resolveDmFromCharacterId(dmComposerState.fromCharacterId);
        const fromOptions = ownedDmCharacters.map((character) => `
            <option value="${character.id}" ${character.id === dmComposerState.fromCharacterId ? 'selected' : ''}>${escapeHtml(character.name)} (${escapeHtml(character.handle)})</option>
        `).join('');

        threadEl.innerHTML = `
            <div class="space-y-4">
                <div>
                    <div class="text-sm font-semibold text-[#f2dfb5]">New Direct Message</div>
                    <p class="mt-1 text-xs leading-relaxed text-[#8f8675]">Choose which of your characters is sending, then search for a recipient character.</p>
                </div>
                <div>
                    <label for="dm-compose-from" class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">From</label>
                    <select
                        id="dm-compose-from"
                        class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"
                        ${hasFromCharacter ? '' : 'disabled'}
                    >
                        ${fromOptions || '<option value="">No characters available</option>'}
                    </select>
                </div>
                <div>
                    <label for="dm-compose-search" class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">To</label>
                    <input
                        id="dm-compose-search"
                        type="text"
                        value="${escapeAttr(dmComposerState.query)}"
                        placeholder="Search characters"
                        class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                        ${hasFromCharacter ? '' : 'disabled'}
                    >
                    <div class="mt-2 text-[10px] uppercase tracking-[0.14em] text-amber-500/70">Selecting a target will open an existing DM or start a new one.</div>
                </div>
                <div id="dm-compose-results" class="space-y-2 rounded border border-[#2a241a] bg-[#101012] p-2"></div>
            </div>
        `;

        const fromSelect = threadEl.querySelector('#dm-compose-from');
        const searchInput = threadEl.querySelector('#dm-compose-search');

        fromSelect?.addEventListener('change', () => {
            dmComposerState.fromCharacterId = resolveDmFromCharacterId(fromSelect.value);
            dmComposerState.error = '';
            dmComposerState.results = [];
            if (dmComposerState.query.trim() !== '') {
                dmComposerState.loading = true;
                updateNewDmComposerResults();
                requestDmTargets();
                return;
            }

            updateNewDmComposerResults();
        });

        searchInput?.addEventListener('input', () => {
            dmComposerState.query = searchInput.value;
            dmComposerState.error = '';
            dmComposerState.results = [];
            dmComposerState.starting = false;
            abortDmTargetSearch();

            if (dmComposerState.query.trim() === '') {
                dmComposerState.loading = false;
                updateNewDmComposerResults();
                return;
            }

            dmComposerState.loading = true;
            updateNewDmComposerResults();
            dmComposeSearchTimer = window.setTimeout(() => requestDmTargets(), 200);
        });

        updateNewDmComposerResults();
        searchInput?.focus();
    }

    function startDmFromComposer(targetId) {
        const fromCharacterId = resolveDmFromCharacterId(dmComposerState.fromCharacterId);
        const target = dmComposerState.results.find((entry) => entry.id === targetId);
        if (!fromCharacterId || !target) return;

        dmComposerState.error = '';
        dmComposerState.starting = true;
        updateNewDmComposerResults();

        fetch(dmStartUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                my_character_id: fromCharacterId,
                other_character_id: target.id,
            })
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(typeof data?.message === 'string' && data.message ? data.message : 'Could not start DM.');
                }

                return data;
            })
            .then((data) => fetchDmRooms({ showLoading: false }).then(() => {
                dmComposerState.starting = false;

                if (!dmRoomsBySlug.has(data.slug)) {
                    const placeholder = {
                        roomId: 0,
                        slug: data.slug || '',
                        updatedAt: null,
                        archivedAt: null,
                        displayName: target.name,
                        avatar: target.avatar || '',
                        unreadCount: 0,
                        myCharacterId: fromCharacterId,
                        otherCharacterId: target.id,
                        isBlockedByViewer: false,
                    };
                    dmRoomsBySlug.set(placeholder.slug, placeholder);
                    renderRooms(Array.from(dmRoomsBySlug.values()));
                }

                openConversation(data.slug);
            }))
            .catch((error) => {
                dmComposerState.starting = false;
                dmComposerState.error = error?.message || 'Could not start DM.';
                updateNewDmComposerResults();
            });
    }

    function showNewDmComposer() {
        clearThread();
        dmComposerState.fromCharacterId = resolveDmFromCharacterId(defaultDmFromCharacterId);
        dmComposerState.query = '';
        dmComposerState.results = [];
        dmComposerState.error = '';
        dmComposerState.loading = false;
        dmComposerState.starting = false;
        renderNewDmComposer();
    }

    window.setCharacterBlock = window.setCharacterBlock || function setCharacterBlock(blockerId, blockedId, shouldBlock) {
        const action = shouldBlock ? 'Block this character?' : 'Unblock this character?';
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
        .catch(() => alert(shouldBlock ? 'Failed to block character.' : 'Failed to unblock character.'));
    };

    function syncDmBlockToggle() {
        if (!blockToggleBtn) return;

        const canToggle = !!activeDm.myCharacterId && !!activeDm.otherCharacterId;
        blockToggleBtn.classList.toggle('hidden', !canToggle);
        if (!canToggle) return;

        blockToggleBtn.textContent = activeDm.isBlockedByViewer ? 'Blocked' : 'Block';
        blockToggleBtn.className = 'shrink-0 rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-xs ' + (
            activeDm.isBlockedByViewer
                ? 'text-[#8f8675] hover:text-[#d6c8ad]'
                : 'text-red-400 hover:text-red-300'
        );
    }

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

    function incrementUnreadBadge(badge) {
        if (!badge) return;
        setUnreadBadge(badge, parseUnreadCount(badge.dataset.unreadCount) + 1);
    }

    function updateGlobalUnreadBadge() {
        if (!globalUnreadBadge) return;

        const total = Array.from(dmRoomsBySlug.values()).reduce((sum, room) => {
            return sum + parseUnreadCount(room.unreadCount);
        }, 0);

        setUnreadBadge(globalUnreadBadge, total);
    }

    function updateGlobalUnreadBadgeFromRooms(rooms) {
        if (!globalUnreadBadge) return;

        const total = (Array.isArray(rooms) ? rooms : []).reduce((sum, room) => {
            return sum + parseUnreadCount(room.unreadCount ?? room.unread_count);
        }, 0);

        setUnreadBadge(globalUnreadBadge, total);
    }

    function findRoomByConversationId(conversationId) {
        for (const room of dmRoomsBySlug.values()) {
            if (room.roomId === conversationId) return room;
        }

        return null;
    }

    function setRoomUnreadCount(slug, count) {
        const room = dmRoomsBySlug.get(slug);
        if (!room) return;
        room.unreadCount = parseUnreadCount(count);
    }

    function clearDmUnread(conversationId, slug = null) {
        const badge = listEl?.querySelector(`[data-dm-unread-badge="${conversationId}"]`);
        setUnreadBadge(badge, 0);

        const room = slug ? dmRoomsBySlug.get(slug) : findRoomByConversationId(conversationId);
        if (room?.slug) setRoomUnreadCount(room.slug, 0);

        updateGlobalUnreadBadge();
    }

    function incrementDmUnread(conversationId) {
        const normalizedConversationId = parseInt(conversationId || 0, 10) || 0;
        if (!normalizedConversationId) return;

        if (isOpen() && activeDm.conversationId === normalizedConversationId) {
            return;
        }

        const room = findRoomByConversationId(normalizedConversationId);
        if (room?.slug) {
            room.unreadCount = parseUnreadCount(room.unreadCount) + 1;
        }

        const badge = listEl?.querySelector(`[data-dm-unread-badge="${normalizedConversationId}"]`);
        incrementUnreadBadge(badge);
        updateGlobalUnreadBadge();
    }

    function getMessageCache(slug) {
        if (!slug) return null;

        if (!dmMessageCache.has(slug)) {
            dmMessageCache.set(slug, {
                messages: [],
                messageIds: new Set(),
                lastId: 0,
                loaded: false,
                loading: false,
                scrollTop: null,
            });
        }

        return dmMessageCache.get(slug);
    }

    function normalizeRoom(raw) {
        const roomId = parseInt((raw.roomId ?? raw.room_id ?? 0), 10) || 0;
        const myCharacterId = parseInt((raw.myCharacterId ?? raw.my_character_id ?? 0), 10) || 0;
        const otherCharacterId = parseInt((raw.otherCharacterId ?? raw.other_character_id ?? 0), 10) || 0;
        const profileUrl = raw.profileUrl || raw.profile_url || raw.other_character_profile_url || (otherCharacterId ? `/characters/${otherCharacterId}/profile` : '');

        return {
            roomId,
            slug: raw.slug || '',
            updatedAt: raw.updatedAt ?? raw.updated_at ?? null,
            archivedAt: raw.archivedAt ?? raw.archived_at ?? null,
            displayName: raw.displayName || raw.other_character_name || 'DM',
            handle: raw.handle || raw.other_character_handle || raw.other_character_name || 'DM',
            avatar: raw.avatar || raw.other_character_avatar || '',
            profileUrl,
            unreadCount: parseUnreadCount(raw.unreadCount ?? raw.unread_count),
            myCharacterId,
            otherCharacterId,
            otherUserId: parseInt((raw.otherUserId ?? raw.other_user_id ?? 0), 10) || 0,
            isBlockedByViewer: parseBool(raw.isBlockedByViewer ?? raw.is_blocked_by_viewer),
        };
    }

    function isArchivedRoom(room) {
        return !!room?.archivedAt;
    }

    function matchesConversationFilter(room) {
        const needle = dmConversationFilter.trim().toLowerCase();
        if (!needle) return true;

        return String(room.displayName || '').toLowerCase().includes(needle)
            || String(room.slug || '').toLowerCase().includes(needle);
    }

    function getLastOpenedDmSlug() {
        try {
            return sessionStorage.getItem(lastDmSlugStorageKey) || '';
        } catch (e) {
            return '';
        }
    }

    function setLastOpenedDmSlug(slug) {
        if (!slug) return;

        try {
            sessionStorage.setItem(lastDmSlugStorageKey, slug);
        } catch (e) {
            // Ignore storage failures and continue with in-memory selection only.
        }
    }

    function clearLastOpenedDmSlug() {
        try {
            sessionStorage.removeItem(lastDmSlugStorageKey);
        } catch (e) {
            // Ignore storage failures and continue with in-memory selection only.
        }
    }

    function resolveDefaultConversationSlug(rooms) {
        const normalizedRooms = Array.isArray(rooms)
            ? rooms.map(normalizeRoom).filter((room) => !!room.slug)
            : [];
        const activeRooms = normalizedRooms.filter((room) => !isArchivedRoom(room));

        if (activeDm.slug && normalizedRooms.some((room) => room.slug === activeDm.slug)) {
            return activeDm.slug;
        }

        const storedSlug = getLastOpenedDmSlug();
        if (storedSlug && normalizedRooms.some((room) => room.slug === storedSlug)) {
            return storedSlug;
        }

        return activeRooms[0]?.slug || normalizedRooms[0]?.slug || null;
    }

    function roomButtonClass(isActive) {
        return 'min-w-0 flex-1 text-left rounded border px-2 py-2 transition-colors ' + (
            isActive
                ? 'border-amber-500/40 bg-amber-500/10 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.10)]'
                : 'border-[#332817] bg-[#101012] hover:border-amber-500/40 hover:bg-[#141416]'
        );
    }

    function roomRowMarkup(room) {
        const isActive = activeDm.slug && room.slug === activeDm.slug;

        return `
            <div class="flex items-start gap-2" data-dm-row="${escapeAttr(room.slug)}">
                <button
                    type="button"
                    data-dm-open="${escapeAttr(room.slug)}"
                    class="${roomButtonClass(isActive)}"
                >
                    <div class="flex items-center gap-2">
                        ${avatarHtml(room.avatar, room.displayName, 'h-7 w-7')}
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs text-[#d6c8ad]">${escapeHtml(room.displayName)}</div>
                            <div class="truncate text-[10px] text-[#8f8675]">${escapeHtml(room.slug)}</div>
                        </div>
                        <span
                            data-dm-unread-badge="${room.roomId}"
                            data-unread-count="${room.unreadCount}"
                            class="${room.unreadCount > 0 ? '' : 'hidden'} shrink-0 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                            ${formatUnreadCount(room.unreadCount)}
                        </span>
                    </div>
                </button>
            </div>
        `;
    }

    function roomSectionMarkup(label, rooms, emptyMessage = '') {
        if (rooms.length > 0) {
            return `<div class="space-y-2">${rooms.map(roomRowMarkup).join('')}</div>`;
        }

        if (!emptyMessage) return '';
        return `<div class="rounded border border-dashed border-[#2a241a] px-2 py-3 text-[11px] text-[#8f8675]">${escapeHtml(emptyMessage)}</div>`;
    }

    function refreshRoomButton() {
        renderRooms(Array.from(dmRoomsBySlug.values()));
    }

    function syncActiveConversationHighlight() {
        if (!listEl) return;
        renderRooms(Array.from(dmRoomsBySlug.values()));
    }

    function setRoomListMessage(message, isError = false) {
        if (!listEl) return;
        listEl.innerHTML = `<div class="${isError ? 'text-red-400' : 'text-[#8f8675]'}">${escapeHtml(message)}</div>`;
    }

    function getPartitionedRooms(rooms) {
        const normalizedRooms = Array.isArray(rooms)
            ? rooms.map(normalizeRoom).filter((room) => !!room.slug)
            : [];

        return {
            normalizedRooms,
            activeConversations: normalizedRooms.filter((room) => !isArchivedRoom(room)),
            archivedConversations: normalizedRooms.filter((room) => isArchivedRoom(room)),
        };
    }

    function toggleArchivedSection() {
        archivedSectionExpanded = !archivedSectionExpanded;
        renderRooms(Array.from(dmRoomsBySlug.values()));
    }

    function toggleDmArchiveState(slug, archived, options = {}) {
        const room = dmRoomsBySlug.get(slug);
        if (!room) return Promise.resolve(false);

        return fetch(`/dms/${encodeURIComponent(slug)}/${archived ? 'restore' : 'archive'}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(typeof data?.message === 'string' && data.message ? data.message : 'Could not update DM state.');
                }

                return data;
            })
            .then((data) => {
                room.archivedAt = archived ? null : (data?.archived_at || new Date().toISOString());

                if (!archived && !archivedSectionExpanded) {
                    archivedSectionExpanded = true;
                }

                renderRooms(Array.from(dmRoomsBySlug.values()));

                if (options.skipRefresh === true) {
                    return room;
                }

                return fetchDmRooms({ showLoading: false }).then(() => dmRoomsBySlug.get(slug) || room);
            })
            .catch((error) => {
                console.error('DM archive toggle error:', error);
                return false;
            });
    }

    function syncActiveDmArchiveControl() {
        if (!archiveActiveBtn) return;

        const room = activeDm.slug ? dmRoomsBySlug.get(activeDm.slug) : null;
        const canToggle = !!activeDm.slug && !!room && !dmComposerState.active;
        archiveActiveBtn.classList.toggle('hidden', !canToggle);

        if (!canToggle) return;

        const archived = isArchivedRoom(room);
        archiveActiveBtn.textContent = archived ? 'Restore DM' : 'Close DM';
        archiveActiveBtn.title = archived ? 'Restore this conversation' : 'Close this conversation';
    }

    function closeActiveDmConversation() {
        if (!activeDm.slug) return;

        const currentSlug = activeDm.slug;
        const currentRoom = dmRoomsBySlug.get(currentSlug);
        const shouldRestore = !!currentRoom && isArchivedRoom(currentRoom);

        toggleDmArchiveState(currentSlug, shouldRestore, { skipRefresh: true })
            .then((result) => {
                if (!result) return;

                if (shouldRestore) {
                    openConversation(currentSlug);
                    fetchDmRooms({ showLoading: false });
                    return;
                }

                clearLastOpenedDmSlug();
                clearThread();

                return fetchDmRooms({ showLoading: false }).then((rooms) => {
                    const { activeConversations } = getPartitionedRooms(rooms);
                    const nextActiveRoom = activeConversations[0] || null;

                    if (nextActiveRoom?.slug) {
                        openConversation(nextActiveRoom.slug);
                    }
                });
            });
    }

    function bindRoomListInteractions() {
        if (!listEl) return;

        listEl.querySelectorAll('[data-dm-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const slug = button.dataset.dmOpen || '';
                if (slug) openConversation(slug);
            });
        });

        listEl.querySelectorAll('[data-dm-archived-section-toggle]').forEach((button) => {
            button.addEventListener('click', toggleArchivedSection);
        });
    }

    function renderRooms(rooms) {
        if (!listEl) return;

        const { normalizedRooms, activeConversations, archivedConversations } = getPartitionedRooms(rooms);
        roomsLoaded = true;

        dmRoomsBySlug.clear();
        normalizedRooms.forEach((room) => {
            dmRoomsBySlug.set(room.slug, room);
        });
        updateGlobalUnreadBadge();
        syncActiveDmArchiveControl();

        if (normalizedRooms.length === 0) {
            clearLastOpenedDmSlug();
            setRoomListMessage('No DMs yet.');
            return;
        }

        const previousScrollTop = listEl.scrollTop;
        const visibleActiveRooms = activeConversations.filter(matchesConversationFilter);
        const visibleArchivedRooms = archivedConversations.filter(matchesConversationFilter);
        const hasFilter = dmConversationFilter.trim() !== '';

        const activeMarkup = roomSectionMarkup(
            'Active',
            visibleActiveRooms,
            archivedConversations.length > 0 || hasFilter ? 'No active conversations match this filter.' : 'No active DMs yet.'
        );
        const archivedHeader = `
            <button
                type="button"
                data-dm-archived-section-toggle
                class="flex w-full items-center justify-between rounded border border-[#2a241a] bg-[#101012] px-2 py-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675] hover:border-amber-500/30 hover:text-[#d6c8ad]"
            >
                <span>Archived (${archivedConversations.length})</span>
                <span>${archivedSectionExpanded ? '−' : '+'}</span>
            </button>
        `;
        const archivedMarkup = archivedConversations.length > 0
            ? archivedHeader + (archivedSectionExpanded
                ? `<div class="mt-2">${roomSectionMarkup('Archived', visibleArchivedRooms, hasFilter ? 'No archived conversations match this filter.' : 'No archived DMs.')}</div>`
                : '')
            : '';

        listEl.innerHTML = `
            <div class="space-y-3">
                <div>
                    <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">Active</div>
                    ${activeMarkup}
                </div>
                ${archivedConversations.length > 0 ? '<div class="border-t border-[#2a241a] pt-3">' + archivedMarkup + '</div>' : ''}
            </div>
        `;

        listEl.scrollTop = previousScrollTop;
        bindRoomListInteractions();
        syncDmListRealtimeSubscriptions(normalizedRooms);
    }

    function fetchDmRooms(options = {}) {
        const { showLoading = false } = options;

        try {
            if (!isOpen() || !listEl) return Promise.resolve([]);
        } catch (error) {
            console.error('DM list preflight error:', error);
            return Promise.resolve([]);
        }

        if (showLoading && !roomsLoaded) {
            setRoomListMessage('Loading...');
        }

        return fetch('/dms', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(async (response) => {
            if (!response.ok) {
                throw new Error(`DM list request failed (${response.status})`);
            }

            return response.json();
        })
        .then(data => {
            const rooms = data && Array.isArray(data.rooms) ? data.rooms : [];

            try {
                renderRooms(rooms);
            } catch (error) {
                console.error('DM list render error:', error);
                if (!roomsLoaded) setRoomListMessage('Could not load DMs.', true);
                return [];
            }

            return rooms;
        })
        .catch(err => {
            console.error('DM list error:', err);
            if (!roomsLoaded) setRoomListMessage('Could not load DMs.', true);
            return [];
        });
    }

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
        el.style.display = 'inline-block';
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
        try { s = JSON.parse(el.dataset.style || '{}'); }
        catch (e) { s = {}; }

        const stops = buildStops(s);
        const shouldFade = !!s.fade && stops.length >= 2;

        if (shouldFade) applyGradientText(el, stops);
        else applySolidText(el, s.c1);
    }

    function applyStylesIn(root) {
        (root || document).querySelectorAll('.msg-name, .msg-body').forEach(applyStyleFromDataset);
    }

    function replaceCachedMessages(slug, msgs) {
        const cache = getMessageCache(slug);
        if (!cache) return;

        cache.messages = [];
        cache.messageIds.clear();
        cache.lastId = 0;

        (Array.isArray(msgs) ? msgs : []).forEach((message) => {
            const id = parseInt(message?.id || 0, 10);
            if (!id || cache.messageIds.has(id)) return;
            cache.messageIds.add(id);
            cache.messages.push(message);
            if (id > cache.lastId) cache.lastId = id;
        });

        cache.loaded = true;
    }

    function appendCachedMessages(slug, msgs) {
        const cache = getMessageCache(slug);
        if (!cache) return false;

        let changed = false;

        (Array.isArray(msgs) ? msgs : []).forEach((message) => {
            const id = parseInt(message?.id || 0, 10);
            if (!id || cache.messageIds.has(id)) return;
            cache.messageIds.add(id);
            cache.messages.push(message);
            if (id > cache.lastId) cache.lastId = id;
            changed = true;
        });

        if (changed) {
            cache.messages.sort((a, b) => (parseInt(a?.id || 0, 10) || 0) - (parseInt(b?.id || 0, 10) || 0));
            cache.loaded = true;
        }

        return changed;
    }

    function renderConversationLoading() {
        if (!threadEl) return;
        threadEl.innerHTML = `<div class="text-[#8f8675]">Loading...</div>`;
    }

    function renderActiveConversation(options = {}) {
        if (!threadEl || !activeDm.slug) return;

        const cache = getMessageCache(activeDm.slug);
        if (!cache) return;

        const restoreScroll = options.restoreScroll === true;
        const keepPosition = options.keepPosition === true;
        const previousScrollTop = threadEl.scrollTop;
        const previousDistanceFromBottom = threadEl.scrollHeight - threadEl.scrollTop - threadEl.clientHeight;
        const shouldStickBottom = options.forceBottom === true || (!restoreScroll && !keepPosition && previousDistanceFromBottom < 80);

        threadEl.innerHTML = '';
        activeDm.lastId = cache.lastId || 0;

        if (!cache.loaded) {
            renderConversationLoading();
            return;
        }

        if (cache.messages.length === 0) {
            threadEl.innerHTML = `<div class="text-[#8f8675]">No messages yet.</div>`;
        } else {
            let lastCharacterId = 0;

            cache.messages.forEach((m) => {
                const bodyRaw = (m.content ?? m.body ?? '').toString();
                const isDeleted = !!m.deleted_at || bodyRaw === '[deleted]';
                const isEmote = (m.type || 'normal') === 'emote';
                const bodyDisplay = bodyRaw.trim();
                const who =
                    (m.character && m.character.name)
                        ? m.character.name
                        : (m.user && m.user.name ? m.user.name : 'Unknown');
                const avatar = m.character?.avatar || '';
                const characterId = parseInt(m.character?.id ?? m.character_id ?? 0, 10) || 0;
                const isGrouped = characterId > 0 && lastCharacterId === characterId;

                let settings = (m.character && m.character.settings) ? m.character.settings : {};
                if (typeof settings === 'string') {
                    try { settings = JSON.parse(settings); } catch (e) { settings = {}; }
                }

                const c1 = settings.text_color_1 || '#D8F3FF';
                const c2 = settings.text_color_2 || null;
                const c3 = settings.text_color_3 || null;
                const c4 = settings.text_color_4 || null;
                const fadeName = !!settings.fade_name;
                const fadeMsg = !!settings.fade_message;
                const nameStyleJson = JSON.stringify({ c1, c2, c3, c4, fade: fadeName });
                const bodyStyleJson = JSON.stringify({ c1, c2, c3, c4, fade: fadeMsg });
                const characterHandle = m.character?.public_handle || who;
                const avatarMarkup = (isGrouped || isEmote)
                    ? '<div class="w-7 shrink-0"></div>'
                    : `<div class="w-7 shrink-0">${characterTriggerHtml({ characterId, name: who, handle: characterHandle, avatar, contentHtml: avatarHtml(avatar, who, 'h-7 w-7'), extraClass: 'inline-flex rounded focus:outline-none focus:ring-2 focus:ring-amber-500/40' })}</div>`;
                const nameMarkup = (isGrouped || isEmote) ? '' : `
                            <div class="mb-0 flex items-baseline gap-2">
                                ${characterTriggerHtml({ characterId, name: who, handle: characterHandle, avatar, contentHtml: `<span class="msg-name text-base font-bold leading-none" data-style="${escapeHtml(nameStyleJson)}">${escapeHtml(who)}</span>`, extraClass: 'rounded hover:underline focus:outline-none focus:ring-2 focus:ring-amber-500/40 text-left' })}
                            </div>
                `;

                const bubble = document.createElement('div');
                bubble.className = `flex gap-2 px-2 ${isGrouped ? 'border-0 rounded-none bg-transparent py-0' : 'border-t border-[#16120c] py-0.5'}`;
                bubble.dataset.characterId = characterId ? String(characterId) : '';
                bubble.innerHTML = `
                    ${avatarMarkup}
                    <div class="min-w-0 flex-1">
                        ${nameMarkup}
                        <div class="msg-body-wrapper mt-0 text-sm leading-snug">
                            ${isEmote && !isDeleted ? `
                                <span class="inline-flex flex-wrap items-baseline gap-1 leading-snug">
                                    ${characterTriggerHtml({ characterId, name: who, handle: characterHandle, avatar, contentHtml: `<span class="msg-name text-sm font-bold leading-snug" data-style="${escapeHtml(nameStyleJson)}">${escapeHtml(who)}</span>`, extraClass: 'rounded hover:underline focus:outline-none focus:ring-2 focus:ring-amber-500/40 text-left' })}
                                    <span class="msg-body text-sm text-[#d6c8ad] leading-snug whitespace-pre-line" data-style="${escapeHtml(bodyStyleJson)}">${escapeHtml(bodyDisplay)}</span>
                                </span>
                            ` : `<span class="msg-body text-sm text-[#d6c8ad] leading-snug whitespace-pre-line" data-style="${escapeHtml(bodyStyleJson)}">${escapeHtml(isDeleted ? '[deleted]' : bodyDisplay)}</span>`}
                        </div>
                    </div>
                `;

                threadEl.appendChild(bubble);
                applyStylesIn(bubble);
                lastCharacterId = characterId;
            });
        }

        if (restoreScroll && cache.scrollTop !== null) {
            threadEl.scrollTop = Math.min(cache.scrollTop, Math.max(threadEl.scrollHeight - threadEl.clientHeight, 0));
            return;
        }

        if (shouldStickBottom) {
            threadEl.scrollTop = threadEl.scrollHeight;
            return;
        }

        if (keepPosition) {
            threadEl.scrollTop = Math.min(previousScrollTop, Math.max(threadEl.scrollHeight - threadEl.clientHeight, 0));
        }
    }

    function storeActiveConversationScroll() {
        if (!threadEl || !activeDm.slug) return;
        const cache = getMessageCache(activeDm.slug);
        if (!cache || !cache.loaded) return;
        cache.scrollTop = threadEl.scrollTop;
    }

    function applyActiveConversationMeta(room) {
        resetDmComposerState();
        setThreadFooterVisible(true);
        activeDm.slug = room?.slug || null;
        activeDm.conversationId = room?.roomId || 0;
        activeDm.lastId = getMessageCache(activeDm.slug)?.lastId || 0;
        activeDm.displayName = room?.displayName || room?.slug || null;
        activeDm.profileUrl = room?.profileUrl || '';
        activeDm.avatar = room?.avatar || '';
        activeDm.handle = room?.handle || room?.displayName || '';
        activeDm.otherUserId = room?.otherUserId || 0;
        activeDm.myCharacterId = room?.myCharacterId || 0;
        activeDm.otherCharacterId = room?.otherCharacterId || 0;
        activeDm.isBlockedByViewer = !!room?.isBlockedByViewer;

        if (threadHeader) {
            threadHeader.innerHTML = activeDm.displayName
                ? `DM: ${activeDm.otherCharacterId ? characterTriggerHtml({ characterId: activeDm.otherCharacterId, name: activeDm.displayName, handle: activeDm.handle || activeDm.displayName, avatar: activeDm.avatar, contentHtml: `<span class="font-semibold text-[#f2dfb5] hover:underline">${escapeHtml(activeDm.displayName)}</span>`, extraClass: 'rounded focus:outline-none focus:ring-2 focus:ring-amber-500/40 text-left' }) : escapeHtml(activeDm.displayName)}`
                : 'Select a conversation.';
        }

        syncDmBlockToggle();
        syncActiveDmArchiveControl();
        setThreadEnabled(!!activeDm.myCharacterId);
        syncActiveConversationHighlight();
    }

    function clearThread() {
        resetDmComposerState();
        storeActiveConversationScroll();
        stopDmRealtime();
        activeDm.slug = null;
        activeDm.conversationId = 0;
        activeDm.lastId = 0;
        activeDm.displayName = null;
        activeDm.profileUrl = '';
        activeDm.avatar = '';
        activeDm.handle = '';
        activeDm.otherUserId = 0;
        activeDm.myCharacterId = 0;
        activeDm.otherCharacterId = 0;
        activeDm.isBlockedByViewer = false;
        pollInFlight = false;

        if (threadHeader) threadHeader.textContent = 'Select a conversation.';
        syncDmBlockToggle();
        syncActiveDmArchiveControl();
        if (threadEl) threadEl.innerHTML = `<div class="text-[#8f8675]">No conversation selected.</div>`;
        if (inputEl) inputEl.value = '';
        setThreadFooterVisible(true);
        setThreadEnabled(false);
        syncActiveConversationHighlight();
    }

    function fetchConversationMessages(slug, options = {}) {
        if (!slug) return Promise.resolve([]);

        const { reset = false } = options;
        const cache = getMessageCache(slug);
        if (!cache || cache.loading) return Promise.resolve([]);

        cache.loading = true;
        const after = reset ? 0 : (cache.lastId || 0);

        return fetch(`/dms/${encodeURIComponent(slug)}/messages?after=${after}`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            const roomId = parseInt(data?.room?.id, 10) || 0;
            const msgs = data && Array.isArray(data.messages) ? data.messages : [];
            const room = dmRoomsBySlug.get(slug);

            if (room && roomId && room.roomId !== roomId) {
                room.roomId = roomId;
                refreshRoomButton(slug);
            }

            if (reset) {
                replaceCachedMessages(slug, msgs);
            } else {
                appendCachedMessages(slug, msgs);
            }

            cache.loading = false;

            if (activeDm.slug === slug) {
                if (roomId) activeDm.conversationId = roomId;
                clearDmUnread(activeDm.conversationId, slug);
                renderActiveConversation({
                    restoreScroll: reset,
                    keepPosition: !reset,
                    forceBottom: reset && cache.scrollTop === null,
                });
                startDmRealtime();
            }

            if (msgs.length > 0) {
                fetchDmRooms({ showLoading: false });
            }

            return msgs;
        })
        .catch(err => {
            cache.loading = false;
            console.error('DM thread load error:', err);
            if (activeDm.slug === slug && reset && threadEl) {
                threadEl.innerHTML = `<div class="text-red-400">Could not load messages.</div>`;
            }
            return [];
        });
    }

    function pollConversation() {
        if (!activeDm.slug || pollInFlight) return;

        pollInFlight = true;
        const cache = getMessageCache(activeDm.slug);

        fetchConversationMessages(activeDm.slug, { reset: !cache?.loaded })
            .finally(() => {
                pollInFlight = false;
            });
    }

    function startDmPolling() {
        stopDmPolling();
        pollDmTimer = setInterval(() => {
            if (!isOpen() || !activeDm.slug) return;
            pollConversation();
        }, 2500);
    }

    function stopDmPolling() {
        if (pollDmTimer) {
            clearInterval(pollDmTimer);
            pollDmTimer = null;
        }
        pollInFlight = false;
    }

    function bindDmReconnectHandlerOnce() {
        if (dmReconnectHandlerBound) return;
        dmReconnectHandlerBound = true;

        window.Echo?.connector?.pusher?.connection?.bind('connected', () => {
            if (isOpen() && activeDm.slug) pollConversation();
        });
    }

    function startDmRealtime() {
        if (!window.Echo || !activeDm.conversationId || !activeDm.myCharacterId) return;

        bindDmReconnectHandlerOnce();

        if (activeRealtimeConversationId === activeDm.conversationId) return;

        stopDmRealtime();

        activeRealtimeConversationId = activeDm.conversationId;

        const channelName = `private-conversation.${activeRealtimeConversationId}`;
        window.StoryboxChannelCharacters[channelName] = activeDm.myCharacterId;

        window.Echo.private(`conversation.${activeRealtimeConversationId}`)
            .listen('.message.created', (event) => {
                const eventId = parseInt(event.id, 10);
                if (!eventId) return;
                pollConversation();
            });
    }

    function syncDmListRealtimeSubscriptions(rooms) {
        if (!window.Echo || !Array.isArray(rooms)) return;

        rooms.forEach((room) => {
            const conversationId = parseInt(room.roomId || 0, 10) || 0;
            const characterId = parseInt(room.myCharacterId || 0, 10) || 0;

            if (!conversationId || !characterId || dmListRealtimeConversationIds.has(conversationId)) {
                return;
            }

            dmListRealtimeConversationIds.add(conversationId);

            const channelName = `private-conversation.${conversationId}`;
            window.StoryboxChannelCharacters[channelName] = characterId;

            window.Echo.private(`conversation.${conversationId}`)
                .listen('.message.created', (event) => {
                    const eventId = parseInt(event.id, 10);
                    if (!eventId) return;

                    if (isOpen() && activeDm.conversationId === conversationId) {
                        pollConversation();
                        fetchDmRooms({ showLoading: false });
                        return;
                    }

                    incrementDmUnread(conversationId);
                    fetchDmRooms({ showLoading: false });
                });
        });
    }

    function stopDmRealtime() {
        if (!window.Echo || !activeRealtimeConversationId) return;

        delete window.StoryboxChannelCharacters[`private-conversation.${activeRealtimeConversationId}`];
        window.Echo.leave(`conversation.${activeRealtimeConversationId}`);
        activeRealtimeConversationId = 0;
    }

    function startListRefresh() {
        stopListRefresh();
        refreshListTimer = setInterval(() => {
            if (!isOpen()) return;
            fetchDmRooms({ showLoading: false });
        }, 10000);
    }

    function stopListRefresh() {
        if (refreshListTimer) {
            clearInterval(refreshListTimer);
            refreshListTimer = null;
        }
    }

    function openConversation(slug) {
        const room = dmRoomsBySlug.get(slug);
        if (!room) return;

        resetDmComposerState();
        setThreadFooterVisible(true);
        setLastOpenedDmSlug(slug);

        if (activeDm.slug === slug) {
            const cache = getMessageCache(slug);

            if (cache?.loaded) {
                renderActiveConversation({
                    restoreScroll: true,
                    forceBottom: cache.scrollTop === null,
                });
                fetchConversationMessages(slug, { reset: false });
            } else {
                renderConversationLoading();
                fetchConversationMessages(slug, { reset: true });
            }

            startDmRealtime();
            startDmPolling();
            return;
        }

        storeActiveConversationScroll();
        stopDmRealtime();
        applyActiveConversationMeta(room);
        clearDmUnread(room.roomId, room.slug);

        const cache = getMessageCache(slug);
        if (cache?.loaded) {
            renderActiveConversation({
                restoreScroll: true,
                forceBottom: cache.scrollTop === null,
            });
            fetchConversationMessages(slug, { reset: false });
        } else {
            renderConversationLoading();
            fetchConversationMessages(slug, { reset: true });
        }

        startDmRealtime();
        startDmPolling();
    }

    blockToggleBtn?.addEventListener('click', () => {
        if (!activeDm.myCharacterId || !activeDm.otherCharacterId) return;
        window.setCharacterBlock(activeDm.myCharacterId, activeDm.otherCharacterId, !activeDm.isBlockedByViewer);
    });

    archiveActiveBtn?.addEventListener('click', () => {
        closeActiveDmConversation();
    });

    function sendDmMessage() {
        if (!activeDm.slug) return;

        const cid = activeDm.myCharacterId || 0;
        if (!cid) return;

        const text = (inputEl?.value ?? '').trim();
        if (!text) return;

        inputEl.value = '';

        fetch(`/dms/${encodeURIComponent(activeDm.slug)}/messages`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                body: text,
                character_id: cid,
            })
        })
        .then(r => r.json())
        .then(() => {
            fetchDmRooms({ showLoading: false });
            setTimeout(() => pollConversation(), 150);
        })
        .catch(err => {
            console.error('DM send error:', err);
        });
    }

    window.addEventListener('open-dm-window', (e) => {
        try {
            dmWindow.classList.remove('hidden');

            const slug = e?.detail?.slug;
            const name = e?.detail?.name;
            const myCharacterId = parseInt(e?.detail?.my_character_id || 0, 10) || 0;
            const otherCharacterId = parseInt(e?.detail?.other_character_id || 0, 10) || 0;
            const isBlockedByViewer = parseBool(e?.detail?.is_blocked_by_viewer);

            fetchDmRooms({ showLoading: true }).then((rooms) => {
                try {
                    const existingRoom = slug ? dmRoomsBySlug.get(slug) : null;

                    if (slug && !existingRoom && name) {
                        dmRoomsBySlug.set(slug, {
                            roomId: 0,
                            slug,
                            updatedAt: null,
                            archivedAt: null,
                            displayName: name,
                            avatar: '',
                            unreadCount: 0,
                            myCharacterId,
                            otherCharacterId,
                            isBlockedByViewer,
                        });
                    }

                    const defaultSlug = slug || resolveDefaultConversationSlug(rooms);

                    if (defaultSlug) {
                        openConversation(defaultSlug);
                    } else {
                        clearThread();
                    }
                } catch (error) {
                    console.error('DM open flow error:', error);
                    clearThread();
                }
            });

            startListRefresh();
        } catch (error) {
            console.error('DM window open error:', error);
            clearThread();
        }
    });

    refreshBtn?.addEventListener('click', () => {
        fetchDmRooms({ showLoading: false });
        if (activeDm.slug) pollConversation();
    });

    convoFilterInput?.addEventListener('input', () => {
        dmConversationFilter = convoFilterInput.value || '';
        renderRooms(Array.from(dmRoomsBySlug.values()));
    });

    newDmBtn?.addEventListener('click', () => {
        try {
            dmWindow.classList.remove('hidden');
            showNewDmComposer();
            fetchDmRooms({ showLoading: !roomsLoaded });
            startListRefresh();
        } catch (error) {
            console.error('New DM composer error:', error);
            clearThread();
        }
    });

    closeBtn?.addEventListener('click', () => {
        storeActiveConversationScroll();
        dmWindow.classList.add('hidden');
        stopDmRealtime();
    });

    sendBtn?.addEventListener('click', () => sendDmMessage());

    inputEl?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendDmMessage();
        }
    });

    threadEl?.addEventListener('scroll', () => {
        storeActiveConversationScroll();
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && isOpen() && activeDm.slug) pollConversation();
    });

    /*
    | DRAG
    */
    const dragHandle = document.getElementById('dm-drag-handle');
    let isDragging = false;
    let offsetX = 0;
    let offsetY = 0;

    dragHandle?.addEventListener('mousedown', (e) => {
        isDragging = true;
        offsetX = e.clientX - dmWindow.offsetLeft;
        offsetY = e.clientY - dmWindow.offsetTop;
        document.body.style.userSelect = 'none';
    });

    document.addEventListener('mouseup', () => {
        isDragging = false;
        document.body.style.userSelect = '';
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;

        dmWindow.style.left = (e.clientX - offsetX) + 'px';
        dmWindow.style.top = (e.clientY - offsetY) + 'px';
        dmWindow.style.right = 'auto';
    });

    /*
    | RESIZE
    */
    const resizeHandle = document.getElementById('dm-resize');
    let isResizing = false;

    resizeHandle?.addEventListener('mousedown', () => {
        isResizing = true;
        document.body.style.userSelect = 'none';
    });

    document.addEventListener('mouseup', () => {
        isResizing = false;
        document.body.style.userSelect = '';
    });

    document.addEventListener('mousemove', (e) => {
        if (!isResizing) return;

        dmWindow.style.width = (e.clientX - dmWindow.offsetLeft) + 'px';
        dmWindow.style.height = (e.clientY - dmWindow.offsetTop) + 'px';
    });

    /*
    | Stop timers when hidden
    */
    const observer = new MutationObserver(() => {
        if (!isOpen()) {
            stopListRefresh();
            stopDmPolling();
        }
    });
    observer.observe(dmWindow, { attributes: true, attributeFilter: ['class'] });

})();
</script>
