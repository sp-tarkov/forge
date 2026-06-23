const offlineDebounce = 2000;
const offlineTimers = new Map();
let presenceChannelJoined = false;
let userPrivateChannel = null;

// Join the global presence channel for tracking online users. This is the single subscription to presence.online; the
// component deliberately does not also declare native Livewire echo-presence listeners for it. Because the component is
// persisted across wire:navigate, this handler runs once and its callbacks stay bound to a live $wire.
$wire.on('join-presence-channel', () => {
    if (!window.Echo || presenceChannelJoined) return;

    presenceChannelJoined = true;
    window.Echo.join('presence.online')
        .here((users) => $wire.handleUsersHere(users))
        .joining((user) => {
            // Cancel a pending offline check if the user rejoins inside the debounce window.
            if (offlineTimers.has(user.id)) {
                clearTimeout(offlineTimers.get(user.id));
                offlineTimers.delete(user.id);
            }
            $wire.handleUserJoining(user);
        })
        .leaving((user) => $wire.handleUserLeaving(user))
        .error((error) => console.error('Nav - Presence channel error:', error));
});

// Mark a user offline after a debounce window, cancelling if they rejoin first.
$wire.on('debounce-user-offline', ({ userId }) => {
    if (offlineTimers.has(userId)) {
        clearTimeout(offlineTimers.get(userId));
    }

    const timerId = setTimeout(() => {
        $wire.dispatch('check-user-offline', { userId });
        offlineTimers.delete(userId);
    }, offlineDebounce);

    offlineTimers.set(userId, timerId);
});

// Subscribe to the per-user private channel for message and conversation events. This is the single subscription to
// user.{id}, handled here rather than via native Livewire echo-private listeners (which never registered because the
// server-side userId property was never set).
$wire.on('set-user-id', ({ userId }) => {
    if (!window.Echo || !userId) return;

    if (userPrivateChannel) {
        window.Echo.leave(`user.${userPrivateChannel}`);
    }

    userPrivateChannel = userId;
    window.Echo.private(`user.${userId}`)
        .listen('MessageSent', (e) => $wire.handleNewMessage(e))
        .listen('ConversationUpdated', (e) => $wire.handleConversationUpdated(e));
});
