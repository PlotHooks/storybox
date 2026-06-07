import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const port = Number(process.env.STORYBOX_VITE_PORT ?? 5173);
const origin = process.env.STORYBOX_VITE_ORIGIN;
const hmrHost = process.env.STORYBOX_VITE_HMR_HOST;
const hmrProtocol = process.env.STORYBOX_VITE_HMR_PROTOCOL;
const hmrClientPort = process.env.STORYBOX_VITE_HMR_CLIENT_PORT
    ? Number(process.env.STORYBOX_VITE_HMR_CLIENT_PORT)
    : undefined;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/live-moderation.js'],
            refresh: true,
        }),
    ],
    server: {
        host: process.env.STORYBOX_VITE_HOST,
        port,
        strictPort: true,
        ...(origin ? { origin } : {}),
        ...((hmrHost || hmrProtocol || hmrClientPort)
            ? {
                hmr: {
                    ...(hmrHost ? { host: hmrHost } : {}),
                    ...(hmrProtocol ? { protocol: hmrProtocol } : {}),
                    ...(hmrClientPort ? { clientPort: hmrClientPort } : {}),
                },
            }
            : {}),
    },
});
