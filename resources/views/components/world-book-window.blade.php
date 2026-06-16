@props([
    'room',
])

<div
    id="world-book-window"
    class="hidden fixed z-50 bg-[#0b0b0c] border rounded-md shadow-[0_28px_72px_rgba(0,0,0,0.62)] flex flex-col overflow-hidden ring-1 ring-amber-500/10"
    style="width: min(1680px, calc(100vw - 48px)); height: min(760px, calc(100vh - 48px)); left: 24px; top: 24px; border-width: 4px; border-color: #3a2d1b;"
>
    <div id="world-book-drag-handle" class="cursor-move flex items-center justify-between px-3 py-2 border-b border-[#3a2d1b] bg-[#111114] shadow-[inset_0_-1px_0_rgba(245,158,11,0.04)]">
        <div>
            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-400">Room Canon</div>
            <div class="text-sm text-[#f2dfb5] font-semibold">World Book</div>
        </div>
        <div class="flex items-center gap-2">
            <button
                id="world-book-refresh-btn"
                type="button"
                class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm"
                title="Refresh"
            >
                ↻
            </button>
            <button
                id="world-book-close-btn"
                type="button"
                class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm"
                title="Close"
            >
                ✕
            </button>
        </div>
    </div>

    <div class="flex-1 min-h-0 grid overflow-hidden" style="grid-template-columns: 12rem 18rem minmax(0, 1fr);">
        <div class="min-w-0 border-r border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] flex flex-col overflow-hidden">
            <div class="p-3 border-b border-[#2a241a] space-y-2">
                <button
                    id="world-book-new-entry-btn"
                    type="button"
                    class="w-full rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                >
                    + Submit Entry
                </button>
                <div id="world-book-status-pill" class="text-[10px] uppercase tracking-[0.18em] text-[#8f8675]">
                    Loading
                </div>
            </div>
            <div class="border-b border-[#2a241a] p-3 space-y-2">
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Search</div>
                <label class="sr-only" for="world-book-search-input">Search world book</label>
                <input
                    id="world-book-search-input"
                    type="text"
                    placeholder="Search title, character, notes, tags"
                    class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-[11px] text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                >
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-2">
                <div class="mb-2 px-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Categories</div>
                <div id="world-book-category-list" class="space-y-1"></div>
            </div>
        </div>

        <div class="min-w-0 border-r border-[#332817] bg-[#0d0d0f] text-xs text-[#d6c8ad] flex flex-col overflow-hidden">
            <div class="px-3 py-3 border-b border-[#2a241a] bg-[#101012]">
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Entries</div>
                <div class="mt-1 text-[11px] text-[#8f8675]">Browse matching lore entries.</div>
            </div>
            <div id="world-book-entry-list" class="min-h-0 flex-1 overflow-y-auto p-2 space-y-2">
                <div class="text-[#8f8675]">Loading...</div>
            </div>
        </div>

        <div class="min-w-0 flex flex-col overflow-hidden bg-[#080809]">
            <div class="px-4 py-3 border-b border-[#332817] bg-[#101012] flex items-center justify-between gap-3 shadow-[inset_0_-1px_0_rgba(245,158,11,0.03)]">
                <div class="min-w-0">
                    <div id="world-book-title" class="truncate text-sm font-semibold text-[#f2dfb5]">World Book</div>
                    <div id="world-book-subtitle" class="mt-1 text-[11px] text-[#8f8675]">Published room reference and pending submissions.</div>
                </div>
                <div id="world-book-actions" class="shrink-0 flex items-center gap-2"></div>
            </div>
            <div id="world-book-body" class="flex-1 min-h-0 overflow-y-auto bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.03),transparent_24rem)] p-5 text-sm text-[#d6c8ad]">
                <div class="text-[#8f8675]">Loading...</div>
            </div>
        </div>
    </div>
    <div id="world-book-resize-handle" class="absolute bottom-0 right-0 h-4 w-4 cursor-se-resize" title="Resize"></div>
</div>

<div
    id="world-book-map-lightbox"
    class="hidden"
    role="dialog"
    aria-modal="true"
    aria-label="Expanded world map"
    style="position: fixed; inset: 0; z-index: 10010; background: rgba(0, 0, 0, 0.9); overflow: auto;"
>
    <button
        id="world-book-map-lightbox-close"
        type="button"
        class="rounded border border-[#5a431f] bg-[#0b0b0c] px-3 py-1.5 text-sm text-[#f2dfb5] hover:border-amber-500/40 hover:text-amber-200"
        aria-label="Close fullscreen map"
        style="position: fixed; top: 16px; right: 16px; z-index: 10011;"
    >
        Close
    </button>
    <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;">
        <div style="display: flex; align-items: center; justify-content: center; max-width: 95vw; max-height: 90vh; width: 100%;">
            <img
                id="world-book-map-lightbox-image"
                src=""
                alt=""
                class="rounded border border-[#5a431f] bg-[#080809] shadow-[0_20px_60px_rgba(0,0,0,0.5)]"
                referrerpolicy="no-referrer"
                style="display: block; max-width: 95vw; max-height: 90vh; width: auto; height: auto; object-fit: contain; margin: 0 auto;"
            >
        </div>
    </div>
</div>

<script>
(function () {
    const windowEl = document.getElementById('world-book-window');
    if (!windowEl) return;

    const roomSlug = @json($room->slug);
    const csrf = @json(csrf_token());
    const categoriesEl = document.getElementById('world-book-category-list');
    const entryListEl = document.getElementById('world-book-entry-list');
    const titleEl = document.getElementById('world-book-title');
    const subtitleEl = document.getElementById('world-book-subtitle');
    const bodyEl = document.getElementById('world-book-body');
    const actionsEl = document.getElementById('world-book-actions');
    const statusPillEl = document.getElementById('world-book-status-pill');
    const refreshBtn = document.getElementById('world-book-refresh-btn');
    const closeBtn = document.getElementById('world-book-close-btn');
    const newEntryBtn = document.getElementById('world-book-new-entry-btn');
    const searchInput = document.getElementById('world-book-search-input');
    const dragHandle = document.getElementById('world-book-drag-handle');
    const resizeHandle = document.getElementById('world-book-resize-handle');
    const mapLightboxEl = document.getElementById('world-book-map-lightbox');
    const mapLightboxImageEl = document.getElementById('world-book-map-lightbox-image');
    const mapLightboxCloseBtn = document.getElementById('world-book-map-lightbox-close');

    const WINDOW_MIN_WIDTH = 960;
    const WINDOW_MIN_HEIGHT = 560;
    const WINDOW_MAX_WIDTH = 1680;
    const WINDOW_MAX_HEIGHT = 900;
    const VIEWPORT_PADDING = 16;
    const SEARCH_DEBOUNCE_MS = 120;
    const PENDING_CATEGORY_KEY = '__pending__';

    const state = {
        categories: [],
        allEntriesCategory: { key: null, label: 'All Entries', icon: '📚' },
        pendingCategory: { key: PENDING_CATEGORY_KEY, label: 'Pending Submissions', icon: '⏳' },
        entries: [],
        pendingQueue: [],
        permissions: {
            can_submit: false,
            can_manage: false,
        },
        ownedCharacters: [],
        selectedCategory: null,
        selectedEntryId: null,
        searchTerm: '',
        mode: 'view',
        formMode: 'create',
    };

    let searchDebounceTimer = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '';
        try {
            return new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(new Date(value));
        } catch (error) {
            return value;
        }
    }

    function currentEntry() {
        return state.entries.find((entry) => entry.id === state.selectedEntryId) || null;
    }

    function normalizeSearchTerm(value) {
        return String(value || '').trim().toLowerCase();
    }

    function entryMatchesSearch(entry) {
        const term = normalizeSearchTerm(state.searchTerm);
        if (!term) return true;
        return String(entry.search_text || '').toLowerCase().includes(term);
    }

    function isPendingCategorySelected() {
        return state.selectedCategory === PENDING_CATEGORY_KEY;
    }

    function visibleEntries() {
        if (isPendingCategorySelected()) {
            return state.entries.filter((entry) => entry.has_pending_draft && entryMatchesSearch(entry));
        }

        return state.entries.filter((entry) => {
            const matchesCategory = !state.selectedCategory || entry.category === state.selectedCategory;
            return matchesCategory && entryMatchesSearch(entry);
        });
    }

    function visiblePendingQueue() {
        return state.pendingQueue.filter((entry) => entryMatchesSearch(entry));
    }

    function groupedVisibleEntries() {
        const items = visibleEntries();
        const grouped = new Map();

        items.forEach((entry) => {
            const key = entry.category || '__uncategorized__';
            if (!grouped.has(key)) {
                grouped.set(key, {
                    key,
                    label: entry.category_label || 'Uncategorized',
                    icon: entry.category_icon || '🏷',
                    entries: [],
                });
            }
            grouped.get(key).entries.push(entry);
        });

        const categoryOrder = new Map(state.categories.map((category, index) => [category.key, index]));

        return Array.from(grouped.values()).sort((left, right) => {
            const leftOrder = categoryOrder.has(left.key) ? categoryOrder.get(left.key) : Number.MAX_SAFE_INTEGER;
            const rightOrder = categoryOrder.has(right.key) ? categoryOrder.get(right.key) : Number.MAX_SAFE_INTEGER;

            if (leftOrder !== rightOrder) {
                return leftOrder - rightOrder;
            }

            return left.label.localeCompare(right.label);
        });
    }

    function sortablePublishedEntriesInCategory(categoryKey) {
        return state.entries.filter((entry) => entry.has_published_content && entry.category === categoryKey);
    }

    function moveStateForEntry(entry) {
        if (!entry?.viewer_can_manage || !entry.has_published_content || !entry.category || isCharacterCategory(entry.category)) {
            return { canMoveUp: false, canMoveDown: false };
        }

        const entries = sortablePublishedEntriesInCategory(entry.category);
        const index = entries.findIndex((item) => item.id === entry.id);

        return {
            canMoveUp: index > 0,
            canMoveDown: index !== -1 && index < entries.length - 1,
        };
    }

    function tagsHtml(tags) {
        if (!Array.isArray(tags) || tags.length === 0) {
            return '';
        }

        return `
            <div class="mt-3 flex flex-wrap gap-2">
                ${tags.map((tag) => `<span class="rounded-full border border-[#5a431f] bg-[#141416] px-2 py-1 text-[10px] uppercase tracking-[0.12em] text-amber-200">${escapeHtml(tag)}</span>`).join('')}
            </div>
        `;
    }

    function isCharacterCategory(category) {
        return category === 'character';
    }

    function isMapCategory(category) {
        return category === 'map';
    }

    function mapImageHtml(imageUrl, title, extraClasses = '', compact = false) {
        if (!imageUrl) {
            return '';
        }

        return `
            <button
                type="button"
                data-world-book-map-preview="${escapeHtml(imageUrl)}"
                data-world-book-map-title="${escapeHtml(title || 'Map preview')}"
                class="group block w-full overflow-hidden rounded border border-[#5a431f] bg-[#0d0d0f] p-3 text-left ${extraClasses}"
            >
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-300">Map Preview</div>
                <img
                    src="${escapeHtml(imageUrl)}"
                    alt="${escapeHtml(title || 'Map preview')}"
                    class="mt-3 mx-auto block ${compact ? 'max-h-[26rem]' : 'max-h-[38rem]'} h-auto w-auto max-w-full rounded object-contain"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                >
                <span class="mt-2 block text-[10px] uppercase tracking-[0.14em] text-[#8f8675] group-hover:text-[#d6c8ad]">Open fullscreen</span>
            </button>
        `;
    }

    function linkedCharacterCardHtml(character, compact = false) {
        if (!character) {
            return '';
        }

        const avatar = character.avatar_url
            ? `<img src="${escapeHtml(character.avatar_url)}" alt="${escapeHtml(character.name || 'Character')} avatar" class="${compact ? 'h-10 w-10 rounded-lg' : 'h-16 w-16 rounded-xl'} border border-[#5a431f] object-cover">`
            : `<div class="${compact ? 'h-10 w-10 rounded-lg text-base' : 'h-16 w-16 rounded-xl text-2xl'} flex items-center justify-center border border-[#5a431f] bg-[#120f0c] font-semibold text-[#f2dfb5]">${escapeHtml((character.name || '?').slice(0, 1).toUpperCase())}</div>`;

        return `
            <div class="rounded border border-[#332817] bg-[#101012] p-4">
                <div class="flex items-start gap-3">
                    ${avatar}
                    <div class="min-w-0 flex-1">
                        <a href="${escapeHtml(character.card_url || character.profile_url || '#')}" target="_blank" rel="noreferrer" class="block truncate text-sm font-semibold text-[#f2dfb5] hover:text-amber-200">${escapeHtml(character.name || 'Linked Character')}</a>
                        <div class="mt-1 text-[11px] text-[#8f8675]">${escapeHtml(character.handle || '')}</div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="${escapeHtml(character.card_url || character.profile_url || '#')}" target="_blank" rel="noreferrer" class="inline-flex items-center rounded border border-[#5a431f] bg-[#171311] px-3 py-1.5 text-[11px] text-[#f2dfb5] hover:bg-[#1d1713]">Open Card</a>
                            <a href="${escapeHtml(character.profile_url || character.card_url || '#')}" target="_blank" rel="noreferrer" class="inline-flex items-center rounded border border-[#332817] bg-[#0b0b0c] px-3 py-1.5 text-[11px] text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]">View Profile</a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function updateStatusPill() {
        const entry = currentEntry();

        if (!entry) {
            statusPillEl.textContent = state.permissions.can_manage ? 'Manager View' : 'Published Canon';
            return;
        }

        if (entry.has_published_content && entry.has_pending_draft) {
            statusPillEl.textContent = 'Published + Pending Review';
            return;
        }

        statusPillEl.textContent = entry.status.replace('_', ' ');
    }

    function selectDefaultEntry() {
        if (currentEntry() && visibleEntries().some((entry) => entry.id === state.selectedEntryId)) {
            return;
        }

        const firstVisible = visibleEntries()[0];
        state.selectedEntryId = firstVisible ? firstVisible.id : null;
    }

    function renderCategories() {
        const items = [state.allEntriesCategory, ...state.categories];

        if (state.permissions.can_manage) {
            items.push(state.pendingCategory);
        }

        categoriesEl.innerHTML = items.map((category) => {
            const active = category.key === state.selectedCategory;
            const count = category.key === PENDING_CATEGORY_KEY
                ? state.entries.filter((entry) => entry.has_pending_draft).length
                : state.entries.filter((entry) => category.key === null || entry.category === category.key).length;

            return `
                <button
                    type="button"
                    data-world-book-category="${category.key ?? ''}"
                    class="${active
                        ? 'w-full rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-left text-[11px] font-semibold text-amber-100'
                        : 'w-full rounded border border-[#332817] bg-[#101012] px-3 py-2 text-left text-[11px] text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]'}"
                >
                    <span class="flex items-center gap-2"><span>${escapeHtml(category.icon || '')}</span><span>${escapeHtml(category.label)}</span></span>
                    <span class="mt-0.5 block text-[10px] text-[#8f8675]">${count} entr${count === 1 ? 'y' : 'ies'}</span>
                </button>
            `;
        }).join('');

        categoriesEl.querySelectorAll('[data-world-book-category]').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedCategory = button.dataset.worldBookCategory || null;
                if (state.selectedCategory === '') {
                    state.selectedCategory = null;
                }
                state.selectedEntryId = null;
                state.mode = 'view';
                selectDefaultEntry();
                render();
            });
        });
    }

    function renderEntryList() {
        const items = visibleEntries();

        if (items.length === 0) {
            entryListEl.innerHTML = '<div class="rounded border border-dashed border-[#332817] bg-[#101012] px-3 py-3 text-[11px] text-[#8f8675]">No entries match the current filters.</div>';
            return;
        }

        function entryRowHtml(entry) {
            const active = entry.id === state.selectedEntryId && state.mode === 'view';
            const status = entry.has_published_content && entry.has_pending_draft
                ? 'Published + Pending'
                : entry.status.replace('_', ' ');
            const moveState = moveStateForEntry(entry);

            return `
                <div class="${active
                    ? 'rounded border border-amber-500/40 bg-amber-500/10 p-3 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.12)]'
                    : 'rounded border border-[#332817] bg-[#101012] p-3'}">
                    <div class="flex items-start gap-2">
                        <button
                            type="button"
                            data-world-book-entry="${entry.id}"
                            class="min-w-0 flex-1 text-left ${active ? '' : 'hover:text-[#f2dfb5]'}"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 text-[11px] font-semibold text-[#f2dfb5]">${entry.linked_character?.avatar_url && isCharacterCategory(entry.category) ? `<img src="${escapeHtml(entry.linked_character.avatar_url)}" alt="" class="h-6 w-6 rounded-md border border-[#5a431f] object-cover">` : `<span>${escapeHtml(entry.category_icon || '')}</span>`}<span class="truncate">${escapeHtml(entry.title || 'Untitled')}</span></div>
                                    <div class="mt-1 text-[10px] uppercase tracking-[0.14em] text-[#8f8675]">${escapeHtml(entry.category_label)}</div>
                                    ${Array.isArray(entry.tags) && entry.tags.length > 0 ? `<div class="mt-2 flex flex-wrap gap-1">${entry.tags.slice(0, 3).map((tag) => `<span class="rounded-full border border-[#332817] bg-[#0b0b0c] px-2 py-0.5 text-[9px] uppercase tracking-[0.12em] text-[#8f8675]">${escapeHtml(tag)}</span>`).join('')}</div>` : ''}
                                </div>
                                <span class="shrink-0 rounded border border-[#332817] bg-[#0b0b0c] px-2 py-0.5 text-[9px] uppercase tracking-[0.14em] text-[#8f8675]">${escapeHtml(status)}</span>
                            </div>
                        </button>
                        ${entry.viewer_can_manage && entry.has_published_content && !isCharacterCategory(entry.category) ? `
                            <div class="shrink-0 flex flex-col gap-1">
                                <button type="button" data-world-book-move="up" data-world-book-move-entry="${entry.id}" ${moveState.canMoveUp ? '' : 'disabled'} class="rounded border px-1.5 py-0.5 text-[10px] ${moveState.canMoveUp ? 'border-[#332817] bg-[#0b0b0c] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]' : 'border-[#2a241a] bg-[#0b0b0c] text-[#5d5549] cursor-not-allowed opacity-60'}">↑</button>
                                <button type="button" data-world-book-move="down" data-world-book-move-entry="${entry.id}" ${moveState.canMoveDown ? '' : 'disabled'} class="rounded border px-1.5 py-0.5 text-[10px] ${moveState.canMoveDown ? 'border-[#332817] bg-[#0b0b0c] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]' : 'border-[#2a241a] bg-[#0b0b0c] text-[#5d5549] cursor-not-allowed opacity-60'}">↓</button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        if (!state.selectedCategory && !isPendingCategorySelected()) {
            entryListEl.innerHTML = groupedVisibleEntries().map((section) => `
                <section class="space-y-2">
                    <div class="px-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-300">${escapeHtml(section.icon || '')} ${escapeHtml(section.label)}</div>
                    <div class="space-y-2">${section.entries.map((entry) => entryRowHtml(entry)).join('')}</div>
                </section>
            `).join('');
        } else {
            entryListEl.innerHTML = items.map((entry) => entryRowHtml(entry)).join('');
        }

        entryListEl.querySelectorAll('[data-world-book-entry]').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedEntryId = parseInt(button.dataset.worldBookEntry || '0', 10) || null;
                state.mode = 'view';
                render();
            });
        });

        entryListEl.querySelectorAll('[data-world-book-move-entry]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.stopPropagation();
                const entryId = parseInt(button.dataset.worldBookMoveEntry || '0', 10) || null;
                const direction = button.dataset.worldBookMove || '';
                if (!entryId || !direction || button.hasAttribute('disabled')) {
                    return;
                }

                await submitJson(`/rooms/${encodeURIComponent(roomSlug)}/world-book/${entryId}/move`, 'POST', { direction });
                await loadWorldBook(true);
            });
        });
    }

    function renderActions(entry) {
        const buttons = [];

        if (state.mode === 'form') {
            buttons.push('<button type="button" id="world-book-cancel-form" class="rounded border border-[#332817] bg-[#0b0b0c] px-3 py-1.5 text-xs text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Cancel</button>');
            buttons.push('<button type="submit" form="world-book-form" id="world-book-save-form" class="rounded border border-amber-500/50 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-500/20">Save</button>');
            actionsEl.innerHTML = buttons.join('');
            document.getElementById('world-book-cancel-form')?.addEventListener('click', () => {
                state.mode = 'view';
                render();
            });
            return;
        }

        if (state.mode === 'reject' && entry) {
            buttons.push('<button type="button" id="world-book-cancel-reject" class="rounded border border-[#332817] bg-[#0b0b0c] px-3 py-1.5 text-xs text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Cancel</button>');
            actionsEl.innerHTML = buttons.join('');
            document.getElementById('world-book-cancel-reject')?.addEventListener('click', () => {
                state.mode = 'view';
                render();
            });
            return;
        }

        if (!entry) {
            actionsEl.innerHTML = '';
            return;
        }

        if (entry.viewer_can_edit) {
            buttons.push('<button type="button" id="world-book-edit-btn" class="rounded border border-[#332817] bg-[#0b0b0c] px-3 py-1.5 text-xs text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Edit</button>');
        }

        if (entry.viewer_can_manage && entry.has_pending_draft) {
            buttons.push('<button type="button" id="world-book-approve-btn" class="rounded border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-xs text-emerald-200 hover:bg-emerald-500/20">Approve</button>');
            buttons.push('<button type="button" id="world-book-reject-btn" class="rounded border border-red-500/40 bg-red-500/10 px-3 py-1.5 text-xs text-red-200 hover:bg-red-500/20">Reject</button>');
        }

        if (entry.viewer_can_manage) {
            buttons.push('<button type="button" id="world-book-delete-btn" class="rounded border border-red-500/40 bg-red-500/10 px-3 py-1.5 text-xs text-red-200 hover:bg-red-500/20">Delete</button>');
        }

        actionsEl.innerHTML = buttons.join('');

        document.getElementById('world-book-edit-btn')?.addEventListener('click', () => {
            state.formMode = 'edit';
            state.mode = 'form';
            render();
        });
        document.getElementById('world-book-approve-btn')?.addEventListener('click', () => approveEntry(entry.id));
        document.getElementById('world-book-reject-btn')?.addEventListener('click', () => {
            state.mode = 'reject';
            render();
        });
        document.getElementById('world-book-delete-btn')?.addEventListener('click', () => deleteEntry(entry.id));
    }

    function renderRejectForm(entry) {
        titleEl.textContent = `Reject ${entry.pending?.title || entry.title || 'Submission'}`;
        subtitleEl.textContent = 'Optional rejection notes are visible only to the submitter and room staff.';

        bodyEl.innerHTML = `
            <form id="world-book-reject-form" class="space-y-4">
                <div class="rounded border border-[#332817] bg-[#101012] p-4">
                    <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-300">Reviewing</div>
                    <div class="mt-3 text-sm font-semibold text-[#f2dfb5]">${escapeHtml(entry.pending?.title || entry.title || 'Untitled')}</div>
                    <div class="mt-1 text-[11px] uppercase tracking-[0.14em] text-[#8f8675]">${escapeHtml(entry.pending?.category_label || entry.category_label)}</div>
                    ${tagsHtml(entry.pending?.tags || entry.tags || [])}
                    <div class="mt-3 whitespace-pre-wrap leading-relaxed text-[#d6c8ad]">${escapeHtml(entry.pending?.body || entry.body || '')}</div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="world-book-rejection-note">Reason</label>
                    <textarea id="world-book-rejection-note" name="rejection_note" rows="5" class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500" placeholder="Conflicts with established canon.">${escapeHtml(entry.rejection_note || '')}</textarea>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button type="submit" class="inline-flex items-center rounded border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm font-semibold text-red-200 hover:bg-red-500/20">Reject Submission</button>
                </div>
                <div id="world-book-reject-error" class="hidden rounded border border-red-500/40 bg-red-500/10 px-3 py-2 text-[11px] text-red-200"></div>
            </form>
        `;

        renderActions(entry);

        document.getElementById('world-book-reject-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const errorEl = document.getElementById('world-book-reject-error');
            const payload = {
                rejection_note: form.rejection_note.value.trim(),
            };

            try {
                errorEl?.classList.add('hidden');
                await submitJson(`/rooms/${encodeURIComponent(roomSlug)}/world-book/${entry.id}/reject`, 'POST', payload);
                state.mode = 'view';
                await loadWorldBook(true);
            } catch (error) {
                errorEl.textContent = error.message || 'Could not reject entry.';
                errorEl.classList.remove('hidden');
            }
        });
    }

    function renderEntryView(entry) {
        if (!entry) {
            titleEl.textContent = 'World Book';
            subtitleEl.textContent = 'Select an entry or submit new room lore.';
            bodyEl.innerHTML = '<div class="rounded border border-dashed border-[#332817] bg-[#101012]/60 p-4 text-[#8f8675]">No entry selected.</div>';
            renderActions(null);
            return;
        }

        titleEl.textContent = entry.title || 'Untitled';
        subtitleEl.textContent = `${entry.category_icon || ''} ${entry.category_label} • ${entry.author_character?.name || 'Unknown author'} • Created ${formatDate(entry.created_at)}`;

        const publishedHtml = entry.published ? `
            <section class="space-y-4">
                ${isCharacterCategory(entry.published.category)
                    ? linkedCharacterCardHtml(entry.published.linked_character)
                    : (isMapCategory(entry.published.category)
                        ? mapImageHtml(entry.published.image_url, entry.published.title || entry.title || 'Map')
                        : (entry.published.image_url ? `<div class="rounded border border-[#332817] bg-[#0d0d0f] p-3"><img src="${escapeHtml(entry.published.image_url)}" alt="${escapeHtml(entry.published.title || entry.title || 'World Book image')}" class="mx-auto block max-h-[32rem] h-auto w-auto max-w-full rounded object-contain" loading="lazy" referrerpolicy="no-referrer"></div>` : ''))}
                <div class="rounded border border-[#332817] bg-[#101012] p-4">
                    <div class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-400"><span>${escapeHtml(entry.published.category_icon || '')}</span><span>${isMapCategory(entry.published.category) ? 'Published Map' : 'Published Canon'}</span></div>
                    ${tagsHtml(entry.published.tags || [])}
                    ${entry.published.body ? `<div class="mt-3 whitespace-pre-wrap leading-relaxed text-[#d6c8ad]">${escapeHtml(entry.published.body)}</div>` : `<div class="mt-3 text-sm text-[#8f8675]">${isMapCategory(entry.published.category) ? 'No map description yet.' : 'No supplemental room notes yet.'}</div>`}
                </div>
            </section>
        ` : '';

        const pendingHtml = entry.pending ? `
            <section class="mt-4 rounded border ${entry.has_published_content ? 'border-amber-500/30 bg-amber-500/10' : 'border-[#332817] bg-[#101012]'} p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.16em] ${entry.has_published_content ? 'text-amber-200' : 'text-[#8f8675]'}"><span>${escapeHtml(entry.pending.category_icon || '')}</span><span>Pending Draft</span></div>
                    <div class="text-[10px] text-[#8f8675]">${entry.status === 'rejected' ? 'Rejected' : 'Awaiting review'}</div>
                </div>
                <div class="mt-3 text-sm font-semibold text-[#f2dfb5]">${escapeHtml(entry.pending.title)}</div>
                <div class="mt-1 text-[11px] uppercase tracking-[0.14em] text-[#8f8675]">${escapeHtml(entry.pending.category_label)}</div>
                ${isCharacterCategory(entry.pending.category)
                    ? `<div class="mt-3">${linkedCharacterCardHtml(entry.pending.linked_character, true)}</div>`
                    : (isMapCategory(entry.pending.category)
                        ? mapImageHtml(entry.pending.image_url, entry.pending.title || entry.title || 'Pending map', 'mt-3', true)
                        : (entry.pending.image_url ? `<div class="mt-3 rounded border border-[#332817] bg-[#0d0d0f] p-3"><img src="${escapeHtml(entry.pending.image_url)}" alt="${escapeHtml(entry.pending.title || entry.title || 'World Book draft image')}" class="mx-auto block max-h-[32rem] h-auto w-auto max-w-full rounded object-contain" loading="lazy" referrerpolicy="no-referrer"></div>` : ''))}
                ${tagsHtml(entry.pending.tags || [])}
                ${entry.pending.body ? `<div class="mt-3 whitespace-pre-wrap leading-relaxed text-[#d6c8ad]">${escapeHtml(entry.pending.body)}</div>` : `<div class="mt-3 text-sm text-[#8f8675]">${isMapCategory(entry.pending.category) ? 'No map description yet.' : 'No supplemental room notes yet.'}</div>`}
            </section>
        ` : '';

        const rejectionHtml = entry.rejection_note ? `
            <section class="rounded border border-red-500/30 bg-red-500/10 p-4">
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-red-200">Rejection Note</div>
                <div class="mt-3 whitespace-pre-wrap leading-relaxed text-red-100">${escapeHtml(entry.rejection_note)}</div>
                ${entry.rejected_at ? `<div class="mt-2 text-[10px] text-red-200/80">Reviewed ${escapeHtml(formatDate(entry.rejected_at))}</div>` : ''}
            </section>
        ` : '';

        bodyEl.innerHTML = `
            <div class="space-y-4">
                ${publishedHtml || ''}
                ${!entry.published && entry.pending ? `
                    <section class="rounded border border-[#332817] bg-[#101012] p-4">
                        <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Submission</div>
                        ${isCharacterCategory(entry.pending.category) ? `<div class="mt-3">${linkedCharacterCardHtml(entry.pending.linked_character)}</div>` : ''}
                        ${!isCharacterCategory(entry.pending.category) && isMapCategory(entry.pending.category) ? mapImageHtml(entry.pending.image_url, entry.pending.title || entry.title || 'Pending map', 'mt-3', true) : ''}
                        ${tagsHtml(entry.pending.tags || [])}
                        ${entry.pending.body ? `<div class="mt-3 whitespace-pre-wrap leading-relaxed text-[#d6c8ad]">${escapeHtml(entry.pending.body)}</div>` : `<div class="mt-3 text-sm text-[#8f8675]">${isMapCategory(entry.pending.category) ? 'No map description yet.' : 'No supplemental room notes yet.'}</div>`}
                    </section>
                ` : ''}
                ${pendingHtml}
                ${rejectionHtml}
            </div>
        `;

        bindMapPreviewButtons();
        renderActions(entry);
    }

    function bindMapPreviewButtons() {
        bodyEl.querySelectorAll('[data-world-book-map-preview]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!mapLightboxEl || !mapLightboxImageEl) {
                    return;
                }

                mapLightboxImageEl.src = button.dataset.worldBookMapPreview || '';
                mapLightboxImageEl.alt = button.dataset.worldBookMapTitle || 'Map preview';
                mapLightboxEl.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            });
        });
    }

    function closeMapLightbox() {
        if (!mapLightboxEl || !mapLightboxImageEl) {
            return;
        }

        mapLightboxEl.classList.add('hidden');
        mapLightboxImageEl.src = '';
        mapLightboxImageEl.alt = '';
        document.body.classList.remove('overflow-hidden');
    }

    function renderForm(entry) {
        const source = state.formMode === 'edit' && entry ? (entry.pending || entry.published || entry) : null;
        const publishAllowed = state.permissions.can_manage;

        titleEl.textContent = state.formMode === 'edit' ? 'Edit Entry' : 'Submit Entry';
        subtitleEl.textContent = state.formMode === 'edit'
            ? 'Draft changes stay pending until approved unless you publish directly as room staff.'
            : 'Submissions become pending until a room owner or moderator approves them.';

        bodyEl.innerHTML = `
            <form id="world-book-form" class="space-y-4">
                <div id="world-book-form-title-wrap">
                    <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="world-book-form-title">Title</label>
                    <input id="world-book-form-title" name="title" type="text" maxlength="255" value="${escapeHtml(source?.title || '')}" class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="world-book-form-category">Category</label>
                    <select id="world-book-form-category" name="category" class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                        ${state.categories.map((category) => `<option value="${escapeHtml(category.key)}" ${source?.category === category.key ? 'selected' : ''}>${escapeHtml(category.icon || '')} ${escapeHtml(category.label)}</option>`).join('')}
                    </select>
                </div>
                <div id="world-book-form-linked-character-wrap" class="hidden">
                    <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="world-book-form-linked-character-id">Linked Character</label>
                    <select id="world-book-form-linked-character-id" name="linked_character_id" class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                        <option value="">Select one of your characters</option>
                        ${state.ownedCharacters.map((character) => `<option value="${escapeHtml(character.id)}" ${String(source?.linked_character?.id || '') === String(character.id) ? 'selected' : ''}>${escapeHtml(character.name)} (${escapeHtml(character.handle)})</option>`).join('')}
                    </select>
                    <div class="mt-1 text-[11px] text-[#8f8675]">Only characters you own can be linked here.</div>
                    ${source?.linked_character ? `<div class="mt-3">${linkedCharacterCardHtml(source.linked_character, true)}</div>` : ''}
                </div>
                <div id="world-book-form-image-url-wrap">
                    <label id="world-book-form-image-url-label" class="block text-[11px] font-semibold text-[#d6c8ad]" for="world-book-form-image-url">Image URL</label>
                    <input id="world-book-form-image-url" name="image_url" type="url" maxlength="2048" value="${escapeHtml(source?.image_url || '')}" class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="world-book-form-tags">Tags</label>
                    <input id="world-book-form-tags" name="tags_input" type="text" maxlength="1000" value="${escapeHtml(Array.isArray(source?.tags) ? source.tags.join(', ') : '')}" placeholder="coastal, trade, moon, pirates" class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                </div>
                <div>
                    <label id="world-book-form-body-label" class="block text-[11px] font-semibold text-[#d6c8ad]" for="world-book-form-body">Body</label>
                    <textarea id="world-book-form-body" name="body" rows="14" class="mt-1 block w-full rounded-md border-[#332817] bg-[#0b0b0c] text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">${escapeHtml(source?.body || '')}</textarea>
                    <div id="world-book-form-body-help" class="mt-1 text-[11px] text-[#8f8675]"></div>
                </div>
                <div class="flex items-center justify-between gap-3">
                    ${publishAllowed ? `
                        <label class="flex items-center gap-2 text-[11px] text-[#d6c8ad]">
                            <input id="world-book-form-publish" name="publish" type="checkbox" value="1" class="rounded border-[#332817] bg-[#0b0b0c] text-amber-500 focus:ring-amber-500">
                            <span>Publish immediately</span>
                        </label>
                    ` : '<div></div>'}
                </div>
                <div id="world-book-form-error" class="hidden rounded border border-red-500/40 bg-red-500/10 px-3 py-2 text-[11px] text-red-200"></div>
            </form>
        `;

        renderActions(entry);

        const form = document.getElementById('world-book-form');
        const categoryInput = document.getElementById('world-book-form-category');
        const titleWrap = document.getElementById('world-book-form-title-wrap');
        const imageWrap = document.getElementById('world-book-form-image-url-wrap');
        const imageLabel = document.getElementById('world-book-form-image-url-label');
        const linkedCharacterWrap = document.getElementById('world-book-form-linked-character-wrap');
        const bodyLabel = document.getElementById('world-book-form-body-label');
        const bodyHelp = document.getElementById('world-book-form-body-help');
        const bodyInput = document.getElementById('world-book-form-body');

        function syncWorldBookFormCategory() {
            const characterCategory = isCharacterCategory(categoryInput?.value);
            const mapCategory = isMapCategory(categoryInput?.value);
            titleWrap?.classList.toggle('hidden', characterCategory);
            imageWrap?.classList.toggle('hidden', characterCategory);
            linkedCharacterWrap?.classList.toggle('hidden', !characterCategory);

            if (imageLabel) {
                imageLabel.textContent = mapCategory ? 'Map Image URL' : 'Image URL';
            }

            if (bodyLabel) {
                bodyLabel.textContent = characterCategory ? 'Supplemental Notes' : (mapCategory ? 'Description' : 'Body');
            }

            if (bodyHelp) {
                bodyHelp.textContent = characterCategory
                    ? 'Optional room-only notes such as affiliations, current status, relationships, or plot relevance.'
                    : (mapCategory ? 'Add context, landmarks, travel notes, or usage guidance for this map.' : '');
            }

            if (bodyInput) {
                bodyInput.placeholder = characterCategory
                    ? 'Known Affiliations, Current Status, Relationship Notes, Plot Relevance...'
                    : (mapCategory ? 'Region overview, key landmarks, route notes, and other map context...' : '');
            }
        }

        categoryInput?.addEventListener('change', syncWorldBookFormCategory);
        syncWorldBookFormCategory();

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const errorEl = document.getElementById('world-book-form-error');
            const payload = {
                title: form.title ? form.title.value.trim() : '',
                category: form.category.value,
                linked_character_id: form.linked_character_id ? form.linked_character_id.value : '',
                image_url: form.image_url ? form.image_url.value.trim() : '',
                tags_input: form.tags_input.value.trim(),
                body: form.body.value.trim(),
                publish: form.publish ? form.publish.checked : false,
            };

            try {
                errorEl?.classList.add('hidden');

                if (state.formMode === 'edit' && entry) {
                    await submitJson(`/rooms/${encodeURIComponent(roomSlug)}/world-book/${entry.id}`, 'PATCH', payload);
                } else {
                    await submitJson(`/rooms/${encodeURIComponent(roomSlug)}/world-book`, 'POST', payload);
                }

                state.mode = 'view';
                await loadWorldBook(true);
            } catch (error) {
                errorEl.textContent = error.message || 'Could not save entry.';
                errorEl.classList.remove('hidden');
            }
        });
    }

    function render() {
        selectDefaultEntry();
        renderCategories();
        renderEntryList();
        updateStatusPill();

        const entry = currentEntry();

        if (state.mode === 'form') {
            renderForm(entry);
            return;
        }

        if (state.mode === 'reject' && entry) {
            renderRejectForm(entry);
            return;
        }

        renderEntryView(entry);
    }

    async function submitJson(url, method, payload) {
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

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(firstError || data?.message || `Request failed with status ${response.status}`);
        }

        return data;
    }

    async function loadWorldBook(forceOpen = false) {
        if (forceOpen) {
            windowEl.classList.remove('hidden');
        }

        bodyEl.innerHTML = '<div class="text-[#8f8675]">Loading...</div>';
        entryListEl.innerHTML = '<div class="text-[#8f8675]">Loading...</div>';

        try {
            const response = await fetch(`/rooms/${encodeURIComponent(roomSlug)}/world-book`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data?.message || 'Could not load World Book.');
            }

            state.categories = Array.isArray(data.categories) ? data.categories : [];
            state.allEntriesCategory = data.all_entries_category || state.allEntriesCategory;
            state.entries = Array.isArray(data.entries) ? data.entries : [];
            state.pendingQueue = Array.isArray(data.pending_queue) ? data.pending_queue : [];
            state.permissions = data.permissions || state.permissions;
            state.ownedCharacters = Array.isArray(data.owned_characters) ? data.owned_characters : [];

            if (!visibleEntries().some((entry) => entry.id === state.selectedEntryId)) {
                state.selectedEntryId = null;
            }

            newEntryBtn.classList.toggle('hidden', !state.permissions.can_submit);
            render();
        } catch (error) {
            titleEl.textContent = 'World Book';
            subtitleEl.textContent = 'Could not load room canon.';
            bodyEl.innerHTML = `<div class="rounded border border-red-500/40 bg-red-500/10 p-4 text-red-200">${escapeHtml(error.message || 'Could not load World Book.')}</div>`;
            entryListEl.innerHTML = '';
        }
    }

    async function approveEntry(entryId) {
        await submitJson(`/rooms/${encodeURIComponent(roomSlug)}/world-book/${entryId}/approve`, 'POST', {});
        await loadWorldBook(true);
    }

    async function deleteEntry(entryId) {
        if (!window.confirm('Soft delete this entry?')) {
            return;
        }

        await submitJson(`/rooms/${encodeURIComponent(roomSlug)}/world-book/${entryId}`, 'DELETE', {});
        state.selectedEntryId = null;
        await loadWorldBook(true);
    }

    function viewportBounds() {
        return {
            width: window.innerWidth || document.documentElement.clientWidth || 0,
            height: window.innerHeight || document.documentElement.clientHeight || 0,
        };
    }

    function minWindowWidth() {
        const { width } = viewportBounds();
        return Math.min(WINDOW_MIN_WIDTH, Math.max(320, width - (VIEWPORT_PADDING * 2)));
    }

    function minWindowHeight() {
        const { height } = viewportBounds();
        return Math.min(WINDOW_MIN_HEIGHT, Math.max(320, height - (VIEWPORT_PADDING * 2)));
    }

    function maxWindowWidth() {
        const { width } = viewportBounds();
        return Math.max(minWindowWidth(), Math.min(WINDOW_MAX_WIDTH, width - (VIEWPORT_PADDING * 2)));
    }

    function maxWindowHeight() {
        const { height } = viewportBounds();
        return Math.max(minWindowHeight(), Math.min(WINDOW_MAX_HEIGHT, height - (VIEWPORT_PADDING * 2)));
    }

    function clampWindowSize(width, height) {
        return {
            width: Math.max(minWindowWidth(), Math.min(maxWindowWidth(), width)),
            height: Math.max(minWindowHeight(), Math.min(maxWindowHeight(), height)),
        };
    }

    function clampWindowPosition(left, top) {
        const { width: viewportWidth, height: viewportHeight } = viewportBounds();
        const rect = windowEl.getBoundingClientRect();
        const maxLeft = Math.max(VIEWPORT_PADDING, viewportWidth - rect.width - VIEWPORT_PADDING);
        const maxTop = Math.max(VIEWPORT_PADDING, viewportHeight - rect.height - VIEWPORT_PADDING);

        return {
            left: Math.min(Math.max(VIEWPORT_PADDING, left), maxLeft),
            top: Math.min(Math.max(VIEWPORT_PADDING, top), maxTop),
        };
    }

    function setWindowGeometry({ width, height, left, top }) {
        if (typeof width === 'number') windowEl.style.width = `${Math.round(width)}px`;
        if (typeof height === 'number') windowEl.style.height = `${Math.round(height)}px`;
        if (typeof left === 'number') windowEl.style.left = `${Math.round(left)}px`;
        if (typeof top === 'number') windowEl.style.top = `${Math.round(top)}px`;
        windowEl.style.right = 'auto';
    }

    function centerWindow() {
        const { width: viewportWidth, height: viewportHeight } = viewportBounds();
        const desired = clampWindowSize(Math.round(viewportWidth * 0.76), Math.round(viewportHeight * 0.68));
        const left = Math.round((viewportWidth - desired.width) / 2);
        const top = Math.round((viewportHeight - desired.height) / 2);
        const position = clampWindowPosition(left, top);

        setWindowGeometry({
            width: desired.width,
            height: desired.height,
            left: position.left,
            top: position.top,
        });
    }

    function keepWindowInViewport() {
        const rect = windowEl.getBoundingClientRect();
        const size = clampWindowSize(rect.width, rect.height);
        setWindowGeometry(size);
        const position = clampWindowPosition(windowEl.offsetLeft, windowEl.offsetTop);
        setWindowGeometry(position);
    }

    function setWindowOpenState(isOpen) {
        window.dispatchEvent(new CustomEvent('world-book-window-state', {
            detail: { open: isOpen },
        }));
    }

    function closeWorldBook() {
        closeMapLightbox();
        windowEl.classList.add('hidden');
        setWindowOpenState(false);
    }

    function openWorldBook() {
        centerWindow();
        windowEl.classList.remove('hidden');
        setWindowOpenState(true);
        window.dispatchEvent(new CustomEvent('room-tool-opened', { detail: { tool: 'world_book' } }));
        loadWorldBook(true);
    }

    refreshBtn?.addEventListener('click', () => loadWorldBook(true));
    closeBtn?.addEventListener('click', closeWorldBook);
    mapLightboxCloseBtn?.addEventListener('click', closeMapLightbox);
    mapLightboxEl?.addEventListener('click', (event) => {
        if (event.target === mapLightboxEl) {
            closeMapLightbox();
        }
    });
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mapLightboxEl && !mapLightboxEl.classList.contains('hidden')) {
            closeMapLightbox();
        }
    });
    newEntryBtn?.addEventListener('click', () => {
        state.formMode = 'create';
        state.mode = 'form';
        render();
    });
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = window.setTimeout(() => {
            state.searchTerm = searchInput.value;
            state.selectedEntryId = null;
            state.mode = 'view';
            render();
        }, SEARCH_DEBOUNCE_MS);
    });

    window.addEventListener('open-world-book-window', openWorldBook);
    window.addEventListener('resize', () => {
        if (!windowEl.classList.contains('hidden')) {
            keepWindowInViewport();
        }
    });

    let dragState = null;
    let resizeState = null;

    dragHandle?.addEventListener('mousedown', (event) => {
        if (resizeState) return;
        dragState = {
            offsetX: event.clientX - windowEl.offsetLeft,
            offsetY: event.clientY - windowEl.offsetTop,
        };
        event.preventDefault();
    });

    resizeHandle?.addEventListener('mousedown', (event) => {
        const rect = windowEl.getBoundingClientRect();
        resizeState = {
            startX: event.clientX,
            startY: event.clientY,
            startWidth: rect.width,
            startHeight: rect.height,
        };
        event.preventDefault();
        event.stopPropagation();
    });

    window.addEventListener('mousemove', (event) => {
        if (resizeState) {
            const nextWidth = resizeState.startWidth + (event.clientX - resizeState.startX);
            const nextHeight = resizeState.startHeight + (event.clientY - resizeState.startY);
            const size = clampWindowSize(nextWidth, nextHeight);
            setWindowGeometry(size);
            keepWindowInViewport();
            return;
        }

        if (!dragState) return;

        const position = clampWindowPosition(
            event.clientX - dragState.offsetX,
            event.clientY - dragState.offsetY,
        );
        setWindowGeometry(position);
    });

    window.addEventListener('mouseup', () => {
        dragState = null;
        resizeState = null;
    });
})();
</script>
