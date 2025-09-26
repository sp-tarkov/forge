<div id="visitor-tracker"
     class="text-right"
     x-data="visitorTracker"
     x-init="init"
     data-peak-count="{{ $peakCount }}"
     data-peak-date="{{ $peakDate }}">

    {{-- Connection error state --}}
    <template x-if="connectionError">
        <div class="flex items-center justify-end space-x-2">
            <div class="relative flex h-2 w-2">
                <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
            </div>
            <div class="text-xs text-gray-400">
                <span class="text-red-400">Connection error</span>
            </div>
        </div>
    </template>

    {{-- Normal state with visitor count --}}
    <template x-if="!connectionError">
        <div>
            <div class="flex items-center justify-end space-x-2">
                <div class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </div>
                <div class="text-xs text-gray-400">
                    <span class="font-medium" x-text="totalVisitorCount"></span>
                    <span x-text="`${pluralize('user', totalVisitorCount)} currently online`"></span>
                    <template x-if="authUserCount > 0">
                        <span class="text-gray-500" x-text="`(${authUserCount} ${pluralize('member', authUserCount)})`"></span>
                    </template>
                </div>
            </div>
            <template x-if="peakCount > 0 && peakDate">
                <div class="text-xs text-gray-400 mt-1">
                    <span class="text-gray-500">Peak:</span>
                    <span class="font-medium" x-text="peakCount"></span>
                    <span class="text-gray-500" x-text="`on ${peakDate}`"></span>
                </div>
            </template>
        </div>
    </template>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('visitorTracker', () => ({
        visitors: new Map(),
        leavingTimers: new Map(), // Track pending leave timers
        totalVisitorCount: 0,
        authUserCount: 0,
        peakCount: 0,
        peakDate: '',
        connectionError: false,
        channel: null,
        peakChannel: null,

        init() {
            // Load initial peak data from data attributes
            this.peakCount = parseInt(this.$el.dataset.peakCount || '0');
            this.peakDate = this.$el.dataset.peakDate || '';

            // Initial connection
            this.connect();

            // Handle Livewire navigation
            this.handleLivewireNavigation();
        },

        connect() {
            // Clean up existing connections first
            this.disconnect();

            // Setup Echo listeners
            this.setupEchoListeners();

            // Join the visitors presence channel
            this.joinChannel();

            // Listen for peak updates
            this.listenForPeakUpdates();
        },

        disconnect() {
            // Clear any pending leave timers
            this.leavingTimers.forEach(timer => clearTimeout(timer));
            this.leavingTimers.clear();

            // Leave existing channels
            if (this.channel) {
                window.Echo.leave('visitors');
                this.channel = null;
            }
            if (this.peakChannel) {
                window.Echo.leave('peak-visitors');
                this.peakChannel = null;
            }
        },

        handleLivewireNavigation() {
            // Store bound functions so we can remove them if needed
            if (!this.navigationHandlers) {
                this.navigationHandlers = {
                    navigated: () => {
                        // Give Echo a moment to stabilize after navigation
                        setTimeout(() => {
                            this.connect();
                        }, 100);
                    },
                    navigating: () => {
                        this.disconnect();
                    }
                };

                // Reconnect on Livewire navigation
                document.addEventListener('livewire:navigated', this.navigationHandlers.navigated);

                // Clean up on navigate away
                document.addEventListener('livewire:navigating', this.navigationHandlers.navigating);
            }
        },

        setupEchoListeners() {
            if (!window.Echo) {
                console.error('Echo not initialized');
                this.connectionError = true;
                return;
            }

            const pusher = window.Echo.connector.pusher;

            // Remove existing bindings to prevent duplicates
            pusher.connection.unbind('error');
            pusher.connection.unbind('unavailable');
            pusher.connection.unbind('failed');
            pusher.connection.unbind('disconnected');
            pusher.connection.unbind('connected');
            pusher.connection.unbind('state_change');

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
                // Rejoin channels when connection is restored
                if (!this.channel) {
                    this.joinChannel();
                }
                if (!this.peakChannel) {
                    this.listenForPeakUpdates();
                }
            });

            // Monitor connection state changes for better reconnection handling
            pusher.connection.bind('state_change', (states) => {
                // states = {previous: 'oldState', current: 'newState'}
                if (states.current === 'connected' && states.previous !== 'connected') {
                    // Connection was restored
                    this.connectionError = false;
                    // Ensure we're in the channels
                    if (!this.channel) {
                        this.joinChannel();
                    }
                    if (!this.peakChannel) {
                        this.listenForPeakUpdates();
                    }
                }
            });
        },

        joinChannel() {
            if (!window.Echo) return;

            try {
                this.channel = window.Echo.join('visitors')
                    .here((users) => {
                        // Clear any pending leave timers
                        this.leavingTimers.forEach(timer => clearTimeout(timer));
                        this.leavingTimers.clear();

                        // Reset visitors list with current users
                        this.visitors.clear();
                        users.forEach(user => {
                            this.visitors.set(user.id, user);
                        });
                        this.updateCounts();
                    })
                    .joining((user) => {
                        // If user was pending leave, cancel it
                        if (this.leavingTimers.has(user.id)) {
                            clearTimeout(this.leavingTimers.get(user.id));
                            this.leavingTimers.delete(user.id);
                        }
                        // Add user if not already present
                        if (!this.visitors.has(user.id)) {
                            this.visitors.set(user.id, user);
                            this.updateCounts();
                        }
                    })
                    .leaving((user) => {
                        // Debounce the leave action by 2 seconds
                        const timer = setTimeout(() => {
                            this.visitors.delete(user.id);
                            this.leavingTimers.delete(user.id);
                            this.updateCounts();
                        }, 2000);

                        this.leavingTimers.set(user.id, timer);
                    });
            } catch (error) {
                console.error('Failed to join visitors channel:', error);
                this.connectionError = true;
            }
        },

        listenForPeakUpdates() {
            if (!window.Echo) return;

            this.peakChannel = window.Echo.channel('peak-visitors')
                .listen('PeakVisitorUpdated', (data) => {
                    this.peakCount = data.count;
                    this.peakDate = data.date;
                });
        },

        updateCounts() {
            this.totalVisitorCount = this.visitors.size;
            this.authUserCount = Array.from(this.visitors.values())
                .filter(v => v.type === 'authenticated').length;
        },

        pluralize(word, count) {
            return count === 1 ? word : word + 's';
        }
    }));
});
</script>
@endpush