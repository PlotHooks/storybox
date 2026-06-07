@php
    $charactersPanelAvailable = request()->routeIs('rooms.*');
    $charactersPanelOpen = $charactersPanelAvailable && request()->query('characters') === '1';
    $characters = auth()->user()?->characters()->orderBy('name')->get() ?? collect();
    $activeId = session('active_character_id');
@endphp

@if ($charactersPanelAvailable)
    <div
        id="characters-window"
        class="{{ $charactersPanelOpen ? 'flex min-h-0 flex-col' : 'hidden' }} fixed z-[10020] overflow-hidden rounded-md border border-[#2a241a] bg-[#0b0b0c] shadow-2xl ring-1 ring-amber-500/10"
        style="width: min(960px, calc(100vw - 3rem)); max-width: calc(100vw - 2rem); height: min(78dvh, calc(100dvh - 2rem)); max-height: calc(100dvh - 2rem); top: 96px; left: calc(50vw - min(960px, calc(100vw - 3rem)) / 2 + 24px);"
    >
        <div
            id="characters-drag-handle"
            class="flex shrink-0 cursor-move items-center justify-between border-b border-[#2a241a] bg-[#101012] px-4 py-3 sm:flex"
        >
            <div>
                <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-400">Chat Tools</div>
                <div class="text-sm font-semibold text-[#f2dfb5]">Characters</div>
            </div>
            <div class="flex gap-2">
                <a
                    href="{{ route('characters.index') }}"
                    class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-sm text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]"
                    title="Open full page"
                >
                    ↗
                </a>
                <button
                    type="button"
                    data-close-characters-panel
                    class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-sm text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5]"
                    title="Close"
                >
                    ✕
                </button>
            </div>
        </div>

        <div class="min-h-0 min-w-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-contain px-4 py-4 text-[#d6c8ad]">
            @include('characters._manager', ['panelMode' => true, 'characters' => $characters, 'activeId' => $activeId])
        </div>

        <div
            id="characters-resize"
            class="absolute bottom-0 right-0 hidden h-4 w-4 cursor-se-resize sm:block"
            aria-hidden="true"
        >
            <svg viewBox="0 0 16 16" class="h-4 w-4 text-[#8f8675]">
                <path d="M5 15L15 5M9 15l6-6M13 15l2-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </div>
    </div>

    <script>
        (() => {
            const charactersWindow = document.getElementById('characters-window');
            const dragHandle = document.getElementById('characters-drag-handle');
            const resizeHandle = document.getElementById('characters-resize');
            if (!charactersWindow || !dragHandle || !resizeHandle) return;

            const openButtons = document.querySelectorAll('[data-open-characters-panel]');
            const closeButtons = document.querySelectorAll('[data-close-characters-panel]');

            const MOBILE_INSET = 8;
            const DESKTOP_INSET = 16;
            const DESKTOP_MARGIN = DESKTOP_INSET * 2;
            const DESKTOP_MIN_WIDTH = 480;
            const DESKTOP_MIN_HEIGHT = 420;
            const DESKTOP_DEFAULT_WIDTH = 960;
            const DESKTOP_DEFAULT_HEIGHT_RATIO = 0.78;

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

            const syncUrl = (open) => {
                const url = new URL(window.location.href);
                if (open) {
                    url.searchParams.set('characters', '1');
                } else {
                    url.searchParams.delete('characters');
                }
                window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
            };

            const applyMobileLayout = () => {
                if (!isMobile()) return;
                charactersWindow.style.width = 'calc(100vw - 16px)';
                charactersWindow.style.height = 'calc(100dvh - 16px)';
                charactersWindow.style.top = `${MOBILE_INSET}px`;
                charactersWindow.style.left = `${MOBILE_INSET}px`;
                charactersWindow.style.right = `${MOBILE_INSET}px`;
                charactersWindow.dataset.mobileLayout = '1';
            };

            const centerWindow = (offset = 24) => {
                if (isMobile()) {
                    applyMobileLayout();
                    return;
                }

                const bounds = getDesktopBounds();
                const width = Math.min(DESKTOP_DEFAULT_WIDTH, bounds.maxWidth);
                const height = Math.min(bounds.maxHeight, window.innerHeight * DESKTOP_DEFAULT_HEIGHT_RATIO);
                const left = clamp((window.innerWidth - width) / 2 + offset, DESKTOP_INSET, Math.max(DESKTOP_INSET, window.innerWidth - width - DESKTOP_INSET));
                const top = clamp((window.innerHeight - height) / 2 - 12, DESKTOP_INSET, Math.max(DESKTOP_INSET, window.innerHeight - height - DESKTOP_INSET));

                charactersWindow.style.width = `${width}px`;
                charactersWindow.style.height = `${height}px`;
                charactersWindow.style.left = `${left}px`;
                charactersWindow.style.top = `${top}px`;
                charactersWindow.style.right = 'auto';
                charactersWindow.dataset.positioned = '1';
                charactersWindow.dataset.mobileLayout = '0';
                delete charactersWindow.dataset.resized;
            };

            const ensureDefaultPosition = () => {
                if (charactersWindow.dataset.positioned === '1') {
                    if (isMobile()) applyMobileLayout();
                    return;
                }
                centerWindow();
            };

            const openWindow = () => {
                charactersWindow.classList.remove('hidden');
                ensureDefaultPosition();
                syncUrl(true);
            };

            const closeWindow = () => {
                charactersWindow.classList.add('hidden');
                syncUrl(false);
            };

            openButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    openWindow();
                });
            });

            closeButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    closeWindow();
                });
            });

            let isDragging = false;
            let isResizing = false;
            let offsetX = 0;
            let offsetY = 0;
            let resizeStartX = 0;
            let resizeStartY = 0;
            let resizeStartWidth = 0;
            let resizeStartHeight = 0;

            dragHandle.addEventListener('mousedown', (event) => {
                if (isMobile()) return;
                if (event.target.closest('button, a, input, textarea, select, label')) return;
                if (isResizing) return;

                isDragging = true;
                offsetX = event.clientX - charactersWindow.offsetLeft;
                offsetY = event.clientY - charactersWindow.offsetTop;
                document.body.style.userSelect = 'none';
            });

            resizeHandle.addEventListener('mousedown', (event) => {
                if (isMobile()) return;

                event.preventDefault();
                event.stopPropagation();
                isResizing = true;
                resizeStartX = event.clientX;
                resizeStartY = event.clientY;
                resizeStartWidth = charactersWindow.offsetWidth;
                resizeStartHeight = charactersWindow.offsetHeight;
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
                    const maxWidth = Math.min(bounds.maxWidth, window.innerWidth - charactersWindow.offsetLeft - DESKTOP_INSET);
                    const maxHeight = Math.min(bounds.maxHeight, window.innerHeight - charactersWindow.offsetTop - DESKTOP_INSET);
                    const nextWidth = clamp(resizeStartWidth + (event.clientX - resizeStartX), bounds.minWidth, Math.max(bounds.minWidth, maxWidth));
                    const nextHeight = clamp(resizeStartHeight + (event.clientY - resizeStartY), bounds.minHeight, Math.max(bounds.minHeight, maxHeight));

                    charactersWindow.style.width = `${nextWidth}px`;
                    charactersWindow.style.height = `${nextHeight}px`;
                    charactersWindow.dataset.positioned = '1';
                    charactersWindow.dataset.resized = '1';
                    charactersWindow.dataset.mobileLayout = '0';
                    return;
                }

                if (!isDragging || isMobile()) return;

                const nextLeft = Math.min(
                    Math.max(DESKTOP_INSET, event.clientX - offsetX),
                    Math.max(DESKTOP_INSET, window.innerWidth - charactersWindow.offsetWidth - DESKTOP_INSET)
                );
                const nextTop = Math.min(
                    Math.max(DESKTOP_INSET, event.clientY - offsetY),
                    Math.max(DESKTOP_INSET, window.innerHeight - charactersWindow.offsetHeight - DESKTOP_INSET)
                );

                charactersWindow.style.left = `${nextLeft}px`;
                charactersWindow.style.top = `${nextTop}px`;
                charactersWindow.style.right = 'auto';
                charactersWindow.dataset.positioned = '1';
            });

            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !charactersWindow.classList.contains('hidden')) {
                    closeWindow();
                }
            });

            window.addEventListener('resize', () => {
                if (charactersWindow.classList.contains('hidden')) return;
                if (isMobile()) {
                    applyMobileLayout();
                    return;
                }

                if (charactersWindow.dataset.mobileLayout === '1') {
                    centerWindow();
                    return;
                }

                const bounds = getDesktopBounds();
                const left = parseFloat(charactersWindow.style.left || `${DESKTOP_INSET}`);
                const top = parseFloat(charactersWindow.style.top || `${DESKTOP_INSET}`);
                const currentWidth = parseFloat(charactersWindow.style.width || `${bounds.maxWidth}`);
                const currentHeight = parseFloat(charactersWindow.style.height || `${Math.min(bounds.maxHeight, window.innerHeight * DESKTOP_DEFAULT_HEIGHT_RATIO)}`);
                const width = charactersWindow.dataset.resized === '1'
                    ? clamp(currentWidth, bounds.minWidth, bounds.maxWidth)
                    : Math.min(DESKTOP_DEFAULT_WIDTH, bounds.maxWidth);
                const height = charactersWindow.dataset.resized === '1'
                    ? clamp(currentHeight, bounds.minHeight, bounds.maxHeight)
                    : Math.min(bounds.maxHeight, window.innerHeight * DESKTOP_DEFAULT_HEIGHT_RATIO);

                charactersWindow.style.width = `${width}px`;
                charactersWindow.style.height = `${height}px`;
                charactersWindow.style.left = `${clamp(left, DESKTOP_INSET, Math.max(DESKTOP_INSET, window.innerWidth - width - DESKTOP_INSET))}px`;
                charactersWindow.style.top = `${clamp(top, DESKTOP_INSET, Math.max(DESKTOP_INSET, window.innerHeight - height - DESKTOP_INSET))}px`;
                charactersWindow.style.right = 'auto';
                charactersWindow.dataset.mobileLayout = '0';
            });

            if (!charactersWindow.classList.contains('hidden')) {
                ensureDefaultPosition();
            }
        })();
    </script>
@endif
