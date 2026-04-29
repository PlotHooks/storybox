import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            const characterId = window.StoryboxChannelCharacters?.[channel.name]
                ?? window.Storybox?.activeCharacterId?.()
                ?? 0;

            window.axios.post('/broadcasting/auth', {
                socket_id: socketId,
                channel_name: channel.name,
                character_id: characterId,
            })
                .then((response) => callback(false, response.data))
                .catch((error) => callback(true, error));
        },
    }),
});
