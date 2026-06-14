@props([
    'room',
])

<div
    id="pinned-notes-window"
    class="hidden fixed z-50 bg-[#0b0d10] border rounded-md shadow-[0_28px_72px_rgba(0,0,0,0.62)] flex flex-col overflow-hidden ring-1 ring-slate-200/10"
    style="width: min(1480px, calc(100vw - 48px)); height: min(760px, calc(100vh - 48px)); left: 28px; top: 28px; border-width: 3px; border-color: #273241;"
>
    <div id="pinned-notes-drag-handle" class="cursor-move flex items-center justify-between px-3 py-2 border-b border-[#273241] bg-[#11161d] shadow-[inset_0_-1px_0_rgba(148,163,184,0.05)]">
        <div>
            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Room Reference</div>
            <div class="text-sm text-slate-100 font-semibold">Pinned Notes</div>
        </div>
        <div class="flex items-center gap-2">
            <button id="pinned-notes-refresh-btn" type="button" class="rounded border border-[#334155] bg-[#0b0d10] px-2 py-1 text-slate-400 hover:border-slate-300/40 hover:bg-[#141a22] hover:text-slate-100 text-sm" title="Refresh">↻</button>
            <button id="pinned-notes-close-btn" type="button" class="rounded border border-[#334155] bg-[#0b0d10] px-2 py-1 text-slate-400 hover:border-slate-300/40 hover:bg-[#141a22] hover:text-slate-100 text-sm" title="Close">✕</button>
        </div>
    </div>

    <div class="flex-1 min-h-0 grid overflow-hidden" style="grid-template-columns: 15rem minmax(0, 1fr);">
        <div class="min-w-0 border-r border-[#273241] bg-[#0d1117] text-xs text-slate-300 flex flex-col overflow-hidden">
            <div class="p-3 border-b border-[#1f2937] space-y-2">
                <button id="pinned-notes-new-btn" type="button" class="w-full rounded border border-slate-300/25 bg-slate-200/5 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-100 hover:bg-slate-200/10 focus:outline-none focus:ring-2 focus:ring-slate-300/30">+ New Note</button>
                <div id="pinned-notes-status-pill" class="text-[10px] uppercase tracking-[0.18em] text-slate-500">Loading</div>
            </div>
            <div class="border-b border-[#1f2937] p-3 space-y-2">
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Search</div>
                <label class="sr-only" for="pinned-notes-search-input">Search pinned notes</label>
                <input id="pinned-notes-search-input" type="text" placeholder="Search title or body" class="block w-full rounded border border-[#334155] bg-[#0b0d10] px-3 py-2 text-[11px] text-slate-200 placeholder:text-slate-500 focus:border-slate-300 focus:ring-slate-300">
                <label class="flex items-center gap-2 text-[11px] text-slate-400">
                    <input id="pinned-notes-show-archived" type="checkbox" class="rounded border-[#334155] bg-[#0b0d10] text-slate-300 focus:ring-slate-300">
                    <span>Show archived</span>
                </label>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-2">
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Categories</div>
                <div id="pinned-notes-category-list" class="space-y-1"></div>
            </div>
        </div>

        <div id="pinned-notes-main-shell" class="min-w-0 bg-[#0b0f14] grid overflow-hidden" style="grid-template-columns: minmax(0, 1fr);">
            <div class="min-w-0 flex flex-col overflow-hidden">
                <div class="border-b border-[#1f2937] bg-[linear-gradient(135deg,rgba(148,163,184,0.10),transparent_42%),linear-gradient(180deg,#11161d,#0b0f14)] px-4 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Official Notes</div>
                            <div class="mt-1 text-lg font-semibold text-slate-100">Owner and staff room guidance</div>
                            <div class="mt-1 text-[12px] text-slate-400">Announcements, plot state, recaps, and event information.</div>
                        </div>
                        <div id="pinned-notes-board-summary" class="shrink-0 rounded border border-[#334155] bg-[#0b0d10] px-3 py-2 text-right text-[11px] text-slate-400">Loading</div>
                    </div>
                </div>
                <div id="pinned-notes-card-board" class="min-h-0 flex-1 overflow-y-auto p-4">
                    <div id="pinned-notes-card-list" class="grid content-start justify-start gap-3" style="grid-template-columns: repeat(auto-fill, minmax(300px, 360px));">
                        <div class="text-slate-400">Loading...</div>
                    </div>
                </div>
            </div>

            <div id="pinned-notes-form-panel" class="hidden min-w-0 flex-col overflow-hidden border-l border-[#273241] bg-[#090c10]">
                <div class="px-4 py-3 border-b border-[#273241] bg-[#11161d] flex items-center justify-between gap-3 shadow-[inset_0_-1px_0_rgba(148,163,184,0.04)]">
                    <div class="min-w-0">
                        <div id="pinned-notes-form-title" class="truncate text-sm font-semibold text-slate-100">New Pinned Note</div>
                        <div id="pinned-notes-form-subtitle" class="mt-1 text-[11px] text-slate-400">Visible to everyone with room access.</div>
                    </div>
                    <button id="pinned-notes-form-close-btn" type="button" class="rounded border border-[#334155] bg-[#0b0d10] px-2 py-1 text-slate-400 hover:border-slate-300/40 hover:text-slate-100 text-sm">✕</button>
                </div>
                <div id="pinned-notes-form-body" class="flex-1 min-h-0 overflow-y-auto bg-[#080b0f] bg-[radial-gradient(circle_at_top_right,rgba(148,163,184,0.06),transparent_24rem)] p-5 text-sm text-slate-300"></div>
            </div>
        </div>
    </div>
    <div id="pinned-notes-resize-handle" class="absolute bottom-0 right-0 h-4 w-4 cursor-se-resize" title="Resize"></div>
</div>

<script>
(function () {
    const windowEl = document.getElementById('pinned-notes-window');
    if (!windowEl) return;

    const roomSlug = @json($room->slug);
    const csrf = @json(csrf_token());
    const categoryListEl = document.getElementById('pinned-notes-category-list');
    const cardListEl = document.getElementById('pinned-notes-card-list');
    const boardSummaryEl = document.getElementById('pinned-notes-board-summary');
    const formPanelEl = document.getElementById('pinned-notes-form-panel');
    const formTitleEl = document.getElementById('pinned-notes-form-title');
    const formSubtitleEl = document.getElementById('pinned-notes-form-subtitle');
    const formBodyEl = document.getElementById('pinned-notes-form-body');
    const mainShellEl = document.getElementById('pinned-notes-main-shell');
    const statusPillEl = document.getElementById('pinned-notes-status-pill');
    const refreshBtn = document.getElementById('pinned-notes-refresh-btn');
    const closeBtn = document.getElementById('pinned-notes-close-btn');
    const newBtn = document.getElementById('pinned-notes-new-btn');
    const formCloseBtn = document.getElementById('pinned-notes-form-close-btn');
    const searchInput = document.getElementById('pinned-notes-search-input');
    const showArchivedInput = document.getElementById('pinned-notes-show-archived');
    const dragHandle = document.getElementById('pinned-notes-drag-handle');
    const resizeHandle = document.getElementById('pinned-notes-resize-handle');

    const WINDOW_MIN_WIDTH = 980;
    const WINDOW_MIN_HEIGHT = 560;
    const WINDOW_MAX_WIDTH = 1600;
    const WINDOW_MAX_HEIGHT = 920;
    const VIEWPORT_PADDING = 16;
    const SEARCH_DEBOUNCE_MS = 120;

    const state = {
        categories: [],
        statuses: [],
        accentColors: [],
        notes: [],
        permissions: {
            can_create: false,
            can_manage: false,
        },
        selectedCategory: null,
        searchTerm: '',
        showArchived: false,
        mode: 'view',
        formMode: 'create',
        selectedNoteId: null,
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
            return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
        } catch (error) {
            return value;
        }
    }

    function normalizeSearchTerm(value) {
        return String(value || '').trim().toLowerCase();
    }

    function noteMatchesSearch(note) {
        const term = normalizeSearchTerm(state.searchTerm);
        if (!term) return true;
        return String(note.search_text || '').toLowerCase().includes(term);
    }

    function visibleNotes() {
        return state.notes.filter((note) => {
            if (note.status === 'archived' && !state.showArchived) return false;
            if (state.selectedCategory && note.category !== state.selectedCategory) return false;
            return noteMatchesSearch(note);
        });
    }

    function currentNote() {
        return state.notes.find((note) => note.id === state.selectedNoteId) || null;
    }

    function buildTone({ borderColor, stripColor, ringColor, glowColor, pillBorderColor, pillBgColor, pillTextColor, titleColor }) {
        return {
            cardStyle: `border-color: ${borderColor}; box-shadow: inset 0 0 0 1px ${ringColor}, 0 10px 26px rgba(0,0,0,0.18); background: linear-gradient(180deg, ${glowColor}, transparent 62%), #11161d;`,
            stripStyle: `background: linear-gradient(180deg, ${stripColor}, rgba(255,255,255,0));`,
            pillStyle: `border-color: ${pillBorderColor}; background-color: ${pillBgColor}; color: ${pillTextColor};`,
            titleStyle: `color: ${titleColor};`,
        };
    }

    function categoryTone(category) {
        const map = {
            announcements: buildTone({ borderColor: 'rgba(148,163,184,0.35)', stripColor: 'rgba(203,213,225,0.92)', ringColor: 'rgba(148,163,184,0.09)', glowColor: 'rgba(148,163,184,0.08)', pillBorderColor: 'rgba(148,163,184,0.25)', pillBgColor: 'rgba(148,163,184,0.09)', pillTextColor: '#e2e8f0', titleColor: '#f8fafc' }),
            current_plot: buildTone({ borderColor: 'rgba(94,234,212,0.28)', stripColor: 'rgba(94,234,212,0.94)', ringColor: 'rgba(94,234,212,0.08)', glowColor: 'rgba(45,212,191,0.08)', pillBorderColor: 'rgba(45,212,191,0.24)', pillBgColor: 'rgba(45,212,191,0.08)', pillTextColor: '#d1fae5', titleColor: '#ecfeff' }),
            session_recaps: buildTone({ borderColor: 'rgba(129,140,248,0.26)', stripColor: 'rgba(165,180,252,0.94)', ringColor: 'rgba(129,140,248,0.08)', glowColor: 'rgba(129,140,248,0.08)', pillBorderColor: 'rgba(129,140,248,0.22)', pillBgColor: 'rgba(129,140,248,0.08)', pillTextColor: '#e0e7ff', titleColor: '#eef2ff' }),
            events: buildTone({ borderColor: 'rgba(250,204,21,0.26)', stripColor: 'rgba(253,224,71,0.94)', ringColor: 'rgba(250,204,21,0.08)', glowColor: 'rgba(250,204,21,0.08)', pillBorderColor: 'rgba(250,204,21,0.22)', pillBgColor: 'rgba(250,204,21,0.08)', pillTextColor: '#fef3c7', titleColor: '#fefce8' }),
            other: buildTone({ borderColor: 'rgba(100,116,139,0.28)', stripColor: 'rgba(148,163,184,0.90)', ringColor: 'rgba(100,116,139,0.08)', glowColor: 'rgba(100,116,139,0.08)', pillBorderColor: 'rgba(100,116,139,0.22)', pillBgColor: 'rgba(100,116,139,0.08)', pillTextColor: '#e2e8f0', titleColor: '#f8fafc' }),
        };

        return map[category] || map.other;
    }

    function accentTone(accentColor) {
        const map = {
            red: buildTone({ borderColor: 'rgba(239, 68, 68, 0.50)', stripColor: 'rgba(248, 113, 113, 0.96)', ringColor: 'rgba(239, 68, 68, 0.18)', glowColor: 'rgba(248, 113, 113, 0.10)', pillBorderColor: 'rgba(239, 68, 68, 0.42)', pillBgColor: 'rgba(239, 68, 68, 0.12)', pillTextColor: '#ffd8d8', titleColor: '#ffd8d8' }),
            orange: buildTone({ borderColor: 'rgba(249, 115, 22, 0.50)', stripColor: 'rgba(251, 146, 60, 0.96)', ringColor: 'rgba(249, 115, 22, 0.18)', glowColor: 'rgba(251, 146, 60, 0.10)', pillBorderColor: 'rgba(249, 115, 22, 0.42)', pillBgColor: 'rgba(249, 115, 22, 0.12)', pillTextColor: '#ffe0c7', titleColor: '#ffe0c7' }),
            gold: buildTone({ borderColor: 'rgba(245, 158, 11, 0.50)', stripColor: 'rgba(251, 191, 36, 0.96)', ringColor: 'rgba(245, 158, 11, 0.18)', glowColor: 'rgba(245, 158, 11, 0.10)', pillBorderColor: 'rgba(245, 158, 11, 0.42)', pillBgColor: 'rgba(245, 158, 11, 0.12)', pillTextColor: '#ffefbf', titleColor: '#ffefbf' }),
            green: buildTone({ borderColor: 'rgba(16, 185, 129, 0.50)', stripColor: 'rgba(52, 211, 153, 0.96)', ringColor: 'rgba(16, 185, 129, 0.18)', glowColor: 'rgba(52, 211, 153, 0.10)', pillBorderColor: 'rgba(16, 185, 129, 0.42)', pillBgColor: 'rgba(16, 185, 129, 0.12)', pillTextColor: '#d8fff0', titleColor: '#d8fff0' }),
            blue: buildTone({ borderColor: 'rgba(14, 165, 233, 0.50)', stripColor: 'rgba(56, 189, 248, 0.96)', ringColor: 'rgba(14, 165, 233, 0.18)', glowColor: 'rgba(56, 189, 248, 0.10)', pillBorderColor: 'rgba(14, 165, 233, 0.42)', pillBgColor: 'rgba(14, 165, 233, 0.12)', pillTextColor: '#d8f2ff', titleColor: '#d8f2ff' }),
            purple: buildTone({ borderColor: 'rgba(139, 92, 246, 0.50)', stripColor: 'rgba(167, 139, 250, 0.96)', ringColor: 'rgba(139, 92, 246, 0.18)', glowColor: 'rgba(167, 139, 250, 0.10)', pillBorderColor: 'rgba(139, 92, 246, 0.42)', pillBgColor: 'rgba(139, 92, 246, 0.12)', pillTextColor: '#e8dcff', titleColor: '#e8dcff' }),
            pink: buildTone({ borderColor: 'rgba(236, 72, 153, 0.50)', stripColor: 'rgba(244, 114, 182, 0.96)', ringColor: 'rgba(236, 72, 153, 0.18)', glowColor: 'rgba(244, 114, 182, 0.10)', pillBorderColor: 'rgba(236, 72, 153, 0.42)', pillBgColor: 'rgba(236, 72, 153, 0.12)', pillTextColor: '#ffdced', titleColor: '#ffdced' }),
            gray: buildTone({ borderColor: 'rgba(148, 163, 184, 0.44)', stripColor: 'rgba(203, 213, 225, 0.92)', ringColor: 'rgba(148, 163, 184, 0.16)', glowColor: 'rgba(148, 163, 184, 0.10)', pillBorderColor: 'rgba(148, 163, 184, 0.36)', pillBgColor: 'rgba(148, 163, 184, 0.12)', pillTextColor: '#e6edf7', titleColor: '#e6edf7' }),
        };

        return accentColor ? (map[accentColor] || null) : null;
    }

    function effectiveNoteTone(note) {
        return accentTone(note.accent_color) || categoryTone(note.category);
    }

    function characterTriggerHtml(character, nameClass = '', fallbackName = 'Unknown') {
        const characterId = parseInt(character?.id || 0, 10) || 0;
        const userId = parseInt(character?.user_id || 0, 10) || 0;
        const name = String(character?.name || fallbackName || 'Unknown').trim() || 'Unknown';
        const handle = String(character?.handle || '').trim();
        const avatar = String(character?.avatar || '').trim();

        if (!characterId) {
            return `<span class="${nameClass}">${escapeHtml(name)}</span>`;
        }

        return `<button type="button"
            class="char-trigger ${nameClass} rounded-sm text-left hover:underline focus:outline-none focus:ring-2 focus:ring-amber-500/50"
            data-character-id="${characterId}"
            data-user-id="${userId || ''}"
            data-character-name="${escapeHtml(name)}"
            data-character-handle="${escapeHtml(handle)}"
            data-character-avatar="${escapeHtml(avatar)}"
        >${escapeHtml(name)}</button>`;
    }

    function metadataRow(note) {
        const items = [];


        if (note.created_at) {
            items.push(`Created ${escapeHtml(formatDate(note.created_at))}`);
        }

        if (note.updated_at) {
            items.push(`Updated ${escapeHtml(formatDate(note.updated_at))}`);
        }

        if (note.expires_at) {
            items.push(`Expires ${escapeHtml(formatDate(note.expires_at))}`);
        }

        return items.join(' • ');
    }

    function renderCategories() {
        const categories = [{ key: null, label: 'All Notes', icon: '📚' }, ...state.categories];

        categoryListEl.innerHTML = categories.map((category) => {
            const active = category.key === state.selectedCategory;
            const count = state.notes.filter((note) => {
                if (note.status === 'archived' && !state.showArchived) return false;
                return category.key === null || note.category === category.key;
            }).length;

            return `
                <button type="button" data-pinned-note-category="${category.key ?? ''}" class="${active ? 'w-full rounded border border-slate-300/30 bg-slate-200/10 px-3 py-2 text-left text-[11px] font-semibold text-slate-100' : 'w-full rounded border border-[#334155] bg-[#11161d] px-3 py-2 text-left text-[11px] text-slate-300 hover:border-slate-300/30 hover:text-slate-100'}">
                    <span class="flex items-center gap-2"><span>${escapeHtml(category.icon || '')}</span><span>${escapeHtml(category.label)}</span></span>
                    <span class="mt-0.5 block text-[10px] text-slate-500">${count} note${count === 1 ? '' : 's'}</span>
                </button>
            `;
        }).join('');

        categoryListEl.querySelectorAll('[data-pinned-note-category]').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedCategory = button.dataset.pinnedNoteCategory || null;
                render();
            });
        });
    }

    function renderCards() {
        const notes = visibleNotes();

        if (notes.length === 0) {
            cardListEl.innerHTML = `
                <div class="rounded border border-dashed border-[#334155] bg-[#0d1117] p-5 text-sm text-slate-400">
                    No pinned notes match the current filters.
                </div>
            `;
            return;
        }

        cardListEl.innerHTML = notes.map((note) => {
            const tone = effectiveNoteTone(note);
            const statusBadge = note.status === 'archived'
                ? '<span class="rounded-full border border-slate-500/30 bg-slate-400/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-300">Archived</span>'
                : `<span class="rounded-full border px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]" style="${tone.pillStyle}">Active</span>`;
            const expiresBadge = note.expires_at
                ? `<span class="rounded-full border border-[#334155] bg-[#0b0d10] px-2 py-1 text-[10px] text-slate-300">Expires ${escapeHtml(formatDate(note.expires_at))}</span>`
                : '';
            const actionButtons = note.viewer_can_manage
                ? `
                    <div class="mt-4 flex items-center gap-2">
                        <button type="button" data-pinned-notes-edit="${note.id}" class="rounded border border-[#475569] bg-[#0b0d10] px-2 py-1 text-[11px] font-semibold text-slate-200 hover:border-slate-200/30 hover:text-white">Edit</button>
                        <button type="button" data-pinned-notes-delete="${note.id}" class="rounded border border-red-500/30 bg-red-500/10 px-2 py-1 text-[11px] font-semibold text-red-200 hover:bg-red-500/20">Delete</button>
                    </div>
                `
                : '';

            return `
                <article class="rounded-md border border-l-4 p-4 text-sm text-slate-300" style="${tone.cardStyle}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]" style="${tone.pillStyle}">${escapeHtml(note.category_label)}</span>
                                ${statusBadge}
                            </div>
                            <h3 class="mt-3 text-base font-semibold leading-tight" style="${tone.titleStyle}">${escapeHtml(note.title)}</h3>
                        </div>
                        <div class="shrink-0 text-lg">${escapeHtml(note.category_icon || '📌')}</div>
                    </div>
                    <div class="mt-3 whitespace-pre-wrap break-words text-[13px] leading-relaxed text-slate-200">${escapeHtml(note.body)}</div>
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-[10px] text-slate-400">
                        ${expiresBadge}
                    </div>
                    <div class="mt-4 border-t border-white/5 pt-3 text-[10px] leading-relaxed text-slate-500"><div class="font-semibold text-slate-300">Posted by ${characterTriggerHtml(note.author_character, 'text-slate-300 font-semibold', 'Unknown')}</div><div class="mt-1">${metadataRow(note)}</div></div>
                    ${actionButtons}
                </article>
            `;
        }).join('');

        cardListEl.querySelectorAll('[data-pinned-notes-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const note = state.notes.find((item) => item.id === Number(button.dataset.pinnedNotesEdit));
                if (note) openForm('edit', note);
            });
        });

        cardListEl.querySelectorAll('[data-pinned-notes-delete]').forEach((button) => {
            button.addEventListener('click', async () => {
                const note = state.notes.find((item) => item.id === Number(button.dataset.pinnedNotesDelete));
                if (!note) return;
                const confirmed = window.confirm(`Delete "${note.title}"?`);
                if (!confirmed) return;
                await requestJson(`/rooms/${encodeURIComponent(roomSlug)}/pinned-notes/${note.id}`, {
                    method: 'DELETE',
                });
                await fetchBoard();
                render();
            });
        });
    }

    function renderSummary() {
        const visibleCount = visibleNotes().length;
        const activeCount = state.notes.filter((note) => note.status === 'active').length;
        const archivedCount = state.notes.filter((note) => note.status === 'archived').length;

        boardSummaryEl.innerHTML = `
            <div class="font-semibold text-slate-200">${visibleCount} visible</div>
            <div class="mt-1">${activeCount} active • ${archivedCount} archived</div>
        `;

        statusPillEl.textContent = state.permissions.can_manage ? 'Staff Manage Access' : 'Read Only';
    }

    function renderForm() {
        if (state.mode !== 'form' || !state.permissions.can_manage) {
            formPanelEl.classList.add('hidden');
            formPanelEl.classList.remove('flex');
            mainShellEl.style.gridTemplateColumns = 'minmax(0, 1fr)';
            return;
        }

        formPanelEl.classList.remove('hidden');
        formPanelEl.classList.add('flex');
        mainShellEl.style.gridTemplateColumns = 'minmax(0, 1fr) 25rem';

        const note = currentNote();
        const isEdit = state.formMode === 'edit' && note;

        formTitleEl.textContent = isEdit ? 'Edit Pinned Note' : 'New Pinned Note';
        formSubtitleEl.textContent = isEdit
            ? 'Update official room information.'
            : 'Create official room information for everyone with access.';

        const categoryOptions = state.categories.map((category) => `
            <option value="${escapeHtml(category.key)}" ${isEdit && note.category === category.key ? 'selected' : ''}>
                ${escapeHtml(category.label)}
            </option>
        `).join('');

        const accentColorOptions = state.accentColors.map((accent) => `
            <option value="${escapeHtml(accent.key)}" ${isEdit && note.accent_color === accent.key ? 'selected' : ''}>
                ${escapeHtml(accent.label)}
            </option>
        `).join('');

        const statusOptions = state.statuses.map((status) => `
            <option value="${escapeHtml(status.key)}" ${isEdit && note.status === status.key ? 'selected' : ''}>
                ${escapeHtml(status.label)}
            </option>
        `).join('');

        formBodyEl.innerHTML = `
            <form id="pinned-notes-form" class="space-y-4">
                <div>
                    <label for="pinned-notes-title" class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Title</label>
                    <input id="pinned-notes-title" name="title" type="text" maxlength="255" value="${escapeHtml(isEdit ? note.title : '')}" class="mt-1 block w-full rounded border border-[#334155] bg-[#0b0d10] px-3 py-2 text-sm text-slate-100 focus:border-slate-300 focus:ring-slate-300">
                </div>
                <div>
                    <label for="pinned-notes-category" class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Category</label>
                    <select id="pinned-notes-category" name="category" class="mt-1 block w-full rounded border border-[#334155] bg-[#0b0d10] px-3 py-2 text-sm text-slate-100 focus:border-slate-300 focus:ring-slate-300">
                        ${categoryOptions}
                    </select>
                </div>
                <div>
                    <label for="pinned-notes-accent-color" class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Accent Color</label>
                    <select id="pinned-notes-accent-color" name="accent_color" class="mt-1 block w-full rounded border border-[#334155] bg-[#0b0d10] px-3 py-2 text-sm text-slate-100 focus:border-slate-300 focus:ring-slate-300">
                        <option value="default" ${isEdit && !note.accent_color ? 'selected' : ''}>Default (Category)</option>
                        ${accentColorOptions}
                    </select>
                    <div class="mt-1 text-[10px] text-slate-500">Optional controlled accent for the card frame and labels.</div>
                </div>
                <div>
                    <label for="pinned-notes-body" class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Body</label>
                    <textarea id="pinned-notes-body" name="body" rows="10" maxlength="20000" class="mt-1 block w-full rounded border border-[#334155] bg-[#0b0d10] px-3 py-2 text-sm text-slate-100 focus:border-slate-300 focus:ring-slate-300">${escapeHtml(isEdit ? note.body : '')}</textarea>
                </div>
                <div>
                    <label for="pinned-notes-expires-at" class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Expiration Date</label>
                    <input id="pinned-notes-expires-at" name="expires_at" type="date" value="${isEdit && note.expires_at ? escapeHtml(String(note.expires_at).slice(0, 10)) : ''}" class="mt-1 block w-full rounded border border-[#334155] bg-[#0b0d10] px-3 py-2 text-sm text-slate-100 focus:border-slate-300 focus:ring-slate-300">
                </div>
                ${isEdit ? `
                    <div>
                        <label for="pinned-notes-status" class="block text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">Status</label>
                        <select id="pinned-notes-status" name="status" class="mt-1 block w-full rounded border border-[#334155] bg-[#0b0d10] px-3 py-2 text-sm text-slate-100 focus:border-slate-300 focus:ring-slate-300">
                            ${statusOptions}
                        </select>
                    </div>
                ` : ''}
                <div class="flex items-center gap-2 pt-2">
                    <button type="submit" class="rounded border border-slate-300/30 bg-slate-100/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-100 hover:bg-slate-100/15">Save</button>
                    <button type="button" id="pinned-notes-cancel-btn" class="rounded border border-[#334155] bg-[#0b0d10] px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-300 hover:text-slate-100">Cancel</button>
                </div>
            </form>
        `;

        formBodyEl.querySelector('#pinned-notes-cancel-btn')?.addEventListener('click', closeForm);
        formBodyEl.querySelector('#pinned-notes-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const formData = new FormData(form);
            const payload = {
                title: String(formData.get('title') || ''),
                category: String(formData.get('category') || ''),
                body: String(formData.get('body') || ''),
                expires_at: String(formData.get('expires_at') || ''),
                accent_color: String(formData.get('accent_color') || 'default'),
            };

            if (isEdit) {
                payload.status = String(formData.get('status') || '');
            }

            const path = isEdit
                ? `/rooms/${encodeURIComponent(roomSlug)}/pinned-notes/${note.id}`
                : `/rooms/${encodeURIComponent(roomSlug)}/pinned-notes`;

            await requestJson(path, {
                method: isEdit ? 'PATCH' : 'POST',
                body: JSON.stringify(payload),
            });

            await fetchBoard();
            closeForm();
            render();
        });
    }

    function render() {
        if (!state.permissions.can_manage && state.mode === 'form') {
            state.mode = 'view';
        }

        if (!state.showArchived && state.selectedNoteId) {
            const selected = currentNote();
            if (selected && selected.status === 'archived') {
                state.selectedNoteId = null;
            }
        }

        renderCategories();
        renderSummary();
        renderCards();
        renderForm();
    }

    function openForm(mode, note = null) {
        if (!state.permissions.can_manage) return;
        state.mode = 'form';
        state.formMode = mode;
        state.selectedNoteId = note ? note.id : null;
        render();
    }

    function closeForm() {
        state.mode = 'view';
        state.selectedNoteId = null;
        render();
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            method: options.method || 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
            body: options.body,
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = payload.errors
                ? Object.values(payload.errors).flat().join('\n')
                : null;
            const message = errors || payload.message || 'The pinned notes request failed.';
            alert(message);
            throw new Error(message);
        }

        return payload;
    }

    async function fetchBoard() {
        const payload = await requestJson(`/rooms/${encodeURIComponent(roomSlug)}/pinned-notes`);
        state.categories = payload.categories || [];
        state.statuses = payload.statuses || [];
        state.accentColors = payload.accent_colors || [];
        state.notes = payload.notes || [];
        state.permissions = payload.permissions || { can_create: false, can_manage: false };

        newBtn.disabled = !state.permissions.can_create;
        newBtn.className = state.permissions.can_create
            ? 'w-full rounded border border-slate-300/25 bg-slate-200/5 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-100 hover:bg-slate-200/10 focus:outline-none focus:ring-2 focus:ring-slate-300/30'
            : 'w-full rounded border border-dashed border-[#334155] bg-[#11161d] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 cursor-not-allowed opacity-70';
    }

    function setWindowOpenState(isOpen) {
        window.dispatchEvent(new CustomEvent('pinned-notes-window-state', {
            detail: { open: isOpen },
        }));
    }

    function openWindow() {
        windowEl.classList.remove('hidden');
        windowEl.style.zIndex = '60';
        setWindowOpenState(true);
        window.dispatchEvent(new CustomEvent('room-tool-opened', { detail: { tool: 'pinned_notes' } }));
        fetchBoard().then(render).catch(() => {
            formBodyEl.innerHTML = '<div class="text-red-300">Failed to load pinned notes.</div>';
        });
    }

    function closeWindow() {
        windowEl.classList.add('hidden');
        closeForm();
        setWindowOpenState(false);
    }

    function clampWindowToViewport() {
        const rect = windowEl.getBoundingClientRect();
        const maxLeft = Math.max(VIEWPORT_PADDING, window.innerWidth - rect.width - VIEWPORT_PADDING);
        const maxTop = Math.max(VIEWPORT_PADDING, window.innerHeight - rect.height - VIEWPORT_PADDING);
        windowEl.style.left = `${Math.min(Math.max(rect.left, VIEWPORT_PADDING), maxLeft)}px`;
        windowEl.style.top = `${Math.min(Math.max(rect.top, VIEWPORT_PADDING), maxTop)}px`;
    }

    function bindDrag() {
        let dragState = null;
        dragHandle.addEventListener('mousedown', (event) => {
            dragState = { startX: event.clientX, startY: event.clientY, startLeft: windowEl.offsetLeft, startTop: windowEl.offsetTop };
            event.preventDefault();
        });
        window.addEventListener('mousemove', (event) => {
            if (!dragState) return;
            windowEl.style.left = `${dragState.startLeft + (event.clientX - dragState.startX)}px`;
            windowEl.style.top = `${dragState.startTop + (event.clientY - dragState.startY)}px`;
            clampWindowToViewport();
        });
        window.addEventListener('mouseup', () => { dragState = null; });
    }

    function bindResize() {
        let resizeState = null;
        resizeHandle.addEventListener('mousedown', (event) => {
            resizeState = { startX: event.clientX, startY: event.clientY, startWidth: windowEl.offsetWidth, startHeight: windowEl.offsetHeight };
            event.preventDefault();
        });
        window.addEventListener('mousemove', (event) => {
            if (!resizeState) return;
            const nextWidth = Math.min(WINDOW_MAX_WIDTH, Math.max(WINDOW_MIN_WIDTH, resizeState.startWidth + (event.clientX - resizeState.startX)));
            const nextHeight = Math.min(WINDOW_MAX_HEIGHT, Math.max(WINDOW_MIN_HEIGHT, resizeState.startHeight + (event.clientY - resizeState.startY)));
            windowEl.style.width = `${Math.min(nextWidth, window.innerWidth - (VIEWPORT_PADDING * 2))}px`;
            windowEl.style.height = `${Math.min(nextHeight, window.innerHeight - (VIEWPORT_PADDING * 2))}px`;
            clampWindowToViewport();
        });
        window.addEventListener('mouseup', () => { resizeState = null; });
    }

    refreshBtn.addEventListener('click', async () => { await fetchBoard(); render(); });
    closeBtn.addEventListener('click', closeWindow);
    formCloseBtn.addEventListener('click', closeForm);
    newBtn.addEventListener('click', () => openForm('create'));

    searchInput.addEventListener('input', () => {
        window.clearTimeout(searchDebounceTimer);
        searchDebounceTimer = window.setTimeout(() => {
            state.searchTerm = searchInput.value;
            render();
        }, SEARCH_DEBOUNCE_MS);
    });

    showArchivedInput.addEventListener('change', () => {
        state.showArchived = showArchivedInput.checked;
        render();
    });

    window.addEventListener('resize', clampWindowToViewport);
    window.addEventListener('open-pinned-notes-window', openWindow);

    bindDrag();
    bindResize();
})();
</script>
