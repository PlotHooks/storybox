<div
    id="rp-ads-window"
    class="fixed right-6 top-20 z-[1000] hidden w-[min(1040px,calc(100vw-2rem))] max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-[#3a2d1b] bg-[#080809] shadow-[0_32px_80px_rgba(0,0,0,0.55)]"
    style="height:min(780px,calc(100vh-6rem));"
>
    <div id="rp-ads-drag-handle" class="flex cursor-move items-center justify-between border-b border-[#3a2d1b] bg-[#111114] px-3 py-2 shadow-[inset_0_-1px_0_rgba(245,158,11,0.04)]">
        <div>
            <div class="text-sm font-semibold text-[#f2dfb5]">RP Ads</div>
            <div class="text-[11px] text-[#8f8675]">Site-wide roleplay ads for rooms and DMs.</div>
        </div>
        <div class="flex items-center gap-2">
            <button id="rp-ads-refresh-btn" type="button" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-sm text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]" title="Refresh">↻</button>
            <button id="rp-ads-close-btn" type="button" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-sm text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]" title="Close">✕</button>
        </div>
    </div>

    <div class="grid h-[calc(100%-49px)] min-h-0 grid-cols-[280px_minmax(0,1fr)]">
        <div class="border-r border-[#332817] bg-[#0b0b0c] p-3">
            <div class="space-y-2">
                <div class="grid grid-cols-2 gap-1 text-xs font-semibold">
                    <button id="rp-ads-tab-room" type="button" class="rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-amber-200">Room Ads</button>
                    <button id="rp-ads-tab-dm" type="button" class="rounded border border-[#332817] px-2 py-1.5 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]">DM Ads</button>
                </div>
                <button id="rp-ads-my-ads-btn" type="button" class="w-full rounded border border-[#332817] bg-[#101012] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">My Ads</button>
                <button id="rp-ads-new-btn" type="button" class="w-full rounded border border-[#332817] bg-[#101012] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">+ Create Ad</button>
                <div id="rp-ads-status-pill" class="text-[10px] uppercase tracking-[0.18em] text-[#8f8675]">Loading</div>
            </div>

            <div class="mt-3">
                <label class="sr-only" for="rp-ads-search-input">Search RP ads</label>
                <input id="rp-ads-search-input" type="text" placeholder="Search title, body, or tags" class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-[11px] text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500">
            </div>

            <div class="mt-4 rounded border border-[#332817] bg-[#09090a] p-3 text-[11px] text-[#8f8675]">
                <div class="font-semibold uppercase tracking-[0.14em] text-[#d6c8ad]">Rules</div>
                <div class="mt-2">One active ad per character. Ads expire after 7 days. No replies, bumps, or discussion threads.</div>
            </div>
        </div>

        <div id="rp-ads-main-shell" class="grid min-w-0 grid-cols-[minmax(0,1fr)] overflow-hidden">
            <div class="flex min-w-0 flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-[#332817] bg-[#0c0c0e] px-4 py-3">
                    <div>
                        <div id="rp-ads-board-title" class="text-sm font-semibold text-[#f2dfb5]">Room Ads</div>
                        <div id="rp-ads-board-subtitle" class="mt-1 text-[11px] text-[#8f8675]">Browse active character ads.</div>
                    </div>
                    <div id="rp-ads-board-summary" class="rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-right text-[11px] text-[#8f8675]">Loading</div>
                </div>
                <div id="rp-ads-card-board" class="min-h-0 flex-1 overflow-y-auto p-4">
                    <div id="rp-ads-card-list" class="grid content-start justify-start gap-3" style="grid-template-columns: repeat(auto-fill, minmax(280px, 320px));"></div>
                </div>
            </div>

            <div id="rp-ads-form-panel" class="hidden min-w-0 flex-col overflow-hidden border-l border-[#332817] bg-[#080809]">
                <div class="flex items-center justify-between border-b border-[#332817] px-4 py-3">
                    <div>
                        <div id="rp-ads-form-title" class="truncate text-sm font-semibold text-[#f2dfb5]">Create RP Ad</div>
                        <div id="rp-ads-form-subtitle" class="mt-1 text-[11px] text-[#8f8675]">Create or edit one character ad at a time.</div>
                    </div>
                    <button id="rp-ads-form-close-btn" type="button" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-sm text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">✕</button>
                </div>
                <div id="rp-ads-form-body" class="min-h-0 flex-1 overflow-y-auto bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.03),transparent_24rem)] p-5 text-sm text-[#d6c8ad]"></div>
            </div>
        </div>
    </div>

    <div id="rp-ads-resize-handle" class="absolute bottom-0 right-0 h-4 w-4 cursor-se-resize" title="Resize"></div>
</div>

<script>
(() => {
    const windowEl = document.getElementById('rp-ads-window');
    if (!windowEl || windowEl.dataset.bound === '1') return;
    windowEl.dataset.bound = '1';

    const tabRoomBtn = document.getElementById('rp-ads-tab-room');
    const tabDmBtn = document.getElementById('rp-ads-tab-dm');
    const myAdsBtn = document.getElementById('rp-ads-my-ads-btn');
    const newBtn = document.getElementById('rp-ads-new-btn');
    const refreshBtn = document.getElementById('rp-ads-refresh-btn');
    const closeBtn = document.getElementById('rp-ads-close-btn');
    const formCloseBtn = document.getElementById('rp-ads-form-close-btn');
    const searchInput = document.getElementById('rp-ads-search-input');
    const statusPillEl = document.getElementById('rp-ads-status-pill');
    const boardTitleEl = document.getElementById('rp-ads-board-title');
    const boardSubtitleEl = document.getElementById('rp-ads-board-subtitle');
    const boardSummaryEl = document.getElementById('rp-ads-board-summary');
    const cardListEl = document.getElementById('rp-ads-card-list');
    const formPanelEl = document.getElementById('rp-ads-form-panel');
    const formBodyEl = document.getElementById('rp-ads-form-body');
    const formTitleEl = document.getElementById('rp-ads-form-title');
    const formSubtitleEl = document.getElementById('rp-ads-form-subtitle');
    const mainShellEl = document.getElementById('rp-ads-main-shell');
    const dragHandle = document.getElementById('rp-ads-drag-handle');
    const resizeHandle = document.getElementById('rp-ads-resize-handle');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const state = {
        mode: 'room',
        payload: null,
        loading: false,
        revealedBodies: new Set(),
        editingAd: null,
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function requestJson(url, options = {}) {
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        };

        if (!headers['Content-Type'] && options.body) {
            headers['Content-Type'] = 'application/json';
        }

        if (csrfToken && !headers['X-CSRF-TOKEN']) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        return fetch(url, { credentials: 'same-origin', ...options, headers }).then(async (response) => {
            const contentType = response.headers.get('content-type') || '';
            const payload = contentType.includes('application/json') ? await response.json() : null;

            if (!response.ok) {
                const message = payload?.message
                    || Object.values(payload?.errors || {}).flat()[0]
                    || `Request failed (${response.status})`;
                throw new Error(message);
            }

            return payload;
        });
    }

    function formatDate(value) {
        if (!value) return 'Unknown';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'Unknown';
        return date.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function updateStatus(label, isError = false) {
        statusPillEl.textContent = label;
        statusPillEl.className = `text-[10px] uppercase tracking-[0.18em] ${isError ? 'text-amber-200' : 'text-[#8f8675]'}`;
    }

    function cardsForMode() {
        if (!state.payload) return [];
        if (state.mode === 'room') return state.payload.room_ads || [];
        if (state.mode === 'dm') return state.payload.dm_ads || [];
        return state.payload.my_ads || [];
    }

    function activeCharacterId() {
        return state.payload?.viewer?.active_character_id || state.payload?.viewer?.default_dm_character_id || null;
    }

    function setMainShellFormOpen(isOpen) {
        if (isOpen) {
            formPanelEl.classList.remove('hidden');
            formPanelEl.classList.add('flex');
            mainShellEl.style.gridTemplateColumns = 'minmax(0, 1fr) minmax(360px, 420px)';
            return;
        }

        formPanelEl.classList.add('hidden');
        formPanelEl.classList.remove('flex');
        mainShellEl.style.gridTemplateColumns = 'minmax(0, 1fr)';
        formBodyEl.innerHTML = '';
        state.editingAd = null;
    }

    function renderBoardMeta() {
        const count = cardsForMode().filter(matchesSearch).length;

        if (state.mode === 'room') {
            boardTitleEl.textContent = 'Room Ads';
            boardSubtitleEl.textContent = 'Browse active room-seeking ads.';
        } else if (state.mode === 'dm') {
            boardTitleEl.textContent = 'DM Ads';
            boardSubtitleEl.textContent = 'Browse active direct-message ads.';
        } else {
            boardTitleEl.textContent = 'My Ads';
            boardSubtitleEl.textContent = 'Manage your character ads, including expired ones.';
        }

        boardSummaryEl.textContent = `${count} ${count === 1 ? 'ad' : 'ads'}`;
    }

    function matchesSearch(ad) {
        const needle = (searchInput.value || '').trim().toLowerCase();
        if (!needle) return true;
        return (ad.search_text || '').toLowerCase().includes(needle);
    }

    function avatarMarkup(ad) {
        const avatar = ad.character?.avatar;
        const label = escapeHtml(ad.character?.name || 'Character');

        if (avatar) {
            return `<img src="${escapeHtml(avatar)}" alt="${label}" class="h-11 w-11 rounded-lg border border-[#332817] object-cover">`;
        }

        return `<div class="flex h-11 w-11 items-center justify-center rounded-lg border border-[#332817] bg-[#0b0b0c] text-sm font-semibold text-[#8f8675]">${label.slice(0, 1) || '?'}</div>`;
    }

    function tagsMarkup(tags) {
        if (!Array.isArray(tags) || tags.length === 0) {
            return '<div class="text-[11px] text-[#6f675a]">No tags</div>';
        }

        return tags.map((tag) => `<span class="rounded-full border border-[#332817] bg-[#101012] px-2 py-0.5 text-[10px] text-[#d6c8ad]">${escapeHtml(tag)}</span>`).join('');
    }

    function bodyMarkup(ad) {
        const revealed = state.revealedBodies.has(ad.id);
        const body = escapeHtml(ad.body || '');

        if (!ad.body_obscured || revealed || state.mode === 'my') {
            return `<div class="whitespace-pre-wrap text-sm leading-relaxed text-[#d6c8ad]">${body}</div>`;
        }

        return `
            <button type="button" data-reveal-body="${ad.id}" class="block w-full rounded border border-[#4d1f1f] bg-[#1a0d0d] px-3 py-3 text-left focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                <div class="text-[10px] font-semibold uppercase tracking-[0.14em] text-amber-300">NSFW</div>
                <div class="mt-2 select-none blur-sm text-sm leading-relaxed text-[#d6c8ad]">${body}</div>
                <div class="mt-2 text-[11px] text-[#8f8675]">Click to reveal</div>
            </button>
        `;
    }

    function actionMarkup(ad) {
        if (state.mode === 'my') {
            return `
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-edit-ad="${ad.id}" class="rounded border border-[#332817] bg-[#101012] px-3 py-1.5 text-xs text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]">Edit</button>
                    <button type="button" data-refresh-ad="${ad.id}" class="rounded border border-[#332817] bg-[#101012] px-3 py-1.5 text-xs text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]">Refresh</button>
                    <button type="button" data-delete-ad="${ad.id}" class="rounded border border-[#4d1f1f] bg-[#180c0c] px-3 py-1.5 text-xs text-amber-200 hover:border-amber-500/40">Delete</button>
                </div>
            `;
        }

        if (ad.action?.kind === 'enter_room') {
            return `<a href="${escapeHtml(ad.action.url || '#')}" class="inline-flex items-center rounded border border-amber-500/40 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-500/20">Enter Room</a>`;
        }

        if (ad.action?.kind === 'start_dm') {
            const disabled = ad.action.disabled || !activeCharacterId() || !state.payload?.viewer?.has_characters;
            return `
                <button
                    type="button"
                    data-start-dm="${ad.id}"
                    ${disabled ? 'disabled' : ''}
                    class="inline-flex items-center rounded border border-amber-500/40 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-500/20 disabled:cursor-not-allowed disabled:border-[#332817] disabled:bg-[#101012] disabled:text-[#6f675a]"
                >Start DM</button>
            `;
        }

        return '';
    }

    function statusMarkup(ad) {
        if (state.mode !== 'my') {
            return `<div class="text-[11px] text-[#8f8675]">Refresh: ${escapeHtml(formatDate(ad.refreshed_at || ad.updated_at))}</div>`;
        }

        return `
            <div class="flex flex-wrap items-center gap-2 text-[11px]">
                <span class="rounded-full border ${ad.is_active ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200' : 'border-[#332817] bg-[#101012] text-[#8f8675]'} px-2 py-0.5">${ad.is_active ? 'Active' : 'Expired'}</span>
                <span class="text-[#8f8675]">Expires ${escapeHtml(formatDate(ad.expires_at))}</span>
            </div>
        `;
    }

    function cardMarkup(ad) {
        return `
            <article class="rounded-lg border border-[#332817] bg-[#0b0b0c] p-4 shadow-[0_12px_30px_rgba(0,0,0,0.22)]">
                <div class="flex items-start gap-3">
                    ${avatarMarkup(ad)}
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="truncate text-sm font-semibold text-[#f2dfb5]">${escapeHtml(ad.character?.name || 'Unknown')}</div>
                            <span class="rounded-full border border-[#332817] bg-[#101012] px-2 py-0.5 text-[10px] uppercase tracking-[0.12em] text-[#8f8675]">${escapeHtml(ad.type_label || 'Ad')}</span>
                            ${ad.is_nsfw ? '<span class="rounded-full border border-[#4d1f1f] bg-[#180c0c] px-2 py-0.5 text-[10px] uppercase tracking-[0.12em] text-amber-200">NSFW</span>' : ''}
                        </div>
                        <div class="mt-1 text-[11px] text-[#8f8675]">${escapeHtml(ad.character?.handle || '')}</div>
                        ${ad.room ? `<div class="mt-1 text-[11px] text-[#8f8675]">Room: <span class="text-[#d6c8ad]">${escapeHtml(ad.room.name)}</span></div>` : ''}
                    </div>
                </div>

                <div class="mt-4 text-base font-semibold text-[#f2dfb5]">${escapeHtml(ad.title || '')}</div>
                <div class="mt-3">${bodyMarkup(ad)}</div>
                <div class="mt-3 flex flex-wrap gap-1.5">${tagsMarkup(ad.tags || [])}</div>
                <div class="mt-4 flex items-center justify-between gap-3">
                    ${statusMarkup(ad)}
                    ${actionMarkup(ad)}
                </div>
            </article>
        `;
    }

    function renderCards() {
        if (!state.payload) {
            cardListEl.innerHTML = '<div class="text-[#8f8675]">Loading...</div>';
            return;
        }

        const cards = cardsForMode().filter(matchesSearch);
        renderBoardMeta();

        if (cards.length === 0) {
            cardListEl.innerHTML = '<div class="rounded border border-[#332817] bg-[#0b0b0c] px-4 py-4 text-sm text-[#8f8675]">No ads match this view.</div>';
            return;
        }

        cardListEl.innerHTML = cards.map(cardMarkup).join('');
    }

    function setMode(mode) {
        state.mode = mode;
        tabRoomBtn.className = mode === 'room'
            ? 'rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-amber-200'
            : 'rounded border border-[#332817] px-2 py-1.5 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]';
        tabDmBtn.className = mode === 'dm'
            ? 'rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1.5 text-amber-200'
            : 'rounded border border-[#332817] px-2 py-1.5 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]';
        myAdsBtn.className = mode === 'my'
            ? 'w-full rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-200'
            : 'w-full rounded border border-[#332817] bg-[#101012] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]';
        renderCards();
    }

    function roomsForCharacter(characterId) {
        const rooms = state.payload?.rooms_by_character?.[String(characterId)];
        return Array.isArray(rooms) ? rooms : [];
    }

    function renderForm(ad = null) {
        const characters = state.payload?.owned_characters || [];
        const selectedCharacterId = ad?.character?.id || activeCharacterId() || characters[0]?.id || '';
        const selectedType = ad?.type || 'room';
        const selectedRoomId = ad?.room?.id || '';
        const tags = Array.isArray(ad?.tags) ? ad.tags.join(', ') : '';

        formTitleEl.textContent = ad ? 'Edit RP Ad' : 'Create RP Ad';
        formSubtitleEl.textContent = ad ? 'Update your ad without changing its expiration.' : 'Create a new 7-day RP ad.';
        formBodyEl.innerHTML = `
            <form id="rp-ads-form" class="space-y-4 max-w-3xl">
                <div>
                    <label for="rp-ads-character" class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">Character</label>
                    <select id="rp-ads-character" name="character_id" class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                        ${characters.map((character) => `<option value="${character.id}" ${String(character.id) === String(selectedCharacterId) ? 'selected' : ''}>${escapeHtml(character.name)}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label for="rp-ads-type" class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">Type</label>
                    <select id="rp-ads-type" name="type" class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                        <option value="room" ${selectedType === 'room' ? 'selected' : ''}>Room</option>
                        <option value="dm" ${selectedType === 'dm' ? 'selected' : ''}>DM</option>
                    </select>
                </div>
                <div id="rp-ads-room-field">
                    <label for="rp-ads-room" class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">Linked Room</label>
                    <select id="rp-ads-room" name="room_id" class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500"></select>
                </div>
                <div>
                    <label for="rp-ads-title" class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">Title</label>
                    <input id="rp-ads-title" name="title" type="text" maxlength="255" value="${escapeHtml(ad?.title || '')}" class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                </div>
                <div>
                    <label for="rp-ads-body" class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">Body</label>
                    <textarea id="rp-ads-body" name="body" rows="8" class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">${escapeHtml(ad?.body || '')}</textarea>
                </div>
                <div>
                    <label for="rp-ads-tags" class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">Tags</label>
                    <input id="rp-ads-tags" name="tags" type="text" value="${escapeHtml(tags)}" placeholder="Horror, Fantasy, Romance" class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                </div>
                <label class="flex items-center gap-2 text-sm text-[#d6c8ad]">
                    <input id="rp-ads-is-nsfw" name="is_nsfw" type="checkbox" ${ad?.is_nsfw ? 'checked' : ''} class="rounded border-[#332817] bg-[#0b0b0c] text-amber-500 focus:ring-amber-500">
                    <span>Mark this ad as NSFW</span>
                </label>
                <div id="rp-ads-form-error" class="hidden rounded border border-[#4d1f1f] bg-[#180c0c] px-3 py-2 text-sm text-amber-200"></div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" id="rp-ads-form-cancel" class="rounded border border-[#332817] bg-[#0b0b0c] px-3 py-1.5 text-xs text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Cancel</button>
                    <button type="submit" class="rounded border border-amber-500/40 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-500/20">${ad ? 'Save Changes' : 'Create Ad'}</button>
                </div>
            </form>
        `;

        const characterSelect = document.getElementById('rp-ads-character');
        const typeSelect = document.getElementById('rp-ads-type');
        const roomField = document.getElementById('rp-ads-room-field');
        const roomSelect = document.getElementById('rp-ads-room');

        function syncRooms() {
            const rooms = roomsForCharacter(characterSelect.value);
            const currentValue = String(roomSelect.dataset.selectedRoomId || selectedRoomId || '');
            roomSelect.innerHTML = ['<option value="">Select a room</option>']
                .concat(rooms.map((room) => `<option value="${room.id}" ${String(room.id) === currentValue ? 'selected' : ''}>${escapeHtml(room.name)}</option>`))
                .join('');
        }

        function syncType() {
            const isRoom = typeSelect.value === 'room';
            roomField.classList.toggle('hidden', !isRoom);
        }

        roomSelect.dataset.selectedRoomId = String(selectedRoomId || '');
        syncRooms();
        syncType();

        characterSelect?.addEventListener('change', () => {
            roomSelect.dataset.selectedRoomId = '';
            syncRooms();
        });

        typeSelect?.addEventListener('change', syncType);

        document.getElementById('rp-ads-form-cancel')?.addEventListener('click', () => setMainShellFormOpen(false));
        document.getElementById('rp-ads-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorEl = document.getElementById('rp-ads-form-error');
            if (errorEl) {
                errorEl.classList.add('hidden');
                errorEl.textContent = '';
            }

            const payload = {
                character_id: Number(characterSelect.value),
                type: typeSelect.value,
                room_id: roomSelect.value ? Number(roomSelect.value) : null,
                title: document.getElementById('rp-ads-title')?.value || '',
                body: document.getElementById('rp-ads-body')?.value || '',
                tags: document.getElementById('rp-ads-tags')?.value || '',
                is_nsfw: document.getElementById('rp-ads-is-nsfw')?.checked ? 1 : 0,
            };

            try {
                const url = ad ? `/rp-ads/${encodeURIComponent(ad.id)}` : '/rp-ads';
                const method = ad ? 'PATCH' : 'POST';
                await requestJson(url, { method, body: JSON.stringify(payload) });
                setMainShellFormOpen(false);
                setMode('my');
                await fetchAds(false);
            } catch (error) {
                if (errorEl) {
                    errorEl.textContent = error?.message || 'Could not save the ad.';
                    errorEl.classList.remove('hidden');
                }
            }
        });
    }

    async function fetchAds(showLoading = true) {
        if (showLoading) updateStatus('Loading');
        state.loading = true;

        try {
            state.payload = await requestJson('/rp-ads');
            updateStatus('Ready');
            renderCards();
        } catch (error) {
            updateStatus(error?.message || 'Could not load ads.', true);
            cardListEl.innerHTML = `<div class="rounded border border-[#4d1f1f] bg-[#180c0c] px-4 py-4 text-sm text-amber-200">${escapeHtml(error?.message || 'Could not load ads.')}</div>`;
        } finally {
            state.loading = false;
        }
    }

    async function handleCardActions(event) {
        const revealButton = event.target.closest('[data-reveal-body]');
        if (revealButton) {
            state.revealedBodies.add(Number(revealButton.getAttribute('data-reveal-body')));
            renderCards();
            return;
        }

        const startDmButton = event.target.closest('[data-start-dm]');
        if (startDmButton) {
            const adId = Number(startDmButton.getAttribute('data-start-dm'));
            const ad = (state.payload?.dm_ads || []).find((item) => Number(item.id) === adId);
            if (!ad) return;

            try {
                updateStatus('Opening DM');
                const response = await requestJson('/dms/start', {
                    method: 'POST',
                    body: JSON.stringify({
                        my_character_id: activeCharacterId(),
                        other_character_id: ad.action?.other_character_id,
                    }),
                });
                updateStatus('Ready');
                window.dispatchEvent(new CustomEvent('open-dm-window', { detail: { slug: response.slug } }));
            } catch (error) {
                updateStatus(error?.message || 'Could not start DM.', true);
            }
            return;
        }

        const editButton = event.target.closest('[data-edit-ad]');
        if (editButton) {
            const adId = Number(editButton.getAttribute('data-edit-ad'));
            const ad = (state.payload?.my_ads || []).find((item) => Number(item.id) === adId);
            if (!ad) return;
            state.editingAd = ad;
            setMainShellFormOpen(true);
            renderForm(ad);
            return;
        }

        const refreshButton = event.target.closest('[data-refresh-ad]');
        if (refreshButton) {
            const adId = Number(refreshButton.getAttribute('data-refresh-ad'));

            try {
                await requestJson(`/rp-ads/${encodeURIComponent(adId)}/refresh`, { method: 'POST' });
                await fetchAds(false);
            } catch (error) {
                updateStatus(error?.message || 'Could not refresh the ad.', true);
            }
            return;
        }

        const deleteButton = event.target.closest('[data-delete-ad]');
        if (deleteButton) {
            const adId = Number(deleteButton.getAttribute('data-delete-ad'));
            if (!window.confirm('Delete this RP ad?')) return;

            try {
                await requestJson(`/rp-ads/${encodeURIComponent(adId)}`, { method: 'DELETE' });
                await fetchAds(false);
            } catch (error) {
                updateStatus(error?.message || 'Could not delete the ad.', true);
            }
        }
    }

    function openWindow() {
        windowEl.classList.remove('hidden');
        if (!state.payload) {
            fetchAds(true);
        } else {
            renderCards();
        }
    }

    function closeWindow() {
        windowEl.classList.add('hidden');
        setMainShellFormOpen(false);
    }

    function enableDragAndResize() {
        let dragState = null;
        let resizeState = null;

        dragHandle?.addEventListener('pointerdown', (event) => {
            if (event.target.closest('button')) return;
            dragState = {
                pointerX: event.clientX,
                pointerY: event.clientY,
                startLeft: windowEl.offsetLeft,
                startTop: windowEl.offsetTop,
            };
            windowEl.setPointerCapture?.(event.pointerId);
        });

        resizeHandle?.addEventListener('pointerdown', (event) => {
            resizeState = {
                pointerX: event.clientX,
                pointerY: event.clientY,
                startWidth: windowEl.offsetWidth,
                startHeight: windowEl.offsetHeight,
            };
            windowEl.setPointerCapture?.(event.pointerId);
            event.preventDefault();
        });

        window.addEventListener('pointermove', (event) => {
            if (dragState) {
                const left = Math.max(12, Math.min(window.innerWidth - windowEl.offsetWidth - 12, dragState.startLeft + (event.clientX - dragState.pointerX)));
                const top = Math.max(12, Math.min(window.innerHeight - windowEl.offsetHeight - 12, dragState.startTop + (event.clientY - dragState.pointerY)));
                windowEl.style.left = `${left}px`;
                windowEl.style.top = `${top}px`;
                windowEl.style.right = 'auto';
            }

            if (resizeState) {
                const width = Math.max(760, Math.min(window.innerWidth - 24, resizeState.startWidth + (event.clientX - resizeState.pointerX)));
                const height = Math.max(480, Math.min(window.innerHeight - 24, resizeState.startHeight + (event.clientY - resizeState.pointerY)));
                windowEl.style.width = `${width}px`;
                windowEl.style.height = `${height}px`;
            }
        });

        window.addEventListener('pointerup', () => {
            dragState = null;
            resizeState = null;
        });
    }

    tabRoomBtn?.addEventListener('click', () => setMode('room'));
    tabDmBtn?.addEventListener('click', () => setMode('dm'));
    myAdsBtn?.addEventListener('click', () => setMode('my'));
    newBtn?.addEventListener('click', () => {
        setMode('my');
        setMainShellFormOpen(true);
        renderForm();
    });
    refreshBtn?.addEventListener('click', () => fetchAds(true));
    closeBtn?.addEventListener('click', closeWindow);
    formCloseBtn?.addEventListener('click', () => setMainShellFormOpen(false));
    searchInput?.addEventListener('input', renderCards);
    cardListEl?.addEventListener('click', handleCardActions);
    window.addEventListener('open-rp-ads-window', openWindow);

    enableDragAndResize();
    setMode('room');
})();
</script>
