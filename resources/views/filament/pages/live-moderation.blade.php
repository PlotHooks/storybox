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
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-mono" x-text="'#' + message.id"></span>
                            <span x-text="message.room_label"></span>
                            <span x-text="message.room_type === 'dm' ? 'DM' : 'Room'"></span>
                            <span x-text="formatTime(message.created_at)"></span>
                            <span
                                x-show="message.deleted"
                                class="rounded bg-danger-100 px-1.5 py-0.5 font-medium text-danger-700 dark:bg-danger-500/20 dark:text-danger-300"
                            >
                                deleted
                            </span>
                        </div>

                        <div class="text-sm font-medium text-gray-950 dark:text-white">
                            <span x-text="message.character_name || ('Character #' + message.character_id)"></span>
                            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                                <span x-text="'user #' + message.user_id"></span>
                                <span x-show="message.user_name" x-text="' · ' + message.user_name"></span>
                            </span>
                        </div>

                        <p class="break-words text-sm text-gray-700 dark:text-gray-200" x-text="message.preview"></p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                            :href="message.view_url"
                        >
                            View context
                        </a>

                        <button
                            type="button"
                            class="rounded border border-danger-300 px-2 py-1 text-xs font-medium text-danger-700 hover:bg-danger-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-danger-700 dark:text-danger-300 dark:hover:bg-danger-950"
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
