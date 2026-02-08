<div
    id="dm-window"
    class="hidden fixed z-50 bg-gray-900 border border-gray-700 rounded-lg shadow-2xl flex flex-col"
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
        class="cursor-move flex items-center justify-between px-3 py-2 border-b border-gray-800 bg-gray-950 rounded-t-lg"
    >
        <div class="text-sm text-gray-200 font-semibold">
            Direct Messages
        </div>

        <div class="flex gap-2">
            <button
                id="dm-refresh-btn"
                class="text-gray-400 hover:text-white text-sm"
                type="button"
                title="Refresh"
            >
                ↻
            </button>

            <button
                onclick="document.getElementById('dm-window').classList.add('hidden')"
                class="text-gray-400 hover:text-white text-sm"
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
        <div class="w-44 border-r border-gray-800 overflow-y-auto text-xs text-gray-200">
            <div class="p-2 border-b border-gray-800 text-[11px] text-gray-400">
                Conversations
            </div>

            <div id="dm-convo-list" class="p-2 space-y-2">
                <div class="text-gray-500">Loading...</div>
            </div>
        </div>

        <!-- RIGHT: message area placeholder -->
        <div class="flex-1 flex flex-col">
            <div id="dm-convo-empty" class="flex-1 p-3 text-sm text-gray-400 overflow-y-auto">
                Select a conversation.
            </div>

            <div class="border-t border-gray-800 p-2">
                <textarea
                    class="w-full resize-none rounded bg-gray-950 border-gray-700 text-gray-200 text-sm"
                    rows="2"
                    placeholder="Message... (not wired yet)"
                    disabled
                ></textarea>
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


<script>
(function () {
    const dmWindow = document.getElementById('dm-window');
    if (!dmWindow) return;

    const listEl = document.getElementById('dm-convo-list');
    const refreshBtn = document.getElementById('dm-refresh-btn');

    let refreshTimer = null;

    function isOpen() {
        return !dmWindow.classList.contains('hidden');
    }

    function renderRooms(rooms) {
        if (!listEl) return;

        if (!Array.isArray(rooms) || rooms.length === 0) {
            listEl.innerHTML = `<div class="text-gray-500">No DMs yet.</div>`;
            return;
        }

        listEl.innerHTML = '';

        rooms.forEach(r => {
            const slug = r.slug || '';
            const name = r.name || 'DM';
            const updated = r.updated_at || '';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left rounded border border-gray-800 bg-gray-950 hover:bg-gray-800 px-2 py-2';
            btn.innerHTML = `
                <div class="text-xs text-gray-100 truncate">${escapeHtml(name)}</div>
                <div class="text-[10px] text-gray-500 truncate">${escapeHtml(slug)}</div>
            `;

            btn.addEventListener('click', () => {
                if (!slug) return;
                window.location.href = `/rooms/${slug}`;
            });

            listEl.appendChild(btn);
        });
    }

    function fetchDmRooms() {
        if (!isOpen()) return;

        if (!listEl) return;
        listEl.innerHTML = `<div class="text-gray-500">Loading...</div>`;

        fetch('/dms', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            const rooms = data && Array.isArray(data.rooms) ? data.rooms : [];
            renderRooms(rooms);
        })
        .catch(err => {
            console.error('DM list error:', err);
            if (listEl) listEl.innerHTML = `<div class="text-red-400">Could not load DMs.</div>`;
        });
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function startAutoRefresh() {
        stopAutoRefresh();
        refreshTimer = setInterval(() => {
            if (isOpen()) fetchDmRooms();
        }, 10000);
    }

    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    /*
    | OPEN EVENT
    */
    window.addEventListener('open-dm-window', () => {
        dmWindow.classList.remove('hidden');
        fetchDmRooms();
        startAutoRefresh();
    });

    refreshBtn?.addEventListener('click', () => {
        fetchDmRooms();
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
    | Optional: if the user closes the window, stop polling
    */
    const observer = new MutationObserver(() => {
        if (!isOpen()) stopAutoRefresh();
    });
    observer.observe(dmWindow, { attributes: true, attributeFilter: ['class'] });

})();
</script>
