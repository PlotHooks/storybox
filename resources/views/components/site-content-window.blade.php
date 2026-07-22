<div
    id="site-content-window"
    class="hidden fixed z-[10010] min-h-0 flex-col overflow-hidden rounded-md border border-[#3a2d1b] bg-[#0b0b0c] shadow-[0_28px_72px_rgba(0,0,0,0.62)] ring-1 ring-amber-500/10"
    style="width: min(1280px, calc(100vw - 48px)); max-width: calc(100vw - 32px); height: min(760px, calc(100vh - 48px)); max-height: calc(100vh - 32px); top: 24px; left: 24px; border-width: 4px;"
>
    <div
        id="site-content-drag-handle"
        class="flex shrink-0 cursor-move items-center justify-between border-b border-[#3a2d1b] bg-[#111114] px-3 py-2 shadow-[inset_0_-1px_0_rgba(245,158,11,0.04)]"
    >
        <div id="site-content-window-title" class="text-sm font-semibold text-[#f2dfb5]">Rules / FAQ</div>
        <div class="flex items-center gap-2">
            <button
                id="site-content-refresh-btn"
                type="button"
                class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-sm text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]"
                title="Refresh"
            >
                ↻
            </button>
            <button
                id="site-content-close-btn"
                type="button"
                class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-sm text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]"
                title="Close"
            >
                ✕
            </button>
        </div>
    </div>

    <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-[#090909]">
        <div class="border-b border-[#2a241a] bg-[linear-gradient(135deg,rgba(245,158,11,0.10),transparent_48%),linear-gradient(180deg,#101012,#0d0d0f)] px-5 py-4">
            <div class="flex flex-col gap-3">
                <div class="min-w-0">
                    <div id="site-content-document-title" class="text-lg font-semibold text-[#f2dfb5]">Rules / FAQ</div>
                    <div id="site-content-document-subtitle" class="mt-1 text-[12px] text-[#8f8675]">Global StoryBox documents available from every room.</div>
                </div>
                <div id="site-content-tabs" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div id="site-content-body" class="min-h-0 flex-1 overflow-y-auto bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.04),transparent_24rem)] px-5 py-4 text-sm text-[#d6c8ad]">
            <div class="text-[#8f8675]">Loading...</div>
        </div>
    </div>

    <div id="site-content-resize" class="absolute bottom-0 right-0 hidden h-4 w-4 cursor-se-resize sm:block" aria-hidden="true">
        <svg viewBox="0 0 16 16" class="h-4 w-4 text-[#8f8675]">
            <path d="M5 15L15 5M9 15l6-6M13 15l2-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
    </div>
</div>

<script>
(() => {
    const windowEl = document.getElementById('site-content-window');
    if (!windowEl) return;

    const titleEl = document.getElementById('site-content-window-title');
    const documentTitleEl = document.getElementById('site-content-document-title');
    const documentSubtitleEl = document.getElementById('site-content-document-subtitle');
    const tabsEl = document.getElementById('site-content-tabs');
    const bodyEl = document.getElementById('site-content-body');
    const refreshBtn = document.getElementById('site-content-refresh-btn');
    const closeBtn = document.getElementById('site-content-close-btn');
    const dragHandle = document.getElementById('site-content-drag-handle');
    const resizeHandle = document.getElementById('site-content-resize');
    const globalSiteContentButtons = Array.from(document.querySelectorAll('[data-global-site-content-button]'));
    const disableChatPolling = @json(config('app.disable_chat_polling'));

    const MOBILE_INSET = 8;
    const DESKTOP_INSET = 16;
    const DESKTOP_MARGIN = DESKTOP_INSET * 2;
    const DESKTOP_MIN_WIDTH = 720;
    const DESKTOP_MIN_HEIGHT = 420;
    const DESKTOP_DEFAULT_WIDTH = 1080;
    const DESKTOP_DEFAULT_HEIGHT = 720;
    const RULES_FAQ_COLLECTION = 'rules-faq';
    const SITE_CONTENT_LAST_SEEN_KEY_PREFIX = 'storybox_site_content_last_seen_';

    const state = {
        collection: 'rules-faq',
        title: 'Rules / FAQ',
        categories: [],
        selectedCategoryKey: null,
        pendingDocumentSlug: null,
        lastRequestCollection: null,
    };

    const isMobile = () => window.innerWidth < 640;
    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
    const getDesktopBounds = () => {
        const maxWidth = Math.max(320, window.innerWidth - DESKTOP_MARGIN);
        const maxHeight = Math.max(320, window.innerHeight - DESKTOP_MARGIN);

        return {
            minWidth: Math.min(DESKTOP_MIN_WIDTH, maxWidth),
            minHeight: Math.min(DESKTOP_MIN_HEIGHT, maxHeight),
            maxWidth,
            maxHeight,
        };
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isOpen() {
        return !windowEl.classList.contains('hidden');
    }

    function parseTimestamp(value) {
        const timestamp = Date.parse(value || '');
        return Number.isFinite(timestamp) ? timestamp : 0;
    }

    function latestUpdatedAtForCategories(categories) {
        return (Array.isArray(categories) ? categories : []).reduce((latest, category) => {
            const documents = Array.isArray(category?.documents) ? category.documents : [];

            return documents.reduce((categoryLatest, document) => {
                return Math.max(categoryLatest, parseTimestamp(document?.last_updated_at));
            }, latest);
        }, 0);
    }

    function syncGlobalSiteContentButtons(hasUpdates) {
        globalSiteContentButtons.forEach((button) => {
            button.classList.toggle('global-header-update-glow', hasUpdates);
        });
    }

    function lastSeenStorageKey(collection) {
        return `${SITE_CONTENT_LAST_SEEN_KEY_PREFIX}${collection}`;
    }

    function readLastSeenAt(collection) {
        try {
            return parseTimestamp(window.localStorage.getItem(lastSeenStorageKey(collection)) || '');
        } catch (error) {
            return 0;
        }
    }

    function writeLastSeenAt(collection, timestamp) {
        if (!timestamp) return;

        try {
            window.localStorage.setItem(lastSeenStorageKey(collection), new Date(timestamp).toISOString());
        } catch (error) {
            // Ignore storage failures and continue without persistence.
        }
    }

    function syncRulesFaqIndicator(latestUpdatedAt) {
        if (!globalSiteContentButtons.length) return;

        const lastSeenAt = readLastSeenAt(RULES_FAQ_COLLECTION);
        const hasUpdates = latestUpdatedAt > 0 && (lastSeenAt === 0 || latestUpdatedAt > lastSeenAt);
        syncGlobalSiteContentButtons(hasUpdates);
    }

    function markCollectionSeen(collection, latestUpdatedAt) {
        if (collection !== RULES_FAQ_COLLECTION) return;

        if (!latestUpdatedAt) {
            syncGlobalSiteContentButtons(false);
            return;
        }

        writeLastSeenAt(collection, latestUpdatedAt);
        syncRulesFaqIndicator(latestUpdatedAt);
    }

    async function refreshRulesFaqIndicator() {
        if (!globalSiteContentButtons.length) return;

        try {
            const response = await fetch(`/site-content/${encodeURIComponent(RULES_FAQ_COLLECTION)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) return;

            syncRulesFaqIndicator(latestUpdatedAtForCategories(payload.categories));
        } catch (error) {
            // Ignore background refresh errors and preserve the last known indicator state.
        }
    }

    function currentCategory() {
        return state.categories.find((category) => category.key === state.selectedCategoryKey) || state.categories[0] || null;
    }

    function findCategoryForSlug(slug) {
        return state.categories.find((category) => Array.isArray(category.documents) && category.documents.some((document) => document.slug === slug)) || null;
    }

    function setWindowOpenState(open) {
        window.dispatchEvent(new CustomEvent('site-content-window-state', {
            detail: {
                open,
                collection: state.collection,
            },
        }));
    }

    function applyMobileLayout() {
        if (!isMobile()) return;
        windowEl.style.width = 'calc(100vw - 16px)';
        windowEl.style.height = 'calc(100dvh - 16px)';
        windowEl.style.top = `${MOBILE_INSET}px`;
        windowEl.style.left = `${MOBILE_INSET}px`;
        windowEl.style.right = `${MOBILE_INSET}px`;
        windowEl.dataset.mobileLayout = '1';
    }

    function centerWindow(offset = 24) {
        if (isMobile()) {
            applyMobileLayout();
            return;
        }

        const bounds = getDesktopBounds();
        const width = Math.min(DESKTOP_DEFAULT_WIDTH, bounds.maxWidth);
        const height = Math.min(DESKTOP_DEFAULT_HEIGHT, bounds.maxHeight);
        const left = clamp((window.innerWidth - width) / 2 + offset, DESKTOP_INSET, Math.max(DESKTOP_INSET, window.innerWidth - width - DESKTOP_INSET));
        const top = clamp((window.innerHeight - height) / 2 - 12, DESKTOP_INSET, Math.max(DESKTOP_INSET, window.innerHeight - height - DESKTOP_INSET));

        windowEl.style.width = `${width}px`;
        windowEl.style.height = `${height}px`;
        windowEl.style.left = `${left}px`;
        windowEl.style.top = `${top}px`;
        windowEl.style.right = 'auto';
        windowEl.dataset.positioned = '1';
        windowEl.dataset.mobileLayout = '0';
        delete windowEl.dataset.resized;
    }

    function ensureDefaultPosition() {
        if (windowEl.dataset.positioned === '1') {
            if (isMobile()) {
                applyMobileLayout();
            }
            return;
        }

        centerWindow();
    }

    function renderTabs() {
        if (state.categories.length === 0) {
            tabsEl.innerHTML = '';
            return;
        }

        tabsEl.innerHTML = state.categories.map((category) => {
            const isActive = category.key === state.selectedCategoryKey;
            const classes = isActive
                ? 'rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-100 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.12)]'
                : 'rounded border border-[#332817] bg-[#141416] px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]';

            return `<button type="button" data-site-content-tab="${escapeHtml(category.key)}" class="${classes}">${escapeHtml(category.label)}</button>`;
        }).join('');

        tabsEl.querySelectorAll('[data-site-content-tab]').forEach((button) => {
            button.addEventListener('click', () => {
                state.selectedCategoryKey = button.dataset.siteContentTab;
                render();
            });
        });
    }

    function renderBody() {
        const category = currentCategory();

        titleEl.textContent = state.title;
        documentTitleEl.textContent = category ? category.label : state.title;
        documentSubtitleEl.textContent = 'Global StoryBox documents available from every room.';
        if (!category || !Array.isArray(category.documents) || category.documents.length === 0) {
            bodyEl.innerHTML = '<div class="mx-auto max-w-4xl px-2 py-8 text-center text-[#8f8675]">No published documents are available in this collection yet.</div>';
            return;
        }

        bodyEl.innerHTML = `
            <div class="mx-auto flex max-w-4xl min-h-full flex-col gap-3 px-2 sm:px-3">
                ${category.documents.map((document) => {
                    return `
                        <article class="rounded border border-[#332817] bg-[#0d0d0f] px-4 py-3 sm:px-5 sm:py-4">
                            <h3 class="text-base font-semibold text-[#f2dfb5] sm:text-lg">${escapeHtml(document.title)}</h3>
                            <div class="site-content-document-body mt-3 whitespace-pre-line text-[15px] text-[#dbcdb2] sm:text-base">${document.rendered_body_html}</div>
                        </article>
                    `;
                }).join('')}
            </div>
        `;
    }

    function render() {
        renderTabs();
        renderBody();
    }

    async function fetchDocuments() {
        const response = await fetch(`/site-content/${encodeURIComponent(state.collection)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message || 'Could not load site content.');
        }

        state.categories = Array.isArray(payload.categories) ? payload.categories : [];

        const requestedCategory = state.pendingDocumentSlug
            ? findCategoryForSlug(state.pendingDocumentSlug)
            : null;

        if (requestedCategory) {
            state.selectedCategoryKey = requestedCategory.key;
        } else if (!state.categories.some((category) => category.key === state.selectedCategoryKey)) {
            state.selectedCategoryKey = payload.default_category || state.categories[0]?.key || null;
        }

        state.pendingDocumentSlug = null;
        state.lastRequestCollection = state.collection;
    }

    async function loadAndRender() {
        bodyEl.innerHTML = '<div class="text-[#8f8675]">Loading...</div>';

        try {
            await fetchDocuments();
            render();

            if (state.collection === RULES_FAQ_COLLECTION) {
                markCollectionSeen(state.collection, latestUpdatedAtForCategories(state.categories));
            }
        } catch (error) {
            bodyEl.innerHTML = `<div class="text-red-300">${escapeHtml(error instanceof Error ? error.message : 'Failed to load site content.')}</div>`;
        }
    }

    function openWindow(detail = {}) {
        state.collection = detail.collection || 'rules-faq';
        state.title = detail.title || 'Rules / FAQ';

        if (detail.slug) {
            state.pendingDocumentSlug = detail.slug;
        } else if (state.collection !== state.lastRequestCollection) {
            state.selectedCategoryKey = null;
            state.pendingDocumentSlug = null;
        }

        windowEl.classList.remove('hidden');
        windowEl.classList.add('flex');
        ensureDefaultPosition();
        setWindowOpenState(true);
        loadAndRender();
    }

    function closeWindow() {
        windowEl.classList.add('hidden');
        windowEl.classList.remove('flex');
        setWindowOpenState(false);
    }

    refreshBtn?.addEventListener('click', () => loadAndRender());
    closeBtn?.addEventListener('click', closeWindow);

    let isDragging = false;
    let isResizing = false;
    let offsetX = 0;
    let offsetY = 0;
    let resizeStartX = 0;
    let resizeStartY = 0;
    let resizeStartWidth = 0;
    let resizeStartHeight = 0;

    dragHandle?.addEventListener('mousedown', (event) => {
        if (isMobile()) return;
        if (event.target.closest('button, a, input, textarea, select, label')) return;
        if (isResizing) return;

        isDragging = true;
        offsetX = event.clientX - windowEl.offsetLeft;
        offsetY = event.clientY - windowEl.offsetTop;
        document.body.style.userSelect = 'none';
    });

    resizeHandle?.addEventListener('mousedown', (event) => {
        if (isMobile()) return;

        event.preventDefault();
        event.stopPropagation();
        isResizing = true;
        resizeStartX = event.clientX;
        resizeStartY = event.clientY;
        resizeStartWidth = windowEl.offsetWidth;
        resizeStartHeight = windowEl.offsetHeight;
        document.body.style.userSelect = 'none';
    });

    document.addEventListener('mouseup', () => {
        isDragging = false;
        isResizing = false;
        document.body.style.userSelect = '';
    });

    document.addEventListener('mousemove', (event) => {
        if (isResizing && !isMobile()) {
            const bounds = getDesktopBounds();
            const maxWidth = Math.min(bounds.maxWidth, window.innerWidth - windowEl.offsetLeft - DESKTOP_INSET);
            const maxHeight = Math.min(bounds.maxHeight, window.innerHeight - windowEl.offsetTop - DESKTOP_INSET);
            const nextWidth = clamp(resizeStartWidth + (event.clientX - resizeStartX), bounds.minWidth, Math.max(bounds.minWidth, maxWidth));
            const nextHeight = clamp(resizeStartHeight + (event.clientY - resizeStartY), bounds.minHeight, Math.max(bounds.minHeight, maxHeight));

            windowEl.style.width = `${nextWidth}px`;
            windowEl.style.height = `${nextHeight}px`;
            windowEl.dataset.positioned = '1';
            windowEl.dataset.resized = '1';
            windowEl.dataset.mobileLayout = '0';
            return;
        }

        if (!isDragging || isMobile()) return;

        const nextLeft = Math.min(
            Math.max(DESKTOP_INSET, event.clientX - offsetX),
            Math.max(DESKTOP_INSET, window.innerWidth - windowEl.offsetWidth - DESKTOP_INSET)
        );
        const nextTop = Math.min(
            Math.max(DESKTOP_INSET, event.clientY - offsetY),
            Math.max(DESKTOP_INSET, window.innerHeight - windowEl.offsetHeight - DESKTOP_INSET)
        );

        windowEl.style.left = `${nextLeft}px`;
        windowEl.style.top = `${nextTop}px`;
        windowEl.style.right = 'auto';
        windowEl.dataset.positioned = '1';
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            closeWindow();
        }
    });

    window.addEventListener('resize', () => {
        if (!isOpen()) return;

        if (isMobile()) {
            applyMobileLayout();
            return;
        }

        if (windowEl.dataset.mobileLayout === '1') {
            centerWindow();
            return;
        }

        const bounds = getDesktopBounds();
        const left = parseFloat(windowEl.style.left || `${DESKTOP_INSET}`);
        const top = parseFloat(windowEl.style.top || `${DESKTOP_INSET}`);
        const currentWidth = parseFloat(windowEl.style.width || `${bounds.maxWidth}`);
        const currentHeight = parseFloat(windowEl.style.height || `${bounds.maxHeight}`);
        const width = windowEl.dataset.resized === '1'
            ? clamp(currentWidth, bounds.minWidth, bounds.maxWidth)
            : Math.min(DESKTOP_DEFAULT_WIDTH, bounds.maxWidth);
        const height = windowEl.dataset.resized === '1'
            ? clamp(currentHeight, bounds.minHeight, bounds.maxHeight)
            : Math.min(DESKTOP_DEFAULT_HEIGHT, bounds.maxHeight);

        windowEl.style.width = `${width}px`;
        windowEl.style.height = `${height}px`;
        windowEl.style.left = `${clamp(left, DESKTOP_INSET, Math.max(DESKTOP_INSET, window.innerWidth - width - DESKTOP_INSET))}px`;
        windowEl.style.top = `${clamp(top, DESKTOP_INSET, Math.max(DESKTOP_INSET, window.innerHeight - height - DESKTOP_INSET))}px`;
        windowEl.style.right = 'auto';
        windowEl.dataset.mobileLayout = '0';
    });

    window.addEventListener('open-site-content-window', (event) => openWindow(event.detail || {}));
})();
</script>