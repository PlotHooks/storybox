<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $room->name }} History | Storybox</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#050505] text-[#e9dcc2] antialiased">
        <div id="room-history-page" tabindex="0" class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(62,42,18,0.55),rgba(7,7,7,0.96)_45%,#050505_100%)] outline-none">
            <div class="w-full px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-10">
                <div class="mx-auto mb-5 flex w-full max-w-none flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('rooms.show', $room->slug) }}" onclick="window.close(); setTimeout(() => { if (!window.closed) { window.location.href = this.href; } }, 100); return false;" class="inline-flex items-center rounded-full border border-[#5a431f] bg-[#141416]/95 px-4 py-2 text-sm font-medium text-[#f2dfb5] hover:bg-[#191511]">
                            Back to Storybox
                        </a>
                        <a href="{{ route('rooms.profile.show', $room->slug) }}" class="inline-flex items-center rounded-full border border-[#5a431f] bg-[#141416]/95 px-4 py-2 text-sm font-medium text-[#f2dfb5] hover:bg-[#191511]">
                            Room Profile
                        </a>
                        @if ($canManageRoom)
                            <a href="{{ route('rooms.profile.edit', $room->slug) }}" class="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-amber-500/20">
                                Edit Profile
                            </a>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-sm text-[#c8b793]">
                        <div class="rounded-full border border-[#3a2f1e] bg-[#0b0b0c]/90 px-4 py-2">
                            {{ $room->name }}
                        </div>
                        <div class="rounded-full border border-[#3a2f1e] bg-[#0b0b0c]/90 px-4 py-2">
                            {{ $selectedDay->format('l, F j, Y') }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
                    <aside class="overflow-hidden border-y border-[#3a2f1e] bg-[#0b0b0c]/95 shadow-[0_30px_80px_rgba(0,0,0,0.45)] sm:rounded-[2rem] sm:border">
                        <div class="border-b border-[#3a2f1e] px-5 py-5">
                            <div class="text-xs uppercase tracking-[0.35em] text-[#c8ac75]">Room History</div>
                            <h1 class="mt-2 text-2xl font-semibold text-[#fff2cc]">Transcript Navigator</h1>
                            <p class="mt-3 text-sm leading-6 text-[#cdbb98]">
                                Browse up to 30 days of room messages. Active days show message counts and open read-only transcripts.
                            </p>
                        </div>

                        <div class="border-b border-[#3a2f1e] px-5 py-4">
                            <div class="grid grid-cols-2 gap-2">
                                @if ($previousActiveDayUrl)
                                    <a href="{{ $previousActiveDayUrl }}" class="inline-flex items-center justify-center rounded-xl border border-[#332817] bg-[#141416] px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]">
                                        Previous Active Day
                                    </a>
                                @else
                                    <span class="inline-flex items-center justify-center rounded-xl border border-dashed border-[#332817] bg-[#101012] px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-[#6f675b] opacity-70">
                                        Previous Active Day
                                    </span>
                                @endif

                                @if ($nextActiveDayUrl)
                                    <a href="{{ $nextActiveDayUrl }}" class="inline-flex items-center justify-center rounded-xl border border-[#332817] bg-[#141416] px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]">
                                        Next Active Day
                                    </a>
                                @else
                                    <span class="inline-flex items-center justify-center rounded-xl border border-dashed border-[#332817] bg-[#101012] px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-[#6f675b] opacity-70">
                                        Next Active Day
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="max-h-[70dvh] overflow-y-auto px-3 py-3">
                            <div class="space-y-2">
                                @foreach ($calendarDays as $day)
                                    @if ($day['is_active'])
                                        <a
                                            href="{{ $day['url'] }}"
                                            class="flex items-center justify-between gap-3 rounded-2xl border px-3 py-3 transition {{ $day['is_selected'] ? 'border-amber-500/40 bg-amber-500/10 text-amber-100 shadow-[inset_0_0_0_1px_rgba(245,158,11,0.12)]' : 'border-[#332817] bg-[#141416] text-[#d6c8ad] hover:border-amber-500/40 hover:text-[#f2dfb5]' }}"
                                        >
                                            <div>
                                                <div class="text-[10px] font-semibold uppercase tracking-[0.2em] {{ $day['is_selected'] ? 'text-amber-300' : 'text-[#8f8675]' }}">{{ $day['month_label'] }}</div>
                                                <div class="mt-1 text-xl font-semibold">{{ $day['day_number'] }}</div>
                                                <div class="mt-1 text-xs {{ $day['is_selected'] ? 'text-amber-200/80' : 'text-[#a49370]' }}">{{ $day['label'] }}</div>
                                            </div>
                                            <div class="rounded-full border border-[#4b3720] bg-[#0b0b0c] px-3 py-1 text-xs font-semibold text-[#f2dfb5]">
                                                {{ $day['message_count'] }}
                                            </div>
                                        </a>
                                    @else
                                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-dashed border-[#2b2419] bg-[#0e0e10] px-3 py-3 text-[#6f675b] opacity-75">
                                            <div>
                                                <div class="text-[10px] font-semibold uppercase tracking-[0.2em]">{{ $day['month_label'] }}</div>
                                                <div class="mt-1 text-xl font-semibold">{{ $day['day_number'] }}</div>
                                                <div class="mt-1 text-xs">{{ $day['label'] }}</div>
                                            </div>
                                            <div class="text-[11px] uppercase tracking-[0.16em]">Empty</div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </aside>

                    <section class="overflow-hidden border-y border-[#3a2f1e] bg-[#0b0b0c]/95 shadow-[0_30px_80px_rgba(0,0,0,0.45)] sm:rounded-[2rem] sm:border">
                        <div class="border-b border-[#3a2f1e] px-5 py-5 sm:px-6">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.35em] text-[#c8ac75]">Selected Day</div>
                                    <h2 class="mt-2 text-3xl font-semibold text-[#fff2cc]">{{ $selectedDay->format('l, F j, Y') }}</h2>
                                    <p class="mt-3 text-sm leading-6 text-[#cdbb98]">
                                        @if ($selectedDayHasMessages)
                                            {{ $selectedDayMessageCount }} {{ $selectedDayMessageCount === 1 ? 'message' : 'messages' }} available in read-only history.
                                        @else
                                            No room messages were recorded for this day within the current 30-day history window.
                                        @endif
                                    </p>
                                </div>
                                <div id="history-selection-toolbar" class="hidden flex-wrap items-center gap-2 rounded-2xl border border-amber-500/40 bg-[#120f0a]/95 px-3 py-2 text-sm text-amber-100 shadow-[0_20px_60px_rgba(0,0,0,0.45)]">
                                    <span id="history-selection-count" class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-300">0 selected</span>
                                    <button type="button" data-history-action="copy-transcript" class="rounded-lg border border-[#5a431f] bg-[#191511] px-3 py-1.5 text-xs font-semibold text-[#f2dfb5] hover:border-amber-500/40 hover:text-white">Copy Transcript</button>
                                    <button type="button" data-history-action="copy-storybox" class="rounded-lg border border-[#5a431f] bg-[#191511] px-3 py-1.5 text-xs font-semibold text-[#f2dfb5] hover:border-amber-500/40 hover:text-white">Copy for Storybox</button>
                                    <button type="button" data-history-action="copy-table" class="rounded-lg border border-[#5a431f] bg-[#191511] px-3 py-1.5 text-xs font-semibold text-[#f2dfb5] hover:border-amber-500/40 hover:text-white">Copy Table</button>
                                    <button type="button" data-history-action="download-csv" class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-500/20">Download CSV</button>
                                </div>
                            </div>
                        </div>

                        <div id="history-transcript" class="relative min-h-[60dvh] bg-[#070707] bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,0.035),transparent_34rem)] px-3 py-4 sm:px-5 sm:py-5">
                            @if ($messages->isEmpty())
                                <div class="rounded-3xl border border-dashed border-[#332817] bg-[#101012]/80 px-6 py-10 text-center text-sm text-[#8f8675]">
                                    No transcript is available for this day.
                                </div>
                            @else
                                @foreach ($messages as $message)
                                    @php
                                        $c = $message->character;
                                        $name = $c?->name ?? 'Unknown';
                                        $messageCharacterId = (int) ($message->character_id ?? 0);
                                        $previous = $loop->index > 0 ? $messages[$loop->index - 1] : null;
                                        $previousCharacterId = (int) ($previous?->character_id ?? 0);
                                        $isDice = ($message->type ?? \App\Models\Message::TYPE_NORMAL) === \App\Models\Message::TYPE_DICE;
                                        $isDeleted = (bool) $message->deleted_at;
                                        $isEmote = ($message->type ?? \App\Models\Message::TYPE_NORMAL) === \App\Models\Message::TYPE_EMOTE;
                                        $inlineMessage = $isEmote || $isDice;
                                        $isGrouped = $messageCharacterId > 0 && $previousCharacterId === $messageCharacterId;
                                        $avatar = $c?->externalAvatarUrl();
                                        $initial = strtoupper(substr($name, 0, 1));
                                        $absoluteTimestamp = $message->created_at?->copy()->setTimezone(config('app.timezone'))->format('M j, Y g:i:s A T') ?? '';
                                    @endphp

                                    <div
                                        class="history-message-row group relative flex gap-2 rounded-xl px-2 py-1.5 transition {{ $isGrouped ? 'border-0' : 'border-t border-[#16120c]' }}"
                                        data-history-message-id="{{ $message->id }}"
                                        data-history-index="{{ $loop->index }}"
                                        aria-selected="false"
                                    >
                                        <div class="w-7 shrink-0">
                                            @unless ($isGrouped || $inlineMessage)
                                                @if ($avatar)
                                                    <img src="{{ $avatar }}" alt="{{ $name }} avatar" loading="lazy" referrerpolicy="no-referrer" class="h-7 w-7 rounded-full object-cover">
                                                @else
                                                    <div class="flex h-7 w-7 items-center justify-center rounded-full border border-[#332817] bg-[#0b0b0c] text-xs font-semibold text-[#8f8675]">
                                                        {{ $initial }}
                                                    </div>
                                                @endif
                                            @endunless
                                        </div>

                                        <div class="min-w-0 flex-1 pr-2">
                                            @unless ($isGrouped || $inlineMessage)
                                                <div class="mb-0 flex flex-wrap items-baseline gap-2">
                                                    <span class="msg-name text-base font-bold leading-none text-[#f2dfb5]">{{ $name }}</span>
                                                    <span class="text-[11px] text-[#8f8675]">{{ $absoluteTimestamp }}</span>
                                                    @if ($isDeleted)
                                                        <span class="text-[10px] text-[#8f8675]">(deleted)</span>
                                                    @elseif ($message->updated_at && ! $message->updated_at->equalTo($message->created_at))
                                                        <span class="text-[10px] text-[#8f8675]">(edited)</span>
                                                    @endif
                                                </div>
                                            @endunless

                                            <div class="msg-body-wrapper mt-0 text-base font-medium leading-6">
                                                @if ($inlineMessage && ! $isDeleted)
                                                    <span class="leading-6">
                                                        <span class="msg-name text-base font-bold leading-6 align-baseline text-[#f2dfb5]">{{ $name }}</span>&nbsp;<span class="msg-body text-base font-medium text-[#d6c8ad] leading-6 whitespace-pre-line">{!! $message->rendered_body_html !!}</span><span class="ml-2 text-[11px] text-[#8f8675]">{{ $absoluteTimestamp }}</span>
                                                    </span>
                                                @else
                                                    <span class="msg-body text-base font-medium text-[#d6c8ad] leading-6 whitespace-pre-line">{!! $message->rendered_body_html !!}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <script>
            const historyRoot = document.getElementById('room-history-page');
            const historyTranscript = document.getElementById('history-transcript');
            const selectionToolbar = document.getElementById('history-selection-toolbar');
            const selectionCount = document.getElementById('history-selection-count');
            const historyRows = @json($historyExportRows);
            const rowDataById = new Map(historyRows.map((row) => [Number(row.id), row]));
            const orderedIds = historyRows.map((row) => Number(row.id));
            const selectedIds = new Set();
            let lastSelectedIndex = null;

            function orderedSelectedRows() {
                return orderedIds
                    .filter((id) => selectedIds.has(id))
                    .map((id) => rowDataById.get(id))
                    .filter(Boolean);
            }

            function updateToolbar() {
                const count = selectedIds.size;
                if (selectionToolbar) {
                    selectionToolbar.classList.toggle('hidden', count === 0);
                }
                if (selectionCount) {
                    selectionCount.textContent = `${count} selected`;
                }
            }

            function updateRowSelectionState() {
                document.querySelectorAll('[data-history-message-id]').forEach((row) => {
                    const id = Number(row.dataset.historyMessageId || 0);
                    const isSelected = selectedIds.has(id);
                    row.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    row.classList.toggle('bg-amber-500/10', isSelected);
                    row.classList.toggle('ring-1', isSelected);
                    row.classList.toggle('ring-amber-500/40', isSelected);
                });
                updateToolbar();
            }

            function clearSelection() {
                selectedIds.clear();
                lastSelectedIndex = null;
                updateRowSelectionState();
            }

            function selectRange(fromIndex, toIndex) {
                const start = Math.min(fromIndex, toIndex);
                const end = Math.max(fromIndex, toIndex);
                selectedIds.clear();
                for (let index = start; index <= end; index += 1) {
                    selectedIds.add(orderedIds[index]);
                }
            }

            function toggleSelection(id) {
                if (selectedIds.has(id)) {
                    selectedIds.delete(id);
                } else {
                    selectedIds.add(id);
                }
            }

            function selectSingle(id) {
                selectedIds.clear();
                selectedIds.add(id);
            }

            historyTranscript?.addEventListener('click', (event) => {
                const row = event.target.closest('[data-history-message-id]');
                if (!row) return;

                historyRoot?.focus();

                const id = Number(row.dataset.historyMessageId || 0);
                const index = Number(row.dataset.historyIndex || -1);
                if (!id || index < 0) return;

                if (event.shiftKey && lastSelectedIndex !== null) {
                    selectRange(lastSelectedIndex, index);
                } else if (event.metaKey || event.ctrlKey) {
                    toggleSelection(id);
                    lastSelectedIndex = index;
                } else {
                    selectSingle(id);
                    lastSelectedIndex = index;
                }

                updateRowSelectionState();
            });

            historyRoot?.addEventListener('keydown', async (event) => {
                const focusedInsideHistory = document.activeElement && historyRoot.contains(document.activeElement);
                if (!focusedInsideHistory) return;

                if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'a') {
                    event.preventDefault();
                    orderedIds.forEach((id) => selectedIds.add(id));
                    updateRowSelectionState();
                    return;
                }

                if (event.key === 'Escape') {
                    clearSelection();
                }
            });

            async function copyText(text) {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(text);
                    return;
                }

                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', 'readonly');
                textarea.style.position = 'absolute';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
            }

            function formatTranscript(rows) {
                return rows.map((row) => row.transcript_line || '').join('\n');
            }

            function formatStorybox(rows) {
                return rows.map((row) => row.storybox_line || '').join('\n');
            }

            function formatTsv(rows) {
                const headers = ['timestamp', 'character_name', 'character_id', 'message_type', 'body', 'roll_expression', 'roll_result', 'edited', 'deleted'];
                const lines = [headers.join('\t')];

                rows.forEach((row) => {
                    const values = [
                        row.timestamp ?? '',
                        row.character_name ?? '',
                        row.character_id ?? '',
                        row.message_type ?? '',
                        row.body ?? '',
                        row.roll_expression ?? '',
                        row.roll_result ?? '',
                        row.edited ? '1' : '0',
                        row.deleted ? '1' : '0',
                    ].map((value) => String(value).replace(/[\r\n\t]+/g, ' '));

                    lines.push(values.join('\t'));
                });

                return lines.join('\n');
            }

            function escapeCsvValue(value) {
                const normalized = String(value ?? '').replace(/[\r\n]+/g, ' ');
                return `"${normalized.replace(/"/g, '""')}"`;
            }

            function formatCsv(rows) {
                const headers = ['timestamp', 'character_name', 'character_id', 'message_type', 'body', 'roll_expression', 'roll_result', 'edited', 'deleted'];
                const lines = [headers.map(escapeCsvValue).join(',')];

                rows.forEach((row) => {
                    const values = [
                        row.timestamp ?? '',
                        row.character_name ?? '',
                        row.character_id ?? '',
                        row.message_type ?? '',
                        row.body ?? '',
                        row.roll_expression ?? '',
                        row.roll_result ?? '',
                        row.edited ? '1' : '0',
                        row.deleted ? '1' : '0',
                    ];

                    lines.push(values.map(escapeCsvValue).join(','));
                });

                return lines.join('\n');
            }

            function downloadCsv(contents, filename) {
                const blob = new Blob([contents], { type: 'text/csv;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            }

            document.querySelectorAll('[data-history-action]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const rows = orderedSelectedRows();
                    if (!rows.length) return;

                    const action = button.dataset.historyAction;

                    if (action === 'copy-transcript') {
                        await copyText(formatTranscript(rows));
                        return;
                    }

                    if (action === 'copy-storybox') {
                        await copyText(formatStorybox(rows));
                        return;
                    }

                    if (action === 'copy-table') {
                        await copyText(formatTsv(rows));
                        return;
                    }

                    if (action === 'download-csv') {
                        downloadCsv(formatCsv(rows), `{{ $room->slug }}-history-{{ $selectedDayString }}.csv`);
                    }
                });
            });
        </script>
    </body>
</html>
