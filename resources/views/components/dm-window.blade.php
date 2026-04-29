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
                id="dm-close-btn"
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

        <!-- RIGHT: message area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <div id="dm-thread-header" class="px-3 py-2 border-b border-gray-800 text-xs text-gray-300">
                Select a conversation.
            </div>

            <div id="dm-thread" class="flex-1 p-3 text-sm text-gray-100 overflow-y-auto space-y-2">
                <div class="text-gray-500">No conversation selected.</div>
            </div>

            <div class="border-t border-gray-800 p-2">
                <div class="flex gap-2">
                    <textarea
                        id="dm-input"
                        class="flex-1 resize-none rounded bg-gray-950 border-gray-700 text-gray-200 text-sm"
                        rows="2"
                        placeholder="Message..."
                        disabled
                    ></textarea>

                    <button
                        id="dm-send-btn"
                        type="button"
                        class="rounded border border-gray-700 bg-gray-800 px-3 py-2 text-xs text-gray-100 hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled
                    >
                        Send
                    </button>
                </div>
                <div class="text-[10px] text-gray-500 mt-1">
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

<script>
(function () {
    const dmWindow = document.getElementById('dm-window');
    if (!dmWindow) return;

    const csrf = @json(csrf_token());

    const listEl = document.getElementById('dm-convo-list');
    const refreshBtn = document.getElementById('dm-refresh-btn');
    const closeBtn = document.getElementById('dm-close-btn');

    const threadHeader = document.getElementById('dm-thread-header');
    const threadEl = document.getElementById('dm-thread');
    const inputEl = document.getElementById('dm-input');
    const sendBtn = document.getElementById('dm-send-btn');

    let refreshListTimer = null;
    let pollDmTimer = null;

    // Prevent overlapping polls (race condition -> duplicates)
    let pollInFlight = false;

    // De-dupe rendered messages per conversation
    const seenMessageIds = new Set();

    let activeDm = {
        slug: null,
        conversationId: 0,
        lastId: 0,
        displayName: null,
        myCharacterId: 0,
    };

    let activeRealtimeConversationId = 0;
    let dmReconnectHandlerBound = false;

    window.StoryboxChannelCharacters = window.StoryboxChannelCharacters || {};

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

    function setThreadEnabled(enabled) {
        if (inputEl) inputEl.disabled = !enabled;
        if (sendBtn) sendBtn.disabled = !enabled;
    }

    function clearThread() {
        stopDmRealtime();
        activeDm.slug = null;
        activeDm.conversationId = 0;
        activeDm.lastId = 0;
        activeDm.displayName = null;
        activeDm.myCharacterId = 0;
        pollInFlight = false;
        seenMessageIds.clear();

        if (threadHeader) threadHeader.textContent = 'Select a conversation.';
        if (threadEl) threadEl.innerHTML = `<div class="text-gray-500">No conversation selected.</div>`;
        if (inputEl) inputEl.value = '';
        setThreadEnabled(false);
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
            const name = r.other_character_name || 'DM';

            const btn = document.createElement('button');
            btn.type = 'button';

            const isActive = activeDm.slug && slug === activeDm.slug;

            btn.className =
                'w-full text-left rounded border px-2 py-2 ' +
                (isActive
                    ? 'border-gray-600 bg-gray-800'
                    : 'border-gray-800 bg-gray-950 hover:bg-gray-800');

            btn.innerHTML = `
                <div class="text-xs text-gray-100 truncate">${escapeHtml(name)}</div>
                <div class="text-[10px] text-gray-500 truncate">${escapeHtml(slug)}</div>
            `;

            btn.addEventListener('click', () => {
                if (!slug) return;
                openConversation(slug, name, r.my_character_id);
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

    // ----- Style helpers (same behavior as rooms) -----
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

    function appendMessages(msgs, initialLoad) {
        if (!threadEl) return;

        if (initialLoad) {
            threadEl.innerHTML = '';
        }

        if (!Array.isArray(msgs) || msgs.length === 0) {
            if (initialLoad) {
                threadEl.innerHTML = `<div class="text-gray-500">No messages yet.</div>`;
            }
            return;
        }

        const wasNearBottom =
            threadEl.scrollHeight - threadEl.scrollTop - threadEl.clientHeight < 80;

        msgs.forEach(m => {
            const mid = parseInt(m.id || 0, 10);
            if (!mid) return;

            // De-dupe: prevents "first send shows twice"
            if (seenMessageIds.has(mid)) return;
            seenMessageIds.add(mid);

            const bodyRaw = (m.content ?? m.body ?? '').toString();
            const isDeleted = !!m.deleted_at || bodyRaw === '[deleted]';

            const who =
                (m.character && m.character.name)
                    ? m.character.name
                    : (m.user && m.user.name ? m.user.name : 'Unknown');

            // Character settings (for fades/colors)
            let settings = (m.character && m.character.settings) ? m.character.settings : {};
            if (typeof settings === 'string') {
                try { settings = JSON.parse(settings); } catch (e) { settings = {}; }
            }

            const c1 = settings.text_color_1 || '#D8F3FF';
            const c2 = settings.text_color_2 || null;
            const c3 = settings.text_color_3 || null;
            const c4 = settings.text_color_4 || null;

            const fadeName = !!settings.fade_name;
            const fadeMsg  = !!settings.fade_message;

            const nameStyleJson = JSON.stringify({ c1, c2, c3, c4, fade: fadeName });
            const bodyStyleJson = JSON.stringify({ c1, c2, c3, c4, fade: fadeMsg });

            const bubble = document.createElement('div');
            bubble.className = 'rounded border border-gray-800 bg-gray-950 px-2 py-1';

            bubble.innerHTML = `
                <div class="text-[10px] text-gray-400">
                    <span class="msg-name" data-style="${escapeHtml(nameStyleJson)}">${escapeHtml(who)}</span>
                </div>
                <div class="text-sm text-gray-100 whitespace-pre-line">
                    <span class="msg-body" data-style="${escapeHtml(bodyStyleJson)}">${escapeHtml(isDeleted ? '[deleted]' : bodyRaw)}</span>
                </div>
            `;

            threadEl.appendChild(bubble);

            applyStylesIn(bubble);

            if (mid > activeDm.lastId) activeDm.lastId = mid;
        });

        if (wasNearBottom || initialLoad) {
            threadEl.scrollTop = threadEl.scrollHeight;
        }
    }

    function fetchConversationInitial(slug) {
        if (!slug) return;

        pollInFlight = false;
        seenMessageIds.clear();
        activeDm.lastId = 0;
        activeDm.conversationId = 0;
        stopDmRealtime();

        fetch(`/dms/${encodeURIComponent(slug)}/messages?after=0`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            activeDm.conversationId = parseInt(data?.room?.id, 10) || 0;
            const msgs = data && Array.isArray(data.messages) ? data.messages : [];
            appendMessages(msgs, true);
            startDmRealtime();
        })
        .catch(err => {
            console.error('DM thread load error:', err);
            if (threadEl) threadEl.innerHTML = `<div class="text-red-400">Could not load messages.</div>`;
        });
    }

    function pollConversation() {
        if (!activeDm.slug) return;
        if (pollInFlight) return;

        pollInFlight = true;

        const after = activeDm.lastId || 0;

        fetch(`/dms/${encodeURIComponent(activeDm.slug)}/messages?after=${after}`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            const msgs = data && Array.isArray(data.messages) ? data.messages : [];
            if (!msgs.length) return;
            appendMessages(msgs, false);
        })
        .catch(() => {})
        .finally(() => {
            pollInFlight = false;
        });
    }

    function startDmPolling() {
        stopDmPolling();
        pollDmTimer = setInterval(() => {
            if (!isOpen()) return;
            if (!activeDm.slug) return;
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
                if (!eventId || seenMessageIds.has(eventId)) return;
                pollConversation();
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
            fetchDmRooms();
        }, 10000);
    }

    function stopListRefresh() {
        if (refreshListTimer) {
            clearInterval(refreshListTimer);
            refreshListTimer = null;
        }
    }

    function openConversation(slug, displayName, lockedMyCharacterId) {
        stopDmRealtime();
        activeDm.slug = slug;
        activeDm.conversationId = 0;
        activeDm.displayName = displayName || slug;
        activeDm.myCharacterId = parseInt(lockedMyCharacterId || 0, 10) || 0;

        if (threadHeader) threadHeader.textContent = `DM: ${activeDm.displayName}`;
        if (threadEl) threadEl.innerHTML = `<div class="text-gray-500">Loading...</div>`;

        setThreadEnabled(!!activeDm.myCharacterId);

        fetchConversationInitial(slug);
        fetchDmRooms();
        startDmPolling();
    }

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
            fetchDmRooms();
            // Let the DB commit settle; then pull once (de-dupe prevents doubles anyway)
            setTimeout(() => pollConversation(), 150);
        })
        .catch(err => {
            console.error('DM send error:', err);
        });
    }

    /*
    | OPEN EVENT
    */
    window.addEventListener('open-dm-window', (e) => {
        dmWindow.classList.remove('hidden');

        fetchDmRooms();
        startListRefresh();

        const slug = e?.detail?.slug;
        const name = e?.detail?.name;

        if (slug) {
            openConversation(slug, name || slug, e?.detail?.my_character_id);
        } else {
            clearThread();
        }
    });

    refreshBtn?.addEventListener('click', () => {
        fetchDmRooms();
        if (activeDm.slug) pollConversation();
    });

    closeBtn?.addEventListener('click', () => {
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
