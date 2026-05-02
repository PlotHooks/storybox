import './bootstrap';

window.liveModerationFeed = function liveModerationFeed(initialMessages) {
    return {
        messages: Array.isArray(initialMessages) ? initialMessages : [],
        seen: new Set(),

        start() {
            this.messages = this.messages.slice(0, 100);
            this.messages.forEach((message) => this.seen.add(Number(message.id)));

            if (!window.Echo) return;

            window.Echo.private('moderation.messages')
                .listen('.message.created', (event) => {
                    const id = Number(event.id);

                    if (!id || this.seen.has(id)) return;

                    this.seen.add(id);
                    this.messages.unshift(event);
                    this.messages = this.messages.slice(0, 100);
                });
        },

        formatTime(value) {
            if (!value) return '';

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) return '';

            return date.toLocaleString();
        },
    };
};
