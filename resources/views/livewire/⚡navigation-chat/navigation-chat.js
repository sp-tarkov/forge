const offlineDeboune = 2000;
const offlineTimers = new Map();
let navPresenceChannelJoined = false;
let userPrivateChannel = null;

// Join the global presence channel for tracking online users
$wire.on('join-presence-channel', () => {
    if (!window.Echo) return;

    const existingChannel = window.Echo.connector.channels['presence-presence.online'];
    if (existingChannel?.subscription?.members) {
        // Already joined, get current members
        const users = [];
        existingChannel.subscription.members.each((member) => {
            users.push({
                id: member.id,
                name: member.info.name,
                profile_photo_url: member.info.profile_photo_url
            });
        });
        $wire.handleUsersHere(users);
    } else if (!navPresenceChannelJoined) {
        // Not joined yet, join the channel
        navPresenceChannelJoined = true;
        window.Echo.join('presence.online')
            .here((users) => $wire.handleUsersHere(users))
            .joining((user) => {
                // Clear any pending offline timer
                if (offlineTimers.has(user.id)) {
                    clearTimeout(offlineTimers.get(user.id));
                    offlineTimers.delete(user.id);
                }
                $wire.handleUserJoining(user);
            })
            .leaving((user) => $wire.handleUserLeaving(user))
            .error((error) => console.error('Nav - Presence channel error:', error));
    }
});

// Handle debounced offline check
$wire.on('debounce-user-offline', ({
    userId
}) => {
    // Clear existing timer if any
    if (offlineTimers.has(userId)) {
        clearTimeout(offlineTimers.get(userId));
    }

    // Set new timer
    const timerId = setTimeout(() => {
        $wire.dispatch('check-user-offline', {
            userId
        });
        offlineTimers.delete(userId);
    }, offlineDeboune);

    offlineTimers.set(userId, timerId);
});

// Set up private channel for user-specific events
$wire.on('set-user-id', ({
    userId
}) => {
    if (!window.Echo || !userId) return;

    // Clean up existing channel
    if (userPrivateChannel) {
        window.Echo.leave(`user.${userPrivateChannel}`);
    }

    userPrivateChannel = userId;
    window.Echo.private(`user.${userId}`)
        .listen('MessageSent', (e) => $wire.handleNewMessage(e))
        .listen('ConversationUpdated', (e) => $wire.handleConversationUpdated(e));
});
