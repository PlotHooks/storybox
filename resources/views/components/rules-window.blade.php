@props([
    'room',
])

<div
    id="rules-window"
    class="hidden fixed z-50 bg-[#0b0b0c] border rounded-md shadow-[0_28px_72px_rgba(0,0,0,0.62)] flex flex-col overflow-hidden ring-1 ring-amber-500/10"
    style="width: min(1280px, calc(100vw - 48px)); height: min(760px, calc(100vh - 48px)); left: 28px; top: 28px; border-width: 4px; border-color: #3a2d1b;"
>
    <div id="rules-drag-handle" class="cursor-move flex items-center justify-between px-3 py-2 border-b border-[#3a2d1b] bg-[#111114] shadow-[inset_0_-1px_0_rgba(245,158,11,0.04)]">
        <div>
            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-400">Room Governance</div>
            <div class="text-sm text-[#f2dfb5] font-semibold">Rules</div>
        </div>
        <div class="flex items-center gap-2">
            <button id="rules-refresh-btn" type="button" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm" title="Refresh">↻</button>
            <button id="rules-close-btn" type="button" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm" title="Close">✕</button>
        </div>
    </div>

    <div id="rules-main-shell" class="flex-1 min-h-0 grid overflow-hidden" style="grid-template-columns: minmax(0, 1fr);">
        <div class="min-w-0 flex flex-col overflow-hidden bg-[#090909]">
            <div class="border-b border-[#2a241a] bg-[linear-gradient(135deg,rgba(245,158,11,0.10),transparent_48%),linear-gradient(180deg,#101012,#0d0d0f)] px-5 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-400">Official Document</div>
                        <div class="mt-1 text-lg font-semibold text-[#f2dfb5]">Room Rules</div>
                        <div class="mt-1 text-[12px] text-[#8f8675]">The standing rules that govern this room.</div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <div id="rules-status-pill" class="rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-right text-[11px] text-[#8f8675]">Loading</div>
                        <button id="rules-new-btn" type="button" class="rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50">+ Add Rule</button>
                    </div>
                </div>
            </div>

            <div id="rules-document-body" class="min-h-0 flex-1 overflow-y-auto bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.04),transparent_24rem)] p-5 text-sm text-[#d6c8ad]">
                <div class="text-[#8f8675]">Loading...</div>
            </div>
        </div>

        <div id="rules-form-panel" class="hidden min-w-0 flex-col overflow-hidden border-l border-[#332817] bg-[#080809]">
            <div class="px-4 py-3 border-b border-[#332817] bg-[#101012] flex items-center justify-between gap-3 shadow-[inset_0_-1px_0_rgba(245,158,11,0.03)]">
                <div class="min-w-0">
                    <div id="rules-form-title" class="truncate text-sm font-semibold text-[#f2dfb5]">Add Rule</div>
                    <div id="rules-form-subtitle" class="mt-1 text-[11px] text-[#8f8675]">Create an official room rule.</div>
                </div>
                <button id="rules-form-close-btn" type="button" class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5] text-sm">✕</button>
            </div>
            <div id="rules-form-body" class="flex-1 min-h-0 overflow-y-auto bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.03),transparent_24rem)] p-5 text-sm text-[#d6c8ad]"></div>
        </div>
    </div>

    <div id="rules-resize-handle" class="absolute bottom-0 right-0 h-4 w-4 cursor-se-resize" title="Resize"></div>
</div>

<script>
(function () {
    const windowEl = document.getElementById('rules-window');
    if (!windowEl) return;

    const roomSlug = @json($room->slug);
    const csrf = @json(csrf_token());
    const documentBodyEl = document.getElementById('rules-document-body');
    const statusPillEl = document.getElementById('rules-status-pill');
    const refreshBtn = document.getElementById('rules-refresh-btn');
    const closeBtn = document.getElementById('rules-close-btn');
    const newBtn = document.getElementById('rules-new-btn');
    const formPanelEl = document.getElementById('rules-form-panel');
    const formTitleEl = document.getElementById('rules-form-title');
    const formSubtitleEl = document.getElementById('rules-form-subtitle');
    const formBodyEl = document.getElementById('rules-form-body');
    const formCloseBtn = document.getElementById('rules-form-close-btn');
    const mainShellEl = document.getElementById('rules-main-shell');
    const dragHandle = document.getElementById('rules-drag-handle');
    const resizeHandle = document.getElementById('rules-resize-handle');

    const WINDOW_MIN_WIDTH = 920;
    const WINDOW_MIN_HEIGHT = 540;
    const WINDOW_MAX_WIDTH = 1440;
    const WINDOW_MAX_HEIGHT = 920;
    const VIEWPORT_PADDING = 16;

    const state = {
        rules: [],
        permissions: {
            can_create: false,
            can_manage: false,
        },
        mode: 'view',
        formMode: 'create',
        selectedRuleId: null,
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function currentRule() {
        return state.rules.find((rule) => rule.id === state.selectedRuleId) || null;
    }

    function setWindowOpenState(isOpen) {
        window.dispatchEvent(new CustomEvent('rules-window-state', {
            detail: { open: isOpen },
        }));
    }

    function setStatus() {
        if (state.rules.length === 0) {
            statusPillEl.textContent = state.permissions.can_manage ? 'No rules yet' : 'No published rules';
            return;
        }

        statusPillEl.textContent = `${state.rules.length} rule${state.rules.length === 1 ? '' : 's'}`;
    }

    function syncAddButton() {
        newBtn.disabled = !state.permissions.can_create;
        newBtn.className = state.permissions.can_create
            ? 'rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-100 hover:bg-amber-500/20 focus:outline-none focus:ring-2 focus:ring-amber-500/50'
            : 'rounded border border-dashed border-[#4b5563] bg-[#11161d] px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 cursor-not-allowed opacity-70';
    }

    function ruleControlsHtml(rule) {
        if (!rule.viewer_can_manage) {
            return '';
        }

        const moveButtonClass = 'rounded border px-1.5 py-0.5 text-[10px] border-[#332817] bg-[#0b0b0c] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]';
        const disabledMoveButtonClass = 'rounded border px-1.5 py-0.5 text-[10px] border-[#2a241a] bg-[#0b0b0c] text-[#5d5549] cursor-not-allowed opacity-60';
        const actionButtonClass = 'rounded border border-[#3b3123] bg-[#111113] px-2 py-1 text-[10px] font-semibold text-[#bca883] hover:border-amber-500/40 hover:text-[#f2dfb5]';
        const deleteButtonClass = 'rounded border border-[#4c231f] bg-[#191011] px-2 py-1 text-[10px] font-semibold text-[#e0b1ab] hover:border-red-400/40 hover:text-[#ffd8d4]';

        return `
            <div class="flex flex-wrap items-center gap-1 text-[10px] uppercase tracking-[0.1em] text-[#8f8675]">
                <button type="button" title="Move up" aria-label="Move rule up" data-rule-action="move-up" data-rule-id="${rule.id}" class="${rule.can_move_up ? moveButtonClass : disabledMoveButtonClass}" ${rule.can_move_up ? '' : 'disabled'}>↑</button>
                <button type="button" title="Move down" aria-label="Move rule down" data-rule-action="move-down" data-rule-id="${rule.id}" class="${rule.can_move_down ? moveButtonClass : disabledMoveButtonClass}" ${rule.can_move_down ? '' : 'disabled'}>↓</button>
                <button type="button" data-rule-action="edit" data-rule-id="${rule.id}" class="${actionButtonClass}">Edit</button>
                <button type="button" data-rule-action="delete" data-rule-id="${rule.id}" class="${deleteButtonClass}">Delete</button>
            </div>
        `;
    }

    function renderDocument() {
        setStatus();
        syncAddButton();

        if (state.rules.length === 0) {
            documentBodyEl.innerHTML = state.permissions.can_manage
                ? '<div class="mx-auto max-w-4xl px-2 py-8 text-center text-[#8f8675]">No rules have been posted yet. Add the first rule to establish room governance.</div>'
                : '<div class="mx-auto max-w-4xl px-2 py-8 text-center text-[#8f8675]">This room has not published any rules yet.</div>';
            return;
        }

        documentBodyEl.innerHTML = `
            <div class="mx-auto max-w-4xl space-y-5 px-2 sm:px-3">
                ${state.rules.map((rule, index) => `
                    <article class="pb-5 ${index === state.rules.length - 1 ? '' : 'border-b border-[#3a2f1e]'}">
                        <div class="flex items-start gap-4">
                            <div class="pt-0.5 text-lg font-semibold text-[#d0ae68] sm:text-xl">${index + 1}.</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="min-w-0 flex-1 text-xl font-semibold text-[#f7e8c1]">${escapeHtml(rule.title)}</h3>
                                    <div class="shrink-0 opacity-80">${ruleControlsHtml(rule)}</div>
                                </div>
                                <div class="mt-3 whitespace-pre-line text-[15px] leading-7 text-[#dbcdb2] sm:text-base sm:leading-8">${escapeHtml(rule.body)}</div>
                            </div>
                        </div>
                    </article>
                `).join('')}
            </div>
        `;

        documentBodyEl.querySelectorAll('[data-rule-action]').forEach((button) => {
            button.addEventListener('click', async () => {
                const ruleId = Number(button.dataset.ruleId || 0);
                const action = button.dataset.ruleAction;
                const rule = state.rules.find((item) => item.id === ruleId) || null;
                if (!rule) return;

                if (action === 'edit') {
                    openForm('edit', rule);
                    return;
                }

                if (action === 'delete') {
                    if (!window.confirm(`Delete rule \"${rule.title}\"?`)) {
                        return;
                    }

                    await requestJson(`/rooms/${encodeURIComponent(roomSlug)}/rules/${rule.id}`, {
                        method: 'DELETE',
                    });
                    await fetchRules();
                    render();
                    return;
                }

                if (action === 'move-up' || action === 'move-down') {
                    await requestJson(`/rooms/${encodeURIComponent(roomSlug)}/rules/${rule.id}/move`, {
                        method: 'POST',
                        body: JSON.stringify({ direction: action === 'move-up' ? 'up' : 'down' }),
                    });
                    await fetchRules();
                    render();
                }
            });
        });
    }

    function renderForm() {
        if (state.mode !== 'form' || !state.permissions.can_manage) {
            formPanelEl.classList.add('hidden');
            formPanelEl.classList.remove('flex');
            mainShellEl.style.gridTemplateColumns = 'minmax(0, 1fr)';
            return;
        }

        const rule = currentRule();
        const isEdit = state.formMode === 'edit' && rule;

        formPanelEl.classList.remove('hidden');
        formPanelEl.classList.add('flex');
        mainShellEl.style.gridTemplateColumns = 'minmax(0, 1fr) 26rem';
        formTitleEl.textContent = isEdit ? 'Edit Rule' : 'Add Rule';
        formSubtitleEl.textContent = isEdit ? 'Update this official room rule.' : 'Create an official room rule.';
        formBodyEl.innerHTML = `
            <form id="rules-editor-form" class="space-y-4">
                <div>
                    <label for="rules-title-input" class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">Title</label>
                    <input id="rules-title-input" name="title" type="text" maxlength="255" value="${escapeHtml(rule?.title || '')}" class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#f2dfb5] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500">
                </div>
                <div>
                    <label for="rules-body-input" class="block text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">Body</label>
                    <textarea id="rules-body-input" name="body" rows="12" maxlength="20000" class="mt-1 block w-full rounded border border-[#332817] bg-[#0b0b0c] px-3 py-2 text-sm text-[#f2dfb5] placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500">${escapeHtml(rule?.body || '')}</textarea>
                </div>
                <div class="flex items-center justify-between gap-3 pt-2">
                    <button type="button" id="rules-editor-cancel" class="rounded border border-[#332817] bg-[#111113] px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675] hover:border-amber-500/40 hover:text-[#f2dfb5]">Cancel</button>
                    <button type="submit" class="rounded border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-100 hover:bg-amber-500/20">${isEdit ? 'Save Rule' : 'Create Rule'}</button>
                </div>
            </form>
        `;

        document.getElementById('rules-editor-cancel')?.addEventListener('click', closeForm);
        document.getElementById('rules-editor-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const formData = new FormData(form);
            const payload = {
                title: String(formData.get('title') || ''),
                body: String(formData.get('body') || ''),
            };
            const endpoint = isEdit
                ? `/rooms/${encodeURIComponent(roomSlug)}/rules/${rule.id}`
                : `/rooms/${encodeURIComponent(roomSlug)}/rules`;

            await requestJson(endpoint, {
                method: isEdit ? 'PATCH' : 'POST',
                body: JSON.stringify(payload),
            });
            await fetchRules();
            closeForm();
            render();
        });
    }

    function render() {
        renderDocument();
        renderForm();
    }

    function openForm(mode, rule = null) {
        if (!state.permissions.can_manage) {
            return;
        }

        state.mode = 'form';
        state.formMode = mode;
        state.selectedRuleId = rule ? rule.id : null;
        render();
    }

    function closeForm() {
        state.mode = 'view';
        state.formMode = 'create';
        state.selectedRuleId = null;
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
            const errors = payload.errors && typeof payload.errors === 'object'
                ? Object.values(payload.errors).flat().join('\n')
                : null;
            const message = errors || payload.message || 'The rules request failed.';
            alert(message);
            throw new Error(message);
        }

        return payload;
    }

    async function fetchRules() {
        const payload = await requestJson(`/rooms/${encodeURIComponent(roomSlug)}/rules`);
        state.rules = payload.rules || [];
        state.permissions = payload.permissions || { can_create: false, can_manage: false };
        if (state.selectedRuleId && !state.rules.some((rule) => rule.id === state.selectedRuleId)) {
            state.selectedRuleId = null;
            state.mode = 'view';
        }
    }

    function openWindow() {
        windowEl.classList.remove('hidden');
        windowEl.style.zIndex = '60';
        setWindowOpenState(true);
        fetchRules().then(render).catch(() => {
            documentBodyEl.innerHTML = '<div class="text-red-300">Failed to load rules.</div>';
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
        dragHandle?.addEventListener('mousedown', (event) => {
            dragState = { startX: event.clientX, startY: event.clientY, startLeft: windowEl.offsetLeft, startTop: windowEl.offsetTop };
            event.preventDefault();
        });
        window.addEventListener('mousemove', (event) => {
            if (!dragState) return;
            windowEl.style.left = `${dragState.startLeft + (event.clientX - dragState.startX)}px`;
            windowEl.style.top = `${dragState.startTop + (event.clientY - dragState.startY)}px`;
            clampWindowToViewport();
        });
        window.addEventListener('mouseup', () => {
            dragState = null;
        });
    }

    function bindResize() {
        let resizeState = null;
        resizeHandle?.addEventListener('mousedown', (event) => {
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
        window.addEventListener('mouseup', () => {
            resizeState = null;
        });
    }

    refreshBtn.addEventListener('click', async () => {
        await fetchRules();
        render();
    });
    closeBtn.addEventListener('click', closeWindow);
    formCloseBtn.addEventListener('click', closeForm);
    newBtn.addEventListener('click', () => openForm('create'));

    window.addEventListener('resize', clampWindowToViewport);
    window.addEventListener('open-rules-window', openWindow);

    bindDrag();
    bindResize();
})();
</script>
