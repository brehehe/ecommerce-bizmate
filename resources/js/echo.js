import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
if (typeof window !== 'undefined') {
    window.Pusher = Pusher;

    // Enable Pusher debug logging (remove in production)
    if (import.meta.env.DEV) {
        Pusher.logToConsole = true;
    }

    if (import.meta.env.VITE_REVERB_APP_KEY) {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS:
                (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        window.Echo.connector.pusher.connection.bind('connected', () => {
            console.log('[Reverb] ✅ WebSocket connected');
        });

        window.Echo.connector.pusher.connection.bind('disconnected', () => {
            console.warn('[Reverb] ❌ WebSocket disconnected');
        });

        window.Echo.connector.pusher.connection.bind('error', (err) => {
            console.error('[Reverb] ⚠️ WebSocket error', err);
        });
    }
}
