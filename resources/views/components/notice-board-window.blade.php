@props([
    'room',
])

<div
    id="notice-board-window"
    class="hidden fixed z-50 bg-[#0b0b0c] border rounded-md shadow-[0_28px_72px_rgba(0,0,0,0.62)] flex flex-col overflow-hidden ring-1 ring-amber-500/10"
    style="width: min(1720px, calc(100vw - 48px)); height: min(780px, calc(100vh - 48px)); left: 32px; top: 32px; border-width: 4px; border-color: #3a2d1b;"
>
    <div id="notice-board-drag-handle" class="cursor-move flex items-center justify-between px-3 py-2 border-b border-[#3a2d1b] bg-[#111114] shadow-[inset_0_-1px_0_rgba(245,158,11,0.04)]">
        <div>
            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-400">Room Activity</div>
            <div class="text-sm text-[#f2dfb5] font-semibold">Notice Board</div>
        </div>
        <div class="flex items-center gap-2">
            <button id="notice-board-refresh-btn" type="button" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm" title="Refresh">↻</button>
            <button id="notice-board-close-btn" type="button" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm" title="Close">✕</button>
        </div>
    </div>

    <div class="flex-1 min-h-0 grid overflow-hidden" style="grid-template-columns: 15rem minmax(0, 1fr);">
        <div class="min-w-0 border-r border-[#332817] bg-[#0b0b0c] text-xs text-[#d6c8ad] flex flex-col overflow-hidden">
            <div class="p-3 border-b border-[#2a241a] space-y-2">
                <button id="notice-board-new-btn" type="button" class="w-full rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50">+ Post Notice</button>
                <div id="notice-board-status-pill" class="text-[10px] uppercase tracking-[0.18em] text-[#8f8675]">Loading</div>
            </div>
            <div class="border-b border-[#2a241a] p-3 space-y-2">
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Search</div>
                <label class="sr-only" for="notice-board-search-input">Search notice board</label>
                <input id="notice-board-search-input" type="text" placeholder="Search title or body" class="block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-[11px] text-[#d6c8ad] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500">
                <label class="flex items-center gap-2 text-[11px] text-[#8f8675]">
                    <input id="notice-board-show-archived" type="checkbox" class="rounded border-[#332817] bg-[#0b0b0c] text-amber-500 focus:ring-amber-500">
                    <span>Show archived</span>
                </label>
            </div>
            <div class="border-b border-[#2a241a] p-3 space-y-2">
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Categories</div>
                <div id="notice-board-category-list" class="space-y-1"></div>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-2">
                <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">Status</div>
                <div id="notice-board-status-list" class="space-y-1"></div>
            </div>
        </div>

        <div id="notice-board-main-shell" class="min-w-0 bg-[#0c0c0e] grid overflow-hidden" style="grid-template-columns: minmax(0, 1fr);">
            <div class="min-w-0 flex flex-col overflow-hidden">
                <div class="border-b border-[#2a241a] bg-[linear-gradient(135deg,rgba(245,158,11,0.08),transparent_45%),linear-gradient(180deg,#101012,#0d0d0f)] px-4 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-400">Board View</div>
                            <div class="mt-1 text-lg font-semibold text-[#f2dfb5]">Hooks, jobs, rumors, and opportunities</div>
                            <div class="mt-1 text-[12px] text-[#8f8675]">Scan the board, pick a hook, and act.</div>
                        </div>
                        <div id="notice-board-board-summary" class="shrink-0 rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-right text-[11px] text-[#8f8675]">Loading</div>
                    </div>
                </div>
                <div id="notice-board-card-board" class="min-h-0 flex-1 overflow-y-auto p-4">
                    <div id="notice-board-card-list" class="grid content-start justify-start gap-3" style="grid-template-columns: repeat(auto-fill, minmax(280px, 320px));">
                        <div class="text-[#8f8675]">Loading...</div>
                    </div>
                </div>
            </div>

            <div id="notice-board-form-panel" class="hidden min-w-0 flex-col overflow-hidden border-l border-[#332817] bg-[#080809]">
                <div class="px-4 py-3 border-b border-[#332817] bg-[#101012] flex items-center justify-between gap-3 shadow-[inset_0_-1px_0_rgba(245,158,11,0.03)]">
                    <div class="min-w-0">
                        <div id="notice-board-form-title" class="truncate text-sm font-semibold text-[#f2dfb5]">Post Notice</div>
                        <div id="notice-board-form-subtitle" class="mt-1 text-[11px] text-[#8f8675]">Create hooks, rumors, jobs, and opportunities.</div>
                    </div>
                    <button id="notice-board-form-close-btn" type="button" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5] text-sm">✕</button>
                </div>
                <div id="notice-board-form-body" class="flex-1 min-h-0 overflow-y-auto bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.03),transparent_24rem)] p-5 text-sm text-[#d6c8ad]"></div>
            </div>
        </div>
    </div>
    <div id="notice-board-resize-handle" class="absolute bottom-0 right-0 h-4 w-4 cursor-se-resize" title="Resize"></div>
</div>

<script>
(function () {
    const windowEl = document.getElementById('notice-board-window');
    if (!windowEl) return;

    const roomSlug = @json($room->slug);
    const csrf = @json(csrf_token());
    const categoryListEl = document.getElementById('notice-board-category-list');
    const statusListEl = document.getElementById('notice-board-status-list');
    const cardListEl = document.getElementById('notice-board-card-list');
    const boardSummaryEl = document.getElementById('notice-board-board-summary');
    const formPanelEl = document.getElementById('notice-board-form-panel');
    const formTitleEl = document.getElementById('notice-board-form-title');
    const formSubtitleEl = document.getElementById('notice-board-form-subtitle');
    const formBodyEl = document.getElementById('notice-board-form-body');
    const mainShellEl = document.getElementById('notice-board-main-shell');
    const statusPillEl = document.getElementById('notice-board-status-pill');
    const refreshBtn = document.getElementById('notice-board-refresh-btn');
    const closeBtn = document.getElementById('notice-board-close-btn');
    const newBtn = document.getElementById('notice-board-new-btn');
    const formCloseBtn = document.getElementById('notice-board-form-close-btn');
    const searchInput = document.getElementById('notice-board-search-input');
    const showArchivedInput = document.getElementById('notice-board-show-archived');
    const dragHandle = document.getElementById('notice-board-drag-handle');
    const resizeHandle = document.getElementById('notice-board-resize-handle');

    const WINDOW_MIN_WIDTH = 1100;
    const WINDOW_MIN_HEIGHT = 580;
    const WINDOW_MAX_WIDTH = 1760;
    const WINDOW_MAX_HEIGHT = 920;
    const VIEWPORT_PADDING = 16;
    const SEARCH_DEBOUNCE_MS = 120;

    const state = {
        categories: [],
        statuses: [],
        accentColors: [],
        notices: [],
        permissions: {
            can_create: false,
            can_manage: false,
        },
        selectedCategory: null,
        selectedStatuses: new Set(['active', 'closed']),
        selectedNoticeId: null,
        searchTerm: '',
        showArchived: false,
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
            return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
        } catch (error) {
            return value;
        }
    }

    function relativeDate(value) {
        if (!value) return '';
        try {
            const hours = Math.round((new Date(value).getTime() - Date.now()) / 3600000);
            return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(hours, 'hour');
        } catch (error) {
            return '';
        }
    }

    function buildTone({ borderColor, stripColor, ringColor, glowColor, pillBorderColor, pillBgColor, pillTextColor, titleColor }) {
        return {
            cardStyle: `border-color: ${borderColor}; border-left-color: ${stripColor}; box-shadow: inset 0 0 0 1px ${ringColor}, 0 12px 24px rgba(0,0,0,0.18); background: linear-gradient(180deg, ${glowColor}, transparent 60%), #101012;`,
            pillStyle: `border-color: ${pillBorderColor}; background-color: ${pillBgColor}; color: ${pillTextColor};`,
            titleStyle: `color: ${titleColor};`,
        };
    }

    function categoryTone(category) {
        const map = {
            jobs: buildTone({ borderColor: 'rgba(14, 165, 233, 0.42)', stripColor: 'rgba(56, 189, 248, 0.92)', ringColor: 'rgba(14, 165, 233, 0.14)', glowColor: 'rgba(56, 189, 248, 0.08)', pillBorderColor: 'rgba(14, 165, 233, 0.34)', pillBgColor: 'rgba(14, 165, 233, 0.12)', pillTextColor: '#d8f2ff', titleColor: '#d8f2ff' }),
            bounties: buildTone({ borderColor: 'rgba(245, 158, 11, 0.42)', stripColor: 'rgba(251, 191, 36, 0.92)', ringColor: 'rgba(245, 158, 11, 0.14)', glowColor: 'rgba(245, 158, 11, 0.10)', pillBorderColor: 'rgba(245, 158, 11, 0.34)', pillBgColor: 'rgba(245, 158, 11, 0.12)', pillTextColor: '#ffefbf', titleColor: '#ffefbf' }),
            wanted: buildTone({ borderColor: 'rgba(239, 68, 68, 0.44)', stripColor: 'rgba(248, 113, 113, 0.94)', ringColor: 'rgba(239, 68, 68, 0.16)', glowColor: 'rgba(248, 113, 113, 0.10)', pillBorderColor: 'rgba(239, 68, 68, 0.36)', pillBgColor: 'rgba(239, 68, 68, 0.12)', pillTextColor: '#ffd8d8', titleColor: '#ffd8d8' }),
            services: buildTone({ borderColor: 'rgba(16, 185, 129, 0.42)', stripColor: 'rgba(52, 211, 153, 0.92)', ringColor: 'rgba(16, 185, 129, 0.14)', glowColor: 'rgba(52, 211, 153, 0.08)', pillBorderColor: 'rgba(16, 185, 129, 0.34)', pillBgColor: 'rgba(16, 185, 129, 0.12)', pillTextColor: '#d8fff0', titleColor: '#d8fff0' }),
            for_sale: buildTone({ borderColor: 'rgba(217, 70, 239, 0.42)', stripColor: 'rgba(232, 121, 249, 0.92)', ringColor: 'rgba(217, 70, 239, 0.14)', glowColor: 'rgba(232, 121, 249, 0.08)', pillBorderColor: 'rgba(217, 70, 239, 0.34)', pillBgColor: 'rgba(217, 70, 239, 0.12)', pillTextColor: '#ffe0ff', titleColor: '#ffe0ff' }),
            events: buildTone({ borderColor: 'rgba(139, 92, 246, 0.42)', stripColor: 'rgba(167, 139, 250, 0.92)', ringColor: 'rgba(139, 92, 246, 0.14)', glowColor: 'rgba(167, 139, 250, 0.08)', pillBorderColor: 'rgba(139, 92, 246, 0.34)', pillBgColor: 'rgba(139, 92, 246, 0.12)', pillTextColor: '#e8dcff', titleColor: '#e8dcff' }),
            rumors: buildTone({ borderColor: 'rgba(251, 146, 60, 0.42)', stripColor: 'rgba(253, 186, 116, 0.92)', ringColor: 'rgba(251, 146, 60, 0.14)', glowColor: 'rgba(253, 186, 116, 0.08)', pillBorderColor: 'rgba(251, 146, 60, 0.34)', pillBgColor: 'rgba(251, 146, 60, 0.12)', pillTextColor: '#ffe5cc', titleColor: '#ffe5cc' }),
            other: buildTone({ borderColor: 'rgba(120, 113, 108, 0.42)', stripColor: 'rgba(168, 162, 158, 0.92)', ringColor: 'rgba(120, 113, 108, 0.14)', glowColor: 'rgba(168, 162, 158, 0.08)', pillBorderColor: 'rgba(120, 113, 108, 0.34)', pillBgColor: 'rgba(120, 113, 108, 0.12)', pillTextColor: '#ede4da', titleColor: '#ede4da' }),
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

    function effectiveNoticeTone(notice) {
        return accentTone(notice.accent_color) || categoryTone(notice.category);
    }

    function normalizeSearchTerm(value) {
        return String(value || '').trim().toLowerCase();
    }

    function noticeMatchesSearch(notice) {
        const term = normalizeSearchTerm(state.searchTerm);
        if (!term) return true;
        return String(notice.search_text || '').toLowerCase().includes(term);
    }

    function visibleNotices() {
        return state.notices.filter((notice) => {
            if (notice.status === 'archived' && !state.showArchived) return false;
            if (state.selectedCategory && notice.category !== state.selectedCategory) return false;
            if (!state.selectedStatuses.has(notice.status)) return false;
            return noticeMatchesSearch(notice);
        });
    }

    function setDefaultStatuses() {
        state.selectedStatuses = state.showArchived
            ? new Set(['active', 'closed', 'archived'])
            : new Set(['active', 'closed']);
    }

    function openForm(mode, notice = null) {
        state.mode = 'form';
        state.formMode = mode;
        state.selectedNoticeId = notice ? notice.id : null;
        render();
    }

    function closeForm() {
        state.mode = 'view';
        render();
    }

    function renderCategories() {
        const categories = [{ key: null, label: 'All Notices', icon: '📋' }, ...state.categories];

        categoryListEl.innerHTML = categories.map((category) => {
            const active = category.key === state.selectedCategory;
            const count = state.notices.filter((notice) => {
                if (notice.status === 'archived' && !state.showArchived) return false;
                return category.key === null || notice.category === category.key;
            }).length;

            return `
                <button type="button" data-notice-category="${category.key ?? ''}" class="${active ? 'w-full rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-left text-[11px] font-semibold text-amber-100' : 'w-full rounded border border-[#332817] bg-[#101012] px-3 py-2 text-left text-[11px] text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]'}">
                    <span class="flex items-center gap-2"><span>${escapeHtml(category.icon || '')}</span><span>${escapeHtml(category.label)}</span></span>
                    <span class="mt-0.5 block text-[10px] text-[#8f8675]">${count} notice${count === 1 ? '' : 's'}</span>
                </button>
            `;
        }).join('');

        categoryListEl.querySelectorAll('[data-notice-category]').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedCategory = button.dataset.noticeCategory || null;
                if (state.selectedCategory === '') state.selectedCategory = null;
                render();
            });
        });
    }

    function renderStatuses() {
        statusListEl.innerHTML = state.statuses.map((status) => {
            const active = state.selectedStatuses.has(status.key);
            const count = state.notices.filter((notice) => notice.status === status.key).length;

            return `
                <button type="button" data-notice-status="${status.key}" class="${active ? 'w-full rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-left text-[11px] font-semibold text-amber-100' : 'w-full rounded border border-[#332817] bg-[#101012] px-3 py-2 text-left text-[11px] text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]'}">
                    <span>${escapeHtml(status.label)}</span>
                    <span class="mt-0.5 block text-[10px] text-[#8f8675]">${count} notice${count === 1 ? '' : 's'}</span>
                </button>
            `;
        }).join('');

        statusListEl.querySelectorAll('[data-notice-status]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.dataset.noticeStatus;
                if (state.selectedStatuses.has(key)) {
                    if (state.selectedStatuses.size === 1) return;
                    state.selectedStatuses.delete(key);
                } else {
                    state.selectedStatuses.add(key);
                }
                render();
            });
        });
    }

    function renderCardList() {
        const items = visibleNotices();

        if (items.length === 0) {
            cardListEl.innerHTML = '<div class="col-span-full rounded border border-dashed border-[#332817] bg-[#101012] px-4 py-6 text-[11px] text-[#8f8675]">No notices match the current filters.</div>';
            return;
        }

        cardListEl.innerHTML = items.map((notice) => {
            const tone = effectiveNoticeTone(notice);
            const statusClass = notice.status === 'closed'
                ? 'border-slate-500/30 bg-slate-500/10 text-slate-200'
                : (notice.status === 'archived'
                    ? 'border-[#4b4437] bg-[#181513] text-[#b9ab91]'
                    : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200');
            const preview = String(notice.body || '');
            const cardClass = 'rounded-xl border border-l-4 p-4 text-left transition';

            return `
                <article class="${cardClass}" style="${tone.cardStyle}">
                    <div class="flex items-start justify-between gap-3">
                        <span class="rounded-full border px-2 py-1 text-[9px] uppercase tracking-[0.14em]" style="${tone.pillStyle}">${escapeHtml(notice.category_icon)} ${escapeHtml(notice.category_label)}</span>
                        ${notice.status === 'active' ? '' : `<span class="shrink-0 rounded border px-2 py-1 text-[9px] uppercase tracking-[0.14em] ${statusClass}">${escapeHtml(notice.status_label)}</span>`}
                    </div>
                    <div class="mt-3 text-base font-semibold leading-tight" style="${tone.titleStyle}">${escapeHtml(notice.title)}</div>
                    <div class="mt-2 text-[12px] leading-relaxed text-[#cbbda1] whitespace-pre-wrap">${escapeHtml(preview.slice(0, 220))}${preview.length > 220 ? '…' : ''}</div>
                    <div class="mt-4 grid gap-2 text-[11px] text-[#a89d88]">
                        ${notice.reward ? `<div><span class="text-[#7f7568]">Reward:</span> <span class="font-semibold text-amber-100">${escapeHtml(notice.reward)}</span></div>` : ''}
                        ${notice.location ? `<div><span class="text-[#7f7568]">Location:</span> ${escapeHtml(notice.location)}</div>` : ''}
                        ${notice.expires_at ? `<div><span class="text-[#7f7568]">Expires:</span> ${escapeHtml(formatDate(notice.expires_at))}</div>` : ''}
                    </div>
                    <div class="mt-4 border-t border-[#2d271f] pt-3 text-[11px] text-[#8f8675]">
                        <div class="font-semibold text-[#e8d2a0]">Posted by ${escapeHtml(notice.author_character?.name || 'Unknown')}</div>
                        <div class="mt-1 text-[11px] text-[#8f8675]">Posted ${escapeHtml(relativeDate(notice.created_at) || formatDate(notice.created_at))} <span class="px-1 text-[#6f675a]">•</span> Updated ${escapeHtml(relativeDate(notice.updated_at) || formatDate(notice.updated_at))}</div>
                    </div>
                    ${(notice.viewer_can_edit) ? `
                        <div class="mt-4 flex items-center justify-end gap-2 border-t border-[#2d271f] pt-3">
                            <button type="button" data-notice-edit="${notice.id}" class="rounded border border-amber-500/40 bg-amber-500/10 px-3 py-1.5 text-[11px] font-semibold text-amber-100 hover:bg-amber-500/20">Edit</button>
                            <button type="button" data-notice-delete="${notice.id}" class="rounded border border-red-500/40 bg-red-500/10 px-3 py-1.5 text-[11px] font-semibold text-red-200 hover:bg-red-500/20">Delete</button>
                        </div>
                    ` : ''}
                </article>
            `;
        }).join('');

        cardListEl.querySelectorAll('[data-notice-edit]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const notice = state.notices.find((item) => item.id === parseInt(button.dataset.noticeEdit || '0', 10));
                if (notice) openForm('edit', notice);
            });
        });

        cardListEl.querySelectorAll('[data-notice-delete]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.stopPropagation();
                const notice = state.notices.find((item) => item.id === parseInt(button.dataset.noticeDelete || '0', 10));
                if (!notice || !confirm(`Delete "${notice.title}"?`)) return;
                await requestJson(`/rooms/${encodeURIComponent(roomSlug)}/notice-board/${notice.id}`, { method: 'DELETE' });
                await fetchBoard();
                render();
            });
        });

    }

    function formHtml(notice) {
        const isEdit = state.formMode === 'edit' && notice;

        return `
            <form id="notice-board-form" class="space-y-4 max-w-3xl">
                <div>
                    <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#8f8675]">${isEdit ? 'Edit Notice' : 'Post Notice'}</div>
                    <div class="mt-1 text-[12px] text-[#8f8675]">Use this board to generate scenes, jobs, rumors, and activity.</div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="notice-title">Title</label>
                    <input id="notice-title" name="title" type="text" maxlength="255" required value="${escapeHtml(isEdit ? notice.title : '')}" class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="notice-category">Category</label>
                        <select id="notice-category" name="category" class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                            ${state.categories.map((category) => `<option value="${escapeHtml(category.key)}" ${isEdit && notice.category === category.key ? 'selected' : ''}>${escapeHtml(category.icon)} ${escapeHtml(category.label)}</option>`).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="notice-status">Status</label>
                        <select id="notice-status" name="status" class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500" ${isEdit ? '' : 'disabled'}>
                            ${state.statuses.map((status) => `<option value="${escapeHtml(status.key)}" ${isEdit && notice.status === status.key ? 'selected' : ''}>${escapeHtml(status.label)}</option>`).join('')}
                        </select>
                        <div class="mt-1 text-[10px] text-[#6f675a]">${isEdit ? 'Set to Active, Closed, or Archived.' : 'New notices post as Active immediately.'}</div>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="notice-accent-color">Accent Color</label>
                    <select id="notice-accent-color" name="accent_color" class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                        <option value="default">Default (Category)</option>
                        ${state.accentColors.map((accent) => `<option value="${escapeHtml(accent.key)}" ${isEdit && notice.accent_color === accent.key ? 'selected' : ''}>${escapeHtml(accent.label)}</option>`).join('')}
                    </select>
                    <div class="mt-1 text-[10px] text-[#6f675a]">Optional board accent for this notice card.</div>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="notice-body">Body</label>
                    <textarea id="notice-body" name="body" rows="10" required class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">${escapeHtml(isEdit ? notice.body : '')}</textarea>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="notice-reward">Reward</label>
                        <input id="notice-reward" name="reward" type="text" maxlength="255" value="${escapeHtml(isEdit ? (notice.reward || '') : '')}" class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="notice-location">Location</label>
                        <input id="notice-location" name="location" type="text" maxlength="255" value="${escapeHtml(isEdit ? (notice.location || '') : '')}" class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-[#d6c8ad]" for="notice-expires-at">Expiration Date</label>
                        <input id="notice-expires-at" name="expires_at" type="date" value="${isEdit && notice.expires_at ? escapeHtml(notice.expires_at.slice(0, 10)) : ''}" class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#d6c8ad] focus:border-amber-500 focus:ring-amber-500">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" id="notice-board-form-cancel" class="rounded border border-[#332817] bg-[#0b0b0c] px-3 py-1.5 text-xs text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Cancel</button>
                    <button type="submit" class="rounded border border-amber-500/40 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-500/20">${isEdit ? 'Save Changes' : 'Post Notice'}</button>
                </div>
            </form>
        `;
    }

    function renderFormPanel() {
        if (state.mode !== 'form') {
            formPanelEl.classList.add('hidden');
            formPanelEl.classList.remove('flex');
            mainShellEl.style.gridTemplateColumns = 'minmax(0, 1fr)';
            return;
        }

        const notice = state.formMode === 'edit'
            ? state.notices.find((item) => item.id === state.selectedNoticeId) || null
            : null;

        formPanelEl.classList.remove('hidden');
        formPanelEl.classList.add('flex');
        mainShellEl.style.gridTemplateColumns = 'minmax(0, 1fr) 24rem';
        formTitleEl.textContent = state.formMode === 'edit' ? 'Edit Notice' : 'Post Notice';
        formSubtitleEl.textContent = state.formMode === 'edit'
            ? 'Update the selected notice and return to the board.'
            : 'Create hooks, rumors, jobs, and opportunities.';
        formBodyEl.innerHTML = formHtml(notice);
        bindForm();
    }

    function updateStatusPill() {
        const visible = visibleNotices();
        statusPillEl.textContent = `${visible.length} visible notice${visible.length === 1 ? '' : 's'}`;
        boardSummaryEl.innerHTML = `<div class="font-semibold text-[#f2dfb5]">${visible.length}</div><div>${visible.length === 1 ? 'notice visible' : 'notices visible'}</div>`;
    }

    function render() {
        updateStatusPill();
        renderCategories();
        renderStatuses();
        renderCardList();
        renderFormPanel();
    }

    function bindForm() {
        document.getElementById('notice-board-form-cancel')?.addEventListener('click', closeForm);

        document.getElementById('notice-board-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const notice = state.notices.find((item) => item.id === state.selectedNoticeId) || null;
            const formData = new FormData(event.currentTarget);
            const payload = {
                title: String(formData.get('title') || '').trim(),
                category: String(formData.get('category') || ''),
                body: String(formData.get('body') || '').trim(),
                reward: String(formData.get('reward') || '').trim(),
                location: String(formData.get('location') || '').trim(),
                expires_at: String(formData.get('expires_at') || '').trim(),
                accent_color: String(formData.get('accent_color') || 'default').trim(),
            };

            if (state.formMode === 'edit') payload.status = String(formData.get('status') || 'active');

            const response = state.formMode === 'edit' && notice
                ? await requestJson(`/rooms/${encodeURIComponent(roomSlug)}/notice-board/${notice.id}`, { method: 'PATCH', body: JSON.stringify(payload) })
                : await requestJson(`/rooms/${encodeURIComponent(roomSlug)}/notice-board`, { method: 'POST', body: JSON.stringify(payload) });

            await fetchBoard();
            state.mode = 'view';
            if (response.notice) state.selectedNoticeId = null;
            render();
        });
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            method: options.method || 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: options.body,
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = payload.message || 'The notice board request failed.';
            alert(message);
            throw new Error(message);
        }

        return payload;
    }

    async function fetchBoard() {
        const payload = await requestJson(`/rooms/${encodeURIComponent(roomSlug)}/notice-board`);
        state.categories = payload.categories || [];
        state.statuses = payload.statuses || [];
        state.accentColors = payload.accent_colors || [];
        state.notices = payload.notices || [];
        state.permissions = payload.permissions || { can_create: false, can_manage: false };
        if (!state.permissions.can_create && state.mode === 'form') state.mode = 'view';

        newBtn.disabled = !state.permissions.can_create;
        newBtn.className = state.permissions.can_create
            ? 'w-full rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50'
            : 'w-full rounded border border-dashed border-[#332817] bg-[#101012] px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-[#6f675b] cursor-not-allowed opacity-70';
    }

    function setWindowOpenState(isOpen) {
        window.dispatchEvent(new CustomEvent('notice-board-window-state', {
            detail: { open: isOpen },
        }));
    }

    function openWindow() {
        windowEl.classList.remove('hidden');
        windowEl.style.zIndex = '60';
        setWindowOpenState(true);
        fetchBoard().then(render).catch(() => {
            formBodyEl.innerHTML = '<div class="text-red-300">Failed to load the notice board.</div>';
        });
    }

    function closeWindow() {
        windowEl.classList.add('hidden');
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
        setDefaultStatuses();
        render();
    });

    window.addEventListener('resize', clampWindowToViewport);
    window.addEventListener('open-notice-board-window', openWindow);

    bindDrag();
    bindResize();
})();
</script>
