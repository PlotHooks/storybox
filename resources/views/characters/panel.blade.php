@php
    $charactersPanelAvailable = request()->routeIs('rooms.*');
    $charactersPanelOpen = $charactersPanelAvailable && request()->query('characters') === '1';
    $characters = auth()->user()?->characters()->orderBy('name')->get() ?? collect();
    $activeId = session('active_character_id');
@endphp

@if ($charactersPanelAvailable)
    <div
        id="characters-window"
        class="{{ $charactersPanelOpen ? 'flex flex-col' : 'hidden' }} fixed z-[10020] overflow-hidden rounded-md border border-[#2a241a] bg-[#0b0b0c] shadow-2xl ring-1 ring-amber-500/10"
        style="width: min(960px, calc(100vw - 48px)); height: min(78dvh, 760px); top: 96px; left: calc(50vw - min(960px, calc(100vw - 48px)) / 2 + 24px);"
    >
        <div
            id="characters-drag-handle"
            class="flex cursor-move items-center justify-between border-b border-[#2a241a] bg-[#101012] px-4 py-3 sm:flex"
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

        <div class="h-full overflow-y-auto px-4 py-4 text-[#d6c8ad] sm:flex-1">
            @include('characters._manager', ['panelMode' => true, 'characters' => $characters, 'activeId' => $activeId])
        </div>
    </div>

    <script>
        (() => {
            const charactersWindow = document.getElementById('characters-window');
            const dragHandle = document.getElementById('characters-drag-handle');
            if (!charactersWindow || !dragHandle) return;

            const openButtons = document.querySelectorAll('[data-open-characters-panel]');
            const closeButtons = document.querySelectorAll('[data-close-characters-panel]');

            const isMobile = () => window.innerWidth < 640;

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
                charactersWindow.style.top = '8px';
                charactersWindow.style.left = '8px';
                charactersWindow.style.right = '8px';
            };

            const centerWindow = (offset = 24) => {
                if (isMobile()) {
                    applyMobileLayout();
                    return;
                }

                const width = Math.min(960, window.innerWidth - 48);
                const height = Math.min(window.innerHeight * 0.78, 760);
                const left = Math.max(24, (window.innerWidth - width) / 2 + offset);
                const top = Math.max(88, (window.innerHeight - height) / 2 - 12);

                charactersWindow.style.width = `${width}px`;
                charactersWindow.style.height = `${height}px`;
                charactersWindow.style.left = `${left}px`;
                charactersWindow.style.top = `${top}px`;
                charactersWindow.style.right = 'auto';
                charactersWindow.dataset.positioned = '1';
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
            let offsetX = 0;
            let offsetY = 0;

            dragHandle.addEventListener('mousedown', (event) => {
                if (isMobile()) return;
                if (event.target.closest('button, a, input, textarea, select, label')) return;

                isDragging = true;
                offsetX = event.clientX - charactersWindow.offsetLeft;
                offsetY = event.clientY - charactersWindow.offsetTop;
                document.body.style.userSelect = 'none';
            });

            document.addEventListener('mouseup', () => {
                isDragging = false;
                document.body.style.userSelect = '';
            });

            document.addEventListener('mousemove', (event) => {
                if (!isDragging || isMobile()) return;

                const nextLeft = Math.min(
                    Math.max(8, event.clientX - offsetX),
                    Math.max(8, window.innerWidth - charactersWindow.offsetWidth - 8)
                );
                const nextTop = Math.min(
                    Math.max(8, event.clientY - offsetY),
                    Math.max(8, window.innerHeight - charactersWindow.offsetHeight - 8)
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

                const left = parseFloat(charactersWindow.style.left || '0');
                const top = parseFloat(charactersWindow.style.top || '0');
                const width = Math.min(960, window.innerWidth - 48);
                const height = Math.min(window.innerHeight * 0.78, 760);

                charactersWindow.style.width = `${width}px`;
                charactersWindow.style.height = `${height}px`;
                charactersWindow.style.left = `${Math.min(Math.max(8, left || 24), Math.max(8, window.innerWidth - width - 8))}px`;
                charactersWindow.style.top = `${Math.min(Math.max(8, top || 88), Math.max(8, window.innerHeight - height - 8))}px`;
            });

            if (!charactersWindow.classList.contains('hidden')) {
                ensureDefaultPosition();
            }
        })();
    </script>
@endif
