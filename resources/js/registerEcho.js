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
});

// Handle WebSocket connection errors
window.Echo.connector.pusher.connection.bind('error', function(err) {
    if (window.Livewire) {
        window.Livewire.dispatchTo('visitors', 'connection-error');
    }
});
window.Echo.connector.pusher.connection.bind('unavailable', function() {
    if (window.Livewire) {
        window.Livewire.dispatchTo('visitors', 'connection-error');
    }
});
window.Echo.connector.pusher.connection.bind('failed', function() {
    if (window.Livewire) {
        window.Livewire.dispatchTo('visitors', 'connection-error');
    }
});
window.Echo.connector.pusher.connection.bind('disconnected', function() {
    if (window.Livewire) {
        window.Livewire.dispatchTo('visitors', 'connection-error');
    }
});

// Handle a successful connection/reconnection
window.Echo.connector.pusher.connection.bind('connected', function() {
    if (window.Livewire) {
        window.Livewire.dispatchTo('visitors', 'connection-restored');
    }
});
