let currentConversationChannel = null;
$wire.on('join-conversation-presence', ({
    conversationHash
}) => {
    if (!window.Echo || !conversationHash) return;
    if (currentConversationChannel) {
        window.Echo.leave(currentConversationChannel);
    }
    currentConversationChannel = `presence.conversation.${conversationHash}`;
    window.Echo.join(currentConversationChannel)
        .leaving((user) => $wire.handleUserLeavingConversation(user))
        .listen('UserStartedTyping', (e) => $wire.handleUserStartedTyping(e))
        .listen('UserStoppedTyping', (e) => $wire.handleUserStoppedTyping(e));
});

// Handle leaving presence channel when archiving or clearing conversation
$wire.on('leave-conversation-presence', ({
    conversationHash
}) => {
    if (!window.Echo || !conversationHash) return;
    if (currentConversationChannel === `presence.conversation.${conversationHash}`) {
        window.Echo.leave(currentConversationChannel);
        currentConversationChannel = null;
    }
});

// Listen for blocking events on the user's private channel
const currentUserId = $wire.currentUserId;
if (window.Echo && currentUserId) {
    window.Echo.private(`user.${currentUserId}`)
        .listen('UserBlocked', (e) => {
            $wire.handleUserBlocked(e.blocker_id);
        })
        .listen('UserUnblocked', (e) => {
            $wire.handleUserUnblocked(e.unblocker_id);
        });
}
