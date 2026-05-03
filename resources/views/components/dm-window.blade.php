<div
    id="dm-window"
    class="hidden fixed z-50 bg-gray-950 border border-gray-800 rounded-lg shadow-2xl flex flex-col overflow-hidden ring-1 ring-emerald-500/10"
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
        class="cursor-move flex items-center justify-between px-3 py-2 border-b border-gray-800 bg-gray-900/95"
    >
        <div>
            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-400">Private Link</div>
            <div class="text-sm text-gray-100 font-semibold">
                Direct Messages
            </div>
        </div>

        <div class="flex gap-2">
            <button
                id="dm-refresh-btn"
                class="rounded border border-gray-800 bg-gray-950/70 px-2 py-1 text-gray-400 hover:border-gray-700 hover:text-white text-sm"
                type="button"
                title="Refresh"
            >
                ↻
            </button>

            <button
                id="dm-close-btn"
                class="rounded border border-gray-800 bg-gray-950/70 px-2 py-1 text-gray-400 hover:border-gray-700 hover:text-white text-sm"
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
        <div class="w-44 border-r border-gray-800 bg-gray-950 overflow-y-auto text-xs text-gray-200">
            <div class="p-2 border-b border-gray-800 text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">
                Conversations
            </div>

            <div id="dm-convo-list" class="p-2 space-y-2">
                <div class="text-gray-500">Loading...</div>
            </div>
        </div>

        <!-- RIGHT: message area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <div class="px-3 py-2 border-b border-gray-800 bg-gray-900/70 text-xs text-gray-300 flex items-center justify-between gap-2">
                <div id="dm-thread-header" class="min-w-0 truncate">
                    Select a conversation.
                </div>

                <button
                    id="dm-block-toggle"
                    type="button"
                    class="hidden shrink-0 text-xs text-red-400 hover:text-red-300"
                >
                    Block
                </button>
            </div>

            <div id="dm-thread" class="flex-1 bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.08),transparent_20rem)] p-3 text-sm text-gray-100 overflow-y-auto space-y-2">
                <div class="text-gray-500">No conversation selected.</div>
            </div>

            <div class="border-t border-gray-800 bg-gray-900/95 p-2">
                <div class="flex gap-2">
                    <textarea
                        id="dm-input"
                        class="flex-1 resize-none rounded bg-gray-950 border-gray-700 text-gray-200 text-sm placeholder:text-gray-600 focus:border-emerald-500 focus:ring-emerald-500"
                        rows="2"
                        placeholder="Message..."
                        disabled
                    ></textarea>

                    <button
                        id="dm-send-btn"
                        type="button"
                        class="rounded border border-emerald-500/50 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-100 hover:bg-emerald-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
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
    const globalUnreadBadge = document.getElementById('dm-unread-badge');
    const refreshBtn = document.getElementById('dm-refresh-btn');
    const closeBtn = document.getElementById('dm-close-btn');

    const threadHeader = document.getElementById('dm-thread-header');
    const blockToggleBtn = document.getElementById('dm-block-toggle');
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
        lastCharacterId: 0,
        displayName: null,
        myCharacterId: 0,
        otherCharacterId: 0,
        isBlockedByViewer: false,
    };

    let activeRealtimeConversationId = 0;
    let dmReconnectHandlerBound = false;
    const dmListRealtimeConversationIds = new Set();

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

    function escapeAttr(s) {
        return escapeHtml(s);
    }

    function isSafeAvatarUrl(url) {
        if (!url) return false;

        try {
            const parsed = new URL(url, window.location.origin);
            return parsed.protocol === 'http:' || parsed.protocol === 'https:';
        } catch (e) {
            return false;
        }
    }

    function avatarInitial(name) {
        return (String(name || '?').trim().charAt(0) || '?').toUpperCase();
    }

    function avatarHtml(url, name, sizeClass = 'h-8 w-8', shapeClass = 'rounded-full') {
        if (isSafeAvatarUrl(url)) {
            return `<img src="${escapeAttr(url)}" alt="${escapeAttr(name)} avatar" loading="lazy" referrerpolicy="no-referrer" class="${sizeClass} shrink-0 ${shapeClass} object-cover">`;
        }

        return `<div class="flex ${sizeClass} shrink-0 items-center justify-center ${shapeClass} border border-gray-800 bg-gray-950 text-xs font-semibold text-gray-500">${escapeHtml(avatarInitial(name))}</div>`;
    }

    function setThreadEnabled(enabled) {
        if (inputEl) inputEl.disabled = !enabled;
        if (sendBtn) sendBtn.disabled = !enabled;
    }

    window.setCharacterBlock = window.setCharacterBlock || function setCharacterBlock(blockerId, blockedId, shouldBlock) {
        const action = shouldBlock ? "Block this character?" : "Unblock this character?";
        if (!confirm(action)) return;

        const token = document.querySelector('meta[name="csrf-token"]').content;

        fetch(`/characters/${blockerId}/blocks/${blockedId}`, {
            method: shouldBlock ? 'POST' : 'DELETE',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error();
            return res.json();
        })
        .then(() => location.reload())
        .catch(() => alert(shouldBlock ? "Failed to block character." : "Failed to unblock character."));
    };

    function syncDmBlockToggle() {
        if (!blockToggleBtn) return;

        const canToggle = !!activeDm.myCharacterId && !!activeDm.otherCharacterId;
        blockToggleBtn.classList.toggle('hidden', !canToggle);
        if (!canToggle) return;

        blockToggleBtn.textContent = activeDm.isBlockedByViewer ? 'Blocked' : 'Block';
        blockToggleBtn.className = 'shrink-0 rounded border border-gray-800 bg-gray-950/70 px-2 py-1 text-xs ' + (
            activeDm.isBlockedByViewer
                ? 'text-gray-400 hover:text-gray-300'
                : 'text-red-400 hover:text-red-300'
        );
    }

    function parseUnreadCount(value) {
        const n = parseInt(value || '0', 10);
        return Number.isFinite(n) && n > 0 ? n : 0;
    }

    function parseBool(value) {
        return value === true || value === 1 || value === '1';
    }

    function formatUnreadCount(count) {
        return count > 99 ? '99+' : String(count);
    }

    function setUnreadBadge(badge, count) {
        if (!badge) return;
        const normalized = parseUnreadCount(count);
        badge.dataset.unreadCount = String(normalized);
        badge.textContent = formatUnreadCount(normalized);
        badge.classList.toggle('hidden', normalized <= 0);
    }

    function incrementUnreadBadge(badge) {
        if (!badge) return;
        setUnreadBadge(badge, parseUnreadCount(badge.dataset.unreadCount) + 1);
    }

    function clearUnreadBadge(badge) {
        setUnreadBadge(badge, 0);
    }

    function updateGlobalUnreadBadge(rooms) {
        if (!globalUnreadBadge) return;

        const total = Array.from(listEl?.querySelectorAll('[data-dm-unread-badge]') || [])
            .reduce((sum, badge) => sum + parseUnreadCount(badge.dataset.unreadCount), 0);

        setUnreadBadge(globalUnreadBadge, total);
    }

    function updateGlobalUnreadBadgeFromRooms(rooms) {
        if (!globalUnreadBadge) return;

        const total = (Array.isArray(rooms) ? rooms : []).reduce((sum, r) => {
            return sum + parseUnreadCount(r.unread_count);
        }, 0);

        setUnreadBadge(globalUnreadBadge, total);
    }

    function clearDmUnread(conversationId) {
        const badge = listEl?.querySelector(`[data-dm-unread-badge="${conversationId}"]`);
        clearUnreadBadge(badge);
        updateGlobalUnreadBadge();
    }

    function incrementDmUnread(conversationId) {
        const normalizedConversationId = parseInt(conversationId || 0, 10) || 0;
        if (!normalizedConversationId) return;

        if (isOpen() && activeDm.conversationId === normalizedConversationId) {
            return;
        }

        const badge = listEl?.querySelector(`[data-dm-unread-badge="${normalizedConversationId}"]`);
        if (!badge) return;

        incrementUnreadBadge(badge);
        updateGlobalUnreadBadge();
    }

    function clearThread() {
        stopDmRealtime();
        activeDm.slug = null;
        activeDm.conversationId = 0;
        activeDm.lastId = 0;
        activeDm.lastCharacterId = 0;
        activeDm.displayName = null;
        activeDm.myCharacterId = 0;
        activeDm.otherCharacterId = 0;
        activeDm.isBlockedByViewer = false;
        pollInFlight = false;
        seenMessageIds.clear();

        if (threadHeader) threadHeader.textContent = 'Select a conversation.';
        syncDmBlockToggle();
        if (threadEl) threadEl.innerHTML = `<div class="text-gray-500">No conversation selected.</div>`;
        if (inputEl) inputEl.value = '';
        setThreadEnabled(false);
    }

    function renderRooms(rooms) {
        if (!listEl) return;

        if (!Array.isArray(rooms) || rooms.length === 0) {
            updateGlobalUnreadBadgeFromRooms([]);
            listEl.innerHTML = `<div class="text-gray-500">No DMs yet.</div>`;
            return;
        }

        updateGlobalUnreadBadgeFromRooms(rooms);
        listEl.innerHTML = '';

        rooms.forEach(r => {
            const conversationId = parseInt(r.room_id || 0, 10) || 0;
            const slug = r.slug || '';
            const name = r.other_character_name || 'DM';
            const avatar = r.other_character_avatar || '';
            const unreadCount = parseUnreadCount(r.unread_count);

            const btn = document.createElement('button');
            btn.type = 'button';
            if (conversationId) btn.dataset.dmConversationId = String(conversationId);

            const isActive = activeDm.slug && slug === activeDm.slug;

            btn.className =
                'w-full text-left rounded border px-2 py-2 transition-colors ' +
                (isActive
                    ? 'border-emerald-500/40 bg-emerald-500/10'
                    : 'border-gray-800 bg-gray-900/60 hover:border-gray-700 hover:bg-gray-900');

            btn.innerHTML = `
                <div class="flex items-center gap-2">
                    ${avatarHtml(avatar, name, 'h-7 w-7')}
                    <div class="min-w-0 flex-1 text-xs text-gray-100 truncate">${escapeHtml(name)}</div>
                    <span
                        data-dm-unread-badge="${conversationId}"
                        data-unread-count="${unreadCount}"
                        class="${unreadCount > 0 ? '' : 'hidden'} shrink-0 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                        ${formatUnreadCount(unreadCount)}
                    </span>
                </div>
                <div class="text-[10px] text-gray-500 truncate">${escapeHtml(slug)}</div>
            `;

            btn.addEventListener('click', () => {
                if (!slug) return;
                openConversation(slug, name, r.my_character_id, r.other_character_id, parseBool(r.is_blocked_by_viewer));
            });

            listEl.appendChild(btn);
        });

        syncDmListRealtimeSubscriptions(rooms);
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
            activeDm.lastCharacterId = 0;
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
            const avatar = m.character?.avatar || '';
            const characterId = parseInt(m.character?.id ?? m.character_id ?? 0, 10) || 0;
            const isGrouped = characterId > 0 && activeDm.lastCharacterId === characterId;

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
            const avatarMarkup = isGrouped ? '<div class="w-8 shrink-0"></div>' : avatarHtml(avatar, who, 'h-8 w-8');
            const nameMarkup = isGrouped ? '' : `
                        <div class="text-[10px] font-semibold text-gray-400">
                            <span class="msg-name" data-style="${escapeHtml(nameStyleJson)}">${escapeHtml(who)}</span>
                        </div>
            `;

            const bubble = document.createElement('div');
            bubble.className = `rounded bg-gray-950/80 px-2 ${isGrouped ? 'py-0.5' : 'border border-gray-800 py-1.5'}`;
            bubble.dataset.characterId = characterId ? String(characterId) : '';

            bubble.innerHTML = `
                <div class="flex items-start">
                    ${avatarMarkup}
                    <div class="ml-2 min-w-0 flex-1">
                        ${nameMarkup}
                        <div class="${isGrouped ? 'mt-0' : 'mt-0.5'} text-sm text-gray-100 whitespace-pre-line leading-relaxed">
                            <span class="msg-body" data-style="${escapeHtml(bodyStyleJson)}">${escapeHtml(isDeleted ? '[deleted]' : bodyRaw)}</span>
                        </div>
                    </div>
                </div>
            `;

            threadEl.appendChild(bubble);

            applyStylesIn(bubble);

            if (mid > activeDm.lastId) activeDm.lastId = mid;
            activeDm.lastCharacterId = characterId;
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
        activeDm.lastCharacterId = 0;
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
            clearDmUnread(activeDm.conversationId);
            fetchDmRooms();
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
            if (msgs.length) appendMessages(msgs, false);
            clearDmUnread(activeDm.conversationId);
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

    function syncDmListRealtimeSubscriptions(rooms) {
        if (!window.Echo || !Array.isArray(rooms)) return;

        rooms.forEach((room) => {
            const conversationId = parseInt(room.room_id || 0, 10) || 0;
            const characterId = parseInt(room.my_character_id || 0, 10) || 0;

            if (!conversationId || !characterId || dmListRealtimeConversationIds.has(conversationId)) {
                return;
            }

            dmListRealtimeConversationIds.add(conversationId);

            const channelName = `private-conversation.${conversationId}`;
            window.StoryboxChannelCharacters[channelName] = characterId;

            window.Echo.private(`conversation.${conversationId}`)
                .listen('.message.created', (event) => {
                    const eventId = parseInt(event.id, 10);
                    if (!eventId) return;

                    if (isOpen() && activeDm.conversationId === conversationId) {
                        return;
                    }

                    incrementDmUnread(conversationId);
                });
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

    function openConversation(slug, displayName, lockedMyCharacterId, otherCharacterId, isBlockedByViewer) {
        stopDmRealtime();
        activeDm.slug = slug;
        activeDm.conversationId = 0;
        activeDm.displayName = displayName || slug;
        activeDm.myCharacterId = parseInt(lockedMyCharacterId || 0, 10) || 0;
        activeDm.otherCharacterId = parseInt(otherCharacterId || 0, 10) || 0;
        activeDm.isBlockedByViewer = parseBool(isBlockedByViewer);

        if (threadHeader) threadHeader.textContent = `DM: ${activeDm.displayName}`;
        syncDmBlockToggle();
        if (threadEl) threadEl.innerHTML = `<div class="text-gray-500">Loading...</div>`;

        setThreadEnabled(!!activeDm.myCharacterId);

        fetchConversationInitial(slug);
        fetchDmRooms();
        startDmPolling();
    }

    blockToggleBtn?.addEventListener('click', () => {
        if (!activeDm.myCharacterId || !activeDm.otherCharacterId) return;
        window.setCharacterBlock(activeDm.myCharacterId, activeDm.otherCharacterId, !activeDm.isBlockedByViewer);
    });

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
            openConversation(
                slug,
                name || slug,
                e?.detail?.my_character_id,
                e?.detail?.other_character_id,
                e?.detail?.is_blocked_by_viewer
            );
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
