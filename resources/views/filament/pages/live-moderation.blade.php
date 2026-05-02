<x-filament-panels::page>
    @vite('resources/js/live-moderation.js')

    <div
        x-data="liveModerationFeed(@js($messages))"
        x-init="start()"
        class="space-y-3"
    >
        <template x-if="messages.length === 0">
            <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                No recent messages.
            </div>
        </template>

        <template x-for="message in messages" :key="message.id">
            <div
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-colors dark:border-gray-800 dark:bg-gray-900"
                :class="message._isNew ? 'border-primary-300 bg-primary-50/40 dark:border-primary-700 dark:bg-primary-950/20' : ''"
            >
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0 flex-1 space-y-3">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            <span class="font-mono normal-case tracking-normal" x-text="'#' + message.id"></span>
                            <span class="max-w-full truncate normal-case tracking-normal text-gray-500 dark:text-gray-400" x-text="message.room_label"></span>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500 dark:bg-gray-800 dark:text-gray-400" x-text="message.room_type === 'dm' ? 'DM' : 'Room'"></span>
                            <span class="normal-case tracking-normal" x-text="formatTime(message.created_at)"></span>
                            <span
                                x-show="message._isNew"
                                class="rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300"
                            >
                                new
                            </span>
                            <span
                                x-show="message.deleted"
                                class="rounded bg-danger-100 px-1.5 py-0.5 text-[10px] font-semibold text-danger-700 dark:bg-danger-500/20 dark:text-danger-300"
                            >
                                deleted
                            </span>
                        </div>

                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span class="text-base font-semibold leading-6 text-gray-950 dark:text-white" x-text="message.character_name || ('Character #' + message.character_id)"></span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="'user #' + message.user_id"></span>
                                <span x-show="message.user_name" x-text="' · ' + message.user_name"></span>
                            </span>
                        </div>

                        <p
                            class="max-h-24 overflow-hidden whitespace-pre-wrap break-words rounded-md bg-gray-50 px-3 py-2 text-sm leading-6 text-gray-800 dark:bg-gray-950/50 dark:text-gray-200"
                            x-text="message.preview"
                        ></p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <a
                            class="inline-flex items-center rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                            :href="message.view_url"
                        >
                            View context
                        </a>

                        <button
                            type="button"
                            class="inline-flex items-center rounded-full border border-danger-300 px-3 py-1 text-xs font-semibold text-danger-700 transition hover:bg-danger-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-danger-700 dark:text-danger-300 dark:hover:bg-danger-950"
                            :disabled="message.deleted"
                            x-on:click="$wire.deleteMessage(message.id).then(() => { message.deleted = true })"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-filament-panels::page>
