import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

Alpine.data('visitorTracker', (initialPeak, initialDate, wire) => ({
    wire: wire,
    visitors: new Map(),
    leavingTimers: new Map(),
    totalCount: 0,
    authCount: 0,
    peakCount: initialPeak || 0,
    peakDate: initialDate || '',
    connectionError: false,
    channel: null,
    peakChannel: null,
    lastPeakCheck: 0,

    // This component is wrapped in @persist in the footer, so it initializes exactly once per page session and survives
    // wire:navigate untouched. That means the presence subscription below is created a single time; Pusher keeps the
    // socket open across SPA navigation and automatically re-subscribes (re-firing `here`) after any genuine reconnect,
    // so there is no need to leave/rejoin the channel on navigation.
    init() {
        if (!window.Echo) {
            console.error('Echo not initialized');
            this.connectionError = true;
            return;
        }

        this.bindConnectionState();
        this.joinVisitorChannel();
        this.listenForPeakUpdates();
    },

    // Alpine only calls this if the persisted element is ever removed from the DOM entirely (e.g. navigating to a layout
    // variant without a footer). Clean up so we never leak a subscription or pending leave timers in that edge case.
    destroy() {
        this.leavingTimers.forEach((timer) => clearTimeout(timer));
        this.leavingTimers.clear();
        if (this.channel) {
            window.Echo.leave('visitors');
            this.channel = null;
        }
        if (this.peakChannel) {
            window.Echo.leave('peak-visitors');
            this.peakChannel = null;
        }
    },

    joinVisitorChannel() {
        if (this.channel) {
            return;
        }

        try {
            this.channel = window.Echo.join('visitors')
                .here((users) => {
                    this.leavingTimers.forEach((timer) => clearTimeout(timer));
                    this.leavingTimers.clear();
                    this.visitors.clear();
                    users.forEach((user) => {
                        this.visitors.set(user.id, user);
                    });
                    this.updateCounts();
                    this.checkForNewPeak();
                })
                .joining((user) => {
                    if (this.leavingTimers.has(user.id)) {
                        clearTimeout(this.leavingTimers.get(user.id));
                        this.leavingTimers.delete(user.id);
                    }
                    if (!this.visitors.has(user.id)) {
                        this.visitors.set(user.id, user);
                        this.updateCounts();
                        this.checkForNewPeak();
                    }
                })
                .leaving((user) => {
                    const timer = setTimeout(() => {
                        this.visitors.delete(user.id);
                        this.leavingTimers.delete(user.id);
                        this.updateCounts();
                    }, 2000);
                    this.leavingTimers.set(user.id, timer);
                })
                .error((error) => {
                    console.error('Channel error:', error);
                    this.connectionError = true;
                });
        } catch (error) {
            console.error('Failed to join visitors channel:', error);
            this.connectionError = true;
        }
    },

    listenForPeakUpdates() {
        if (this.peakChannel) {
            return;
        }

        this.peakChannel = window.Echo.channel('peak-visitors').listen('PeakVisitorUpdated', (data) => {
            this.peakCount = data.count;
            this.peakDate = data.date;
        });
    },

    updateCounts() {
        this.totalCount = this.visitors.size;
        this.authCount = Array.from(this.visitors.values()).filter((v) => v.type === 'authenticated').length;
    },

    checkForNewPeak() {
        const now = Date.now();
        if (now - this.lastPeakCheck < 1000) return;
        this.lastPeakCheck = now;

        if (this.totalCount > this.peakCount && this.wire) {
            this.wire.updatePeak(this.totalCount);
        }
    },

    // Reflect the live socket state in the UI. Bound once, because init() runs once. We deliberately do not leave and
    // rejoin channels here: on reconnect Pusher transparently re-subscribes every channel it still holds and re-fires
    // `here`, which repopulates the member list. The null guards only cover the unlikely case where the very first
    // join attempt happened before the socket was usable.
    bindConnectionState() {
        const pusher = window.Echo.connector.pusher;

        pusher.connection.bind('error', () => {
            this.connectionError = true;
        });
        pusher.connection.bind('unavailable', () => {
            this.connectionError = true;
        });
        pusher.connection.bind('failed', () => {
            this.connectionError = true;
        });
        pusher.connection.bind('disconnected', () => {
            this.connectionError = true;
        });
        pusher.connection.bind('connected', () => {
            this.connectionError = false;
            if (!this.channel) {
                this.joinVisitorChannel();
            }
            if (!this.peakChannel) {
                this.listenForPeakUpdates();
            }
        });
    },
}));
