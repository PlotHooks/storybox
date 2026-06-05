<div
    id="dm-window"
    class="hidden fixed z-50 bg-[#0b0b0c] border border-[#2a241a] rounded-md shadow-2xl flex flex-col overflow-hidden ring-1 ring-amber-500/10"
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
        class="cursor-move flex items-center justify-between px-3 py-2 border-b border-[#2a241a] bg-[#101012]"
    >
        <div>
            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-400">Private Link</div>
            <div class="text-sm text-[#f2dfb5] font-semibold">
                Direct Messages
            </div>
        </div>

        <div class="flex gap-2">
            <button
                id="dm-refresh-btn"
                class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm"
                type="button"
                title="Refresh"
            >
                ↻
            </button>

            <button
                id="dm-close-btn"
                class="rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-[#8f8675] hover:border-amber-500/40 hover:bg-[#141416] hover:text-[#f2dfb5] text-sm"
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
        <div class="w-44 border-r border-[#2a241a] bg-[#0b0b0c] overflow-y-auto text-xs text-[#d6c8ad]">
            <div class="p-2 border-b border-[#2a241a] text-[11px] font-semibold uppercase tracking-[0.14em] text-[#8f8675]">
                Conversations
            </div>

            <div id="dm-convo-list" class="p-2 space-y-2">
                <div class="text-[#8f8675]">Loading...</div>
            </div>
        </div>

        <!-- RIGHT: message area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <div class="px-3 py-2 border-b border-[#2a241a] bg-[#101012] text-xs text-[#d6c8ad] flex items-center justify-between gap-2">
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

            <div id="dm-thread" class="flex-1 bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.035),transparent_22rem)] p-3 text-sm text-[#d6c8ad] overflow-y-auto space-y-2">
                <div class="text-[#8f8675]">No conversation selected.</div>
            </div>

            <div class="border-t border-[#2a241a] bg-[#101012] p-2">
                <div class="flex gap-2">
                    <textarea
                        id="dm-input"
                        class="flex-1 resize-none rounded bg-[#0b0b0c] border-[#332817] text-[#d6c8ad] text-sm placeholder:text-[#6f675a] focus:border-amber-500 focus:ring-amber-500"
                        rows="2"
                        placeholder="Message..."
                        disabled
                    ></textarea>

                    <button
                        id="dm-send-btn"
                        type="button"
                        class="rounded border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-100 hover:bg-amber-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled
                    >
                        Send
                    </button>
                </div>
                <div class="text-[10px] text-amber-500/70 mt-1">
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
    let pollInFlight = false;
    let roomsLoaded = false;

    let activeDm = {
        slug: null,
        conversationId: 0,
        lastId: 0,
        displayName: null,
        myCharacterId: 0,
        otherCharacterId: 0,
        isBlockedByViewer: false,
    };

    let activeRealtimeConversationId = 0;
    let dmReconnectHandlerBound = false;
    const dmListRealtimeConversationIds = new Set();
    const dmRoomsBySlug = new Map();
    const dmMessageCache = new Map();
    const lastDmSlugStorageKey = 'storybox_last_dm_slug';

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

        return `<div class="flex ${sizeClass} shrink-0 items-center justify-center ${shapeClass} border border-[#332817] bg-[#0b0b0c] text-xs font-semibold text-[#8f8675]">${escapeHtml(avatarInitial(name))}</div>`;
    }

    function setThreadEnabled(enabled) {
        if (inputEl) inputEl.disabled = !enabled;
        if (sendBtn) sendBtn.disabled = !enabled;
    }

    window.setCharacterBlock = window.setCharacterBlock || function setCharacterBlock(blockerId, blockedId, shouldBlock) {
        const action = shouldBlock ? 'Block this character?' : 'Unblock this character?';
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
        .catch(() => alert(shouldBlock ? 'Failed to block character.' : 'Failed to unblock character.'));
    };

    function syncDmBlockToggle() {
        if (!blockToggleBtn) return;

        const canToggle = !!activeDm.myCharacterId && !!activeDm.otherCharacterId;
        blockToggleBtn.classList.toggle('hidden', !canToggle);
        if (!canToggle) return;

        blockToggleBtn.textContent = activeDm.isBlockedByViewer ? 'Blocked' : 'Block';
        blockToggleBtn.className = 'shrink-0 rounded border border-[#332817] bg-[#0b0b0c] px-2 py-1 text-xs ' + (
            activeDm.isBlockedByViewer
                ? 'text-[#8f8675] hover:text-[#d6c8ad]'
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

    function updateGlobalUnreadBadge() {
        if (!globalUnreadBadge) return;

        const total = Array.from(listEl?.querySelectorAll('[data-dm-unread-badge]') || [])
            .reduce((sum, badge) => sum + parseUnreadCount(badge.dataset.unreadCount), 0);

        setUnreadBadge(globalUnreadBadge, total);
    }

    function updateGlobalUnreadBadgeFromRooms(rooms) {
        if (!globalUnreadBadge) return;

        const total = (Array.isArray(rooms) ? rooms : []).reduce((sum, room) => {
            return sum + parseUnreadCount(room.unreadCount ?? room.unread_count);
        }, 0);

        setUnreadBadge(globalUnreadBadge, total);
    }

    function findRoomByConversationId(conversationId) {
        for (const room of dmRoomsBySlug.values()) {
            if (room.roomId === conversationId) return room;
        }

        return null;
    }

    function setRoomUnreadCount(slug, count) {
        const room = dmRoomsBySlug.get(slug);
        if (!room) return;
        room.unreadCount = parseUnreadCount(count);
    }

    function clearDmUnread(conversationId, slug = null) {
        const badge = listEl?.querySelector(`[data-dm-unread-badge="${conversationId}"]`);
        setUnreadBadge(badge, 0);

        const room = slug ? dmRoomsBySlug.get(slug) : findRoomByConversationId(conversationId);
        if (room?.slug) setRoomUnreadCount(room.slug, 0);

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

        const room = findRoomByConversationId(normalizedConversationId);
        if (room?.slug) {
            room.unreadCount = parseUnreadCount(room.unreadCount) + 1;
        }

        updateGlobalUnreadBadge();
    }

    function getMessageCache(slug) {
        if (!slug) return null;

        if (!dmMessageCache.has(slug)) {
            dmMessageCache.set(slug, {
                messages: [],
                messageIds: new Set(),
                lastId: 0,
                loaded: false,
                loading: false,
                scrollTop: null,
            });
        }

        return dmMessageCache.get(slug);
    }

    function normalizeRoom(raw) {
        return {
            roomId: parseInt(raw.room_id || 0, 10) || 0,
            slug: raw.slug || '',
            updatedAt: raw.updated_at || null,
            displayName: raw.other_character_name || 'DM',
            avatar: raw.other_character_avatar || '',
            unreadCount: parseUnreadCount(raw.unread_count),
            myCharacterId: parseInt(raw.my_character_id || 0, 10) || 0,
            otherCharacterId: parseInt(raw.other_character_id || 0, 10) || 0,
            isBlockedByViewer: parseBool(raw.is_blocked_by_viewer),
        };
    }


    function getLastOpenedDmSlug() {
        try {
            return sessionStorage.getItem(lastDmSlugStorageKey) || '';
        } catch (e) {
            return '';
        }
    }

    function setLastOpenedDmSlug(slug) {
        if (!slug) return;

        try {
            sessionStorage.setItem(lastDmSlugStorageKey, slug);
        } catch (e) {
            // Ignore storage failures and continue with in-memory selection only.
        }
    }

    function clearLastOpenedDmSlug() {
        try {
            sessionStorage.removeItem(lastDmSlugStorageKey);
        } catch (e) {
            // Ignore storage failures and continue with in-memory selection only.
        }
    }

    function resolveDefaultConversationSlug(rooms) {
        const normalizedRooms = Array.isArray(rooms)
            ? rooms.map(normalizeRoom).filter((room) => !!room.slug)
            : [];

        if (activeDm.slug && normalizedRooms.some((room) => room.slug === activeDm.slug)) {
            return activeDm.slug;
        }

        const storedSlug = getLastOpenedDmSlug();
        if (storedSlug && normalizedRooms.some((room) => room.slug === storedSlug)) {
            return storedSlug;
        }

        return normalizedRooms[0]?.slug || null;
    }

    function roomButtonClass(isActive) {
        return 'w-full text-left rounded border px-2 py-2 transition-colors ' + (
            isActive
                ? 'border-amber-500/40 bg-amber-500/10 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.10)]'
                : 'border-[#332817] bg-[#101012] hover:border-amber-500/40 hover:bg-[#141416]'
        );
    }

    function createRoomButton(room) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.dmSlug = room.slug;
        btn.addEventListener('click', () => {
            if (!room.slug) return;
            openConversation(room.slug);
        });

        return btn;
    }

    function updateRoomButton(btn, room) {
        if (!btn || !room) return;

        btn.dataset.dmSlug = room.slug;
        if (room.roomId) btn.dataset.dmConversationId = String(room.roomId);

        const isActive = activeDm.slug && room.slug === activeDm.slug;
        btn.className = roomButtonClass(isActive);
        btn.innerHTML = `
            <div class="flex items-center gap-2">
                ${avatarHtml(room.avatar, room.displayName, 'h-7 w-7')}
                <div class="min-w-0 flex-1 text-xs text-[#d6c8ad] truncate">${escapeHtml(room.displayName)}</div>
                <span
                    data-dm-unread-badge="${room.roomId}"
                    data-unread-count="${room.unreadCount}"
                    class="${room.unreadCount > 0 ? '' : 'hidden'} shrink-0 rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                    ${formatUnreadCount(room.unreadCount)}
                </span>
            </div>
            <div class="text-[10px] text-[#8f8675] truncate">${escapeHtml(room.slug)}</div>
        `;
    }

    function refreshRoomButton(slug) {
        const room = dmRoomsBySlug.get(slug);
        const btn = listEl?.querySelector(`[data-dm-slug="${CSS.escape(slug)}"]`);
        if (!room || !btn) return;
        updateRoomButton(btn, room);
    }

    function syncActiveConversationHighlight() {
        if (!listEl) return;

        listEl.querySelectorAll('[data-dm-slug]').forEach((btn) => {
            const room = dmRoomsBySlug.get(btn.dataset.dmSlug || '');
            if (room) updateRoomButton(btn, room);
        });
    }

    function setRoomListMessage(message, isError = false) {
        if (!listEl) return;
        listEl.innerHTML = `<div class="${isError ? 'text-red-400' : 'text-[#8f8675]'}">${escapeHtml(message)}</div>`;
    }

    function renderRooms(rooms) {
        if (!listEl) return;

        const normalizedRooms = Array.isArray(rooms) ? rooms.map(normalizeRoom) : [];
        updateGlobalUnreadBadgeFromRooms(normalizedRooms);
        roomsLoaded = true;

        if (normalizedRooms.length === 0) {
            dmRoomsBySlug.clear();
            clearLastOpenedDmSlug();
            setRoomListMessage('No DMs yet.');
            return;
        }

        const previousScrollTop = listEl.scrollTop;
        const existingButtons = new Map(
            Array.from(listEl.querySelectorAll('[data-dm-slug]')).map((btn) => [btn.dataset.dmSlug, btn])
        );
        const nextSlugs = new Set();

        if (existingButtons.size === 0) {
            listEl.innerHTML = '';
        }

        normalizedRooms.forEach((room, index) => {
            if (!room.slug) return;

            nextSlugs.add(room.slug);
            dmRoomsBySlug.set(room.slug, room);

            const btn = existingButtons.get(room.slug) || createRoomButton(room);
            updateRoomButton(btn, room);

            const currentChild = listEl.children[index];
            if (currentChild !== btn) {
                listEl.insertBefore(btn, currentChild || null);
            }
        });

        Array.from(listEl.querySelectorAll('[data-dm-slug]')).forEach((btn) => {
            if (!nextSlugs.has(btn.dataset.dmSlug || '')) {
                btn.remove();
            }
        });

        for (const slug of Array.from(dmRoomsBySlug.keys())) {
            if (!nextSlugs.has(slug)) {
                dmRoomsBySlug.delete(slug);
            }
        }

        listEl.scrollTop = previousScrollTop;
        syncDmListRealtimeSubscriptions(normalizedRooms);
    }

    function fetchDmRooms(options = {}) {
        const { showLoading = false } = options;

        if (!isOpen() || !listEl) return Promise.resolve([]);

        if (showLoading && !roomsLoaded) {
            setRoomListMessage('Loading...');
        }

        return fetch('/dms', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            const rooms = data && Array.isArray(data.rooms) ? data.rooms : [];
            renderRooms(rooms);
            return rooms;
        })
        .catch(err => {
            console.error('DM list error:', err);
            if (!roomsLoaded) setRoomListMessage('Could not load DMs.', true);
            return [];
        });
    }

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

    function replaceCachedMessages(slug, msgs) {
        const cache = getMessageCache(slug);
        if (!cache) return;

        cache.messages = [];
        cache.messageIds.clear();
        cache.lastId = 0;

        (Array.isArray(msgs) ? msgs : []).forEach((message) => {
            const id = parseInt(message?.id || 0, 10);
            if (!id || cache.messageIds.has(id)) return;
            cache.messageIds.add(id);
            cache.messages.push(message);
            if (id > cache.lastId) cache.lastId = id;
        });

        cache.loaded = true;
    }

    function appendCachedMessages(slug, msgs) {
        const cache = getMessageCache(slug);
        if (!cache) return false;

        let changed = false;

        (Array.isArray(msgs) ? msgs : []).forEach((message) => {
            const id = parseInt(message?.id || 0, 10);
            if (!id || cache.messageIds.has(id)) return;
            cache.messageIds.add(id);
            cache.messages.push(message);
            if (id > cache.lastId) cache.lastId = id;
            changed = true;
        });

        if (changed) {
            cache.messages.sort((a, b) => (parseInt(a?.id || 0, 10) || 0) - (parseInt(b?.id || 0, 10) || 0));
            cache.loaded = true;
        }

        return changed;
    }

    function renderConversationLoading() {
        if (!threadEl) return;
        threadEl.innerHTML = `<div class="text-[#8f8675]">Loading...</div>`;
    }

    function renderActiveConversation(options = {}) {
        if (!threadEl || !activeDm.slug) return;

        const cache = getMessageCache(activeDm.slug);
        if (!cache) return;

        const restoreScroll = options.restoreScroll === true;
        const keepPosition = options.keepPosition === true;
        const previousScrollTop = threadEl.scrollTop;
        const previousDistanceFromBottom = threadEl.scrollHeight - threadEl.scrollTop - threadEl.clientHeight;
        const shouldStickBottom = options.forceBottom === true || (!restoreScroll && !keepPosition && previousDistanceFromBottom < 80);

        threadEl.innerHTML = '';
        activeDm.lastId = cache.lastId || 0;

        if (!cache.loaded) {
            renderConversationLoading();
            return;
        }

        if (cache.messages.length === 0) {
            threadEl.innerHTML = `<div class="text-[#8f8675]">No messages yet.</div>`;
        } else {
            let lastCharacterId = 0;

            cache.messages.forEach((m) => {
                const bodyRaw = (m.content ?? m.body ?? '').toString();
                const isDeleted = !!m.deleted_at || bodyRaw === '[deleted]';
                const bodyDisplay = bodyRaw.trim();
                const who =
                    (m.character && m.character.name)
                        ? m.character.name
                        : (m.user && m.user.name ? m.user.name : 'Unknown');
                const avatar = m.character?.avatar || '';
                const characterId = parseInt(m.character?.id ?? m.character_id ?? 0, 10) || 0;
                const isGrouped = characterId > 0 && lastCharacterId === characterId;

                let settings = (m.character && m.character.settings) ? m.character.settings : {};
                if (typeof settings === 'string') {
                    try { settings = JSON.parse(settings); } catch (e) { settings = {}; }
                }

                const c1 = settings.text_color_1 || '#D8F3FF';
                const c2 = settings.text_color_2 || null;
                const c3 = settings.text_color_3 || null;
                const c4 = settings.text_color_4 || null;
                const fadeName = !!settings.fade_name;
                const fadeMsg = !!settings.fade_message;
                const nameStyleJson = JSON.stringify({ c1, c2, c3, c4, fade: fadeName });
                const bodyStyleJson = JSON.stringify({ c1, c2, c3, c4, fade: fadeMsg });
                const avatarMarkup = isGrouped
                    ? '<div class="w-7 shrink-0"></div>'
                    : `<div class="w-7 shrink-0">${avatarHtml(avatar, who, 'h-7 w-7')}</div>`;
                const nameMarkup = isGrouped ? '' : `
                            <div class="mb-0 flex items-baseline gap-2">
                                <span class="msg-name text-base font-bold leading-none" data-style="${escapeHtml(nameStyleJson)}">${escapeHtml(who)}</span>
                            </div>
                `;

                const bubble = document.createElement('div');
                bubble.className = `flex gap-2 px-2 ${isGrouped ? 'border-0 rounded-none bg-transparent py-0' : 'border-t border-[#16120c] py-0.5'}`;
                bubble.dataset.characterId = characterId ? String(characterId) : '';
                bubble.innerHTML = `
                    ${avatarMarkup}
                    <div class="min-w-0 flex-1">
                        ${nameMarkup}
                        <div class="msg-body-wrapper mt-0 text-sm leading-snug">
                            <span class="msg-body text-sm text-[#d6c8ad] leading-snug whitespace-pre-line" data-style="${escapeHtml(bodyStyleJson)}">${escapeHtml(isDeleted ? '[deleted]' : bodyDisplay)}</span>
                        </div>
                    </div>
                `;

                threadEl.appendChild(bubble);
                applyStylesIn(bubble);
                lastCharacterId = characterId;
            });
        }

        if (restoreScroll && cache.scrollTop !== null) {
            threadEl.scrollTop = Math.min(cache.scrollTop, Math.max(threadEl.scrollHeight - threadEl.clientHeight, 0));
            return;
        }

        if (shouldStickBottom) {
            threadEl.scrollTop = threadEl.scrollHeight;
            return;
        }

        if (keepPosition) {
            threadEl.scrollTop = Math.min(previousScrollTop, Math.max(threadEl.scrollHeight - threadEl.clientHeight, 0));
        }
    }

    function storeActiveConversationScroll() {
        if (!threadEl || !activeDm.slug) return;
        const cache = getMessageCache(activeDm.slug);
        if (!cache || !cache.loaded) return;
        cache.scrollTop = threadEl.scrollTop;
    }

    function applyActiveConversationMeta(room) {
        activeDm.slug = room?.slug || null;
        activeDm.conversationId = room?.roomId || 0;
        activeDm.lastId = getMessageCache(activeDm.slug)?.lastId || 0;
        activeDm.displayName = room?.displayName || room?.slug || null;
        activeDm.myCharacterId = room?.myCharacterId || 0;
        activeDm.otherCharacterId = room?.otherCharacterId || 0;
        activeDm.isBlockedByViewer = !!room?.isBlockedByViewer;

        if (threadHeader) {
            threadHeader.textContent = activeDm.displayName ? `DM: ${activeDm.displayName}` : 'Select a conversation.';
        }

        syncDmBlockToggle();
        setThreadEnabled(!!activeDm.myCharacterId);
        syncActiveConversationHighlight();
    }

    function clearThread() {
        storeActiveConversationScroll();
        stopDmRealtime();
        activeDm.slug = null;
        activeDm.conversationId = 0;
        activeDm.lastId = 0;
        activeDm.displayName = null;
        activeDm.myCharacterId = 0;
        activeDm.otherCharacterId = 0;
        activeDm.isBlockedByViewer = false;
        pollInFlight = false;

        if (threadHeader) threadHeader.textContent = 'Select a conversation.';
        syncDmBlockToggle();
        if (threadEl) threadEl.innerHTML = `<div class="text-[#8f8675]">No conversation selected.</div>`;
        if (inputEl) inputEl.value = '';
        setThreadEnabled(false);
        syncActiveConversationHighlight();
    }

    function fetchConversationMessages(slug, options = {}) {
        if (!slug) return Promise.resolve([]);

        const { reset = false } = options;
        const cache = getMessageCache(slug);
        if (!cache || cache.loading) return Promise.resolve([]);

        cache.loading = true;
        const after = reset ? 0 : (cache.lastId || 0);

        return fetch(`/dms/${encodeURIComponent(slug)}/messages?after=${after}`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            const roomId = parseInt(data?.room?.id, 10) || 0;
            const msgs = data && Array.isArray(data.messages) ? data.messages : [];
            const room = dmRoomsBySlug.get(slug);

            if (room && roomId && room.roomId !== roomId) {
                room.roomId = roomId;
                refreshRoomButton(slug);
            }

            if (reset) {
                replaceCachedMessages(slug, msgs);
            } else {
                appendCachedMessages(slug, msgs);
            }

            cache.loading = false;

            if (activeDm.slug === slug) {
                if (roomId) activeDm.conversationId = roomId;
                clearDmUnread(activeDm.conversationId, slug);
                renderActiveConversation({
                    restoreScroll: reset,
                    keepPosition: !reset,
                    forceBottom: reset && cache.scrollTop === null,
                });
                startDmRealtime();
            }

            if (msgs.length > 0) {
                fetchDmRooms({ showLoading: false });
            }

            return msgs;
        })
        .catch(err => {
            cache.loading = false;
            console.error('DM thread load error:', err);
            if (activeDm.slug === slug && reset && threadEl) {
                threadEl.innerHTML = `<div class="text-red-400">Could not load messages.</div>`;
            }
            return [];
        });
    }

    function pollConversation() {
        if (!activeDm.slug || pollInFlight) return;

        pollInFlight = true;
        const cache = getMessageCache(activeDm.slug);

        fetchConversationMessages(activeDm.slug, { reset: !cache?.loaded })
            .finally(() => {
                pollInFlight = false;
            });
    }

    function startDmPolling() {
        stopDmPolling();
        pollDmTimer = setInterval(() => {
            if (!isOpen() || !activeDm.slug) return;
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
                if (!eventId) return;
                pollConversation();
            });
    }

    function syncDmListRealtimeSubscriptions(rooms) {
        if (!window.Echo || !Array.isArray(rooms)) return;

        rooms.forEach((room) => {
            const conversationId = parseInt(room.roomId || 0, 10) || 0;
            const characterId = parseInt(room.myCharacterId || 0, 10) || 0;

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
                        pollConversation();
                        fetchDmRooms({ showLoading: false });
                        return;
                    }

                    incrementDmUnread(conversationId);
                    fetchDmRooms({ showLoading: false });
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
            fetchDmRooms({ showLoading: false });
        }, 10000);
    }

    function stopListRefresh() {
        if (refreshListTimer) {
            clearInterval(refreshListTimer);
            refreshListTimer = null;
        }
    }

    function openConversation(slug) {
        const room = dmRoomsBySlug.get(slug);
        if (!room) return;

        setLastOpenedDmSlug(slug);

        if (activeDm.slug === slug) {
            const cache = getMessageCache(slug);

            if (cache?.loaded) {
                renderActiveConversation({
                    restoreScroll: true,
                    forceBottom: cache.scrollTop === null,
                });
                fetchConversationMessages(slug, { reset: false });
            } else {
                renderConversationLoading();
                fetchConversationMessages(slug, { reset: true });
            }

            startDmRealtime();
            startDmPolling();
            return;
        }

        storeActiveConversationScroll();
        stopDmRealtime();
        applyActiveConversationMeta(room);
        clearDmUnread(room.roomId, room.slug);

        const cache = getMessageCache(slug);
        if (cache?.loaded) {
            renderActiveConversation({
                restoreScroll: true,
                forceBottom: cache.scrollTop === null,
            });
            fetchConversationMessages(slug, { reset: false });
        } else {
            renderConversationLoading();
            fetchConversationMessages(slug, { reset: true });
        }

        startDmRealtime();
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
            fetchDmRooms({ showLoading: false });
            setTimeout(() => pollConversation(), 150);
        })
        .catch(err => {
            console.error('DM send error:', err);
        });
    }

    window.addEventListener('open-dm-window', (e) => {
        dmWindow.classList.remove('hidden');

        const slug = e?.detail?.slug;
        const name = e?.detail?.name;
        const myCharacterId = parseInt(e?.detail?.my_character_id || 0, 10) || 0;
        const otherCharacterId = parseInt(e?.detail?.other_character_id || 0, 10) || 0;
        const isBlockedByViewer = parseBool(e?.detail?.is_blocked_by_viewer);

        fetchDmRooms({ showLoading: true }).then((rooms) => {
            const existingRoom = slug ? dmRoomsBySlug.get(slug) : null;

            if (slug && !existingRoom && name) {
                dmRoomsBySlug.set(slug, {
                    roomId: 0,
                    slug,
                    updatedAt: null,
                    displayName: name,
                    avatar: '',
                    unreadCount: 0,
                    myCharacterId,
                    otherCharacterId,
                    isBlockedByViewer,
                });
            }

            const defaultSlug = slug || resolveDefaultConversationSlug(rooms);

            if (defaultSlug) {
                openConversation(defaultSlug);
            } else {
                clearThread();
            }
        });

        startListRefresh();
    });

    refreshBtn?.addEventListener('click', () => {
        fetchDmRooms({ showLoading: false });
        if (activeDm.slug) pollConversation();
    });

    closeBtn?.addEventListener('click', () => {
        storeActiveConversationScroll();
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

    threadEl?.addEventListener('scroll', () => {
        storeActiveConversationScroll();
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
