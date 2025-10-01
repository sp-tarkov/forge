<div
    x-data="visitorTracker(@js($peakCount), @js($peakDate))"
    x-init="init"
    class="text-xs text-gray-400"
>
    {{-- Connection error state --}}
    <template x-if="connectionError">
        <div class="flex items-center justify-end space-x-2">
            <div class="relative flex h-2 w-2">
                <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
            </div>
            <div>
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
                <div>
                    <span class="font-medium text-gray-300" x-text="totalCount"></span>
                    <span x-text="totalCount === 1 ? 'user currently online' : 'users currently online'"></span>
                    <template x-if="authCount > 0">
                        <span class="text-gray-500" x-text="`(${authCount} ${authCount === 1 ? 'member' : 'members'})`"></span>
                    </template>
                </div>
            </div>

            {{-- Peak display --}}
            <template x-if="peakCount > 0 && peakDate">
                <div class="text-right mt-1">
                    <span class="text-gray-500">Peak:</span>
                    <span class="font-medium text-gray-400" x-text="peakCount"></span>
                    <span class="text-gray-500" x-text="`on ${peakDate}`"></span>
                </div>
            </template>
        </div>
    </template>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('visitorTracker', (initialPeak, initialDate) => ({
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
                navigationHandlers: null,

                init() {
                    this.connect();
                    this.handleLivewireNavigation();
                },

                connect() {
                    this.disconnect();
                    this.setupEchoListeners();
                    this.joinVisitorChannel();
                    this.listenForPeakUpdates();
                },

                disconnect() {
                    this.leavingTimers.forEach(timer => clearTimeout(timer));
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
                    if (!window.Echo) {
                        console.error('Echo not initialized');
                        this.connectionError = true;
                        return;
                    }

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
                                this.checkForNewPeak();
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
                    if (!window.Echo) return;

                    this.peakChannel = window.Echo.channel('peak-visitors')
                        .listen('PeakVisitorUpdated', (data) => {
                            // Update peak from broadcast (another client found new peak)
                            this.peakCount = data.count;
                            this.peakDate = data.date;
                        });
                },

                updateCounts() {
                    this.totalCount = this.visitors.size;
                    this.authCount = Array.from(this.visitors.values()).filter(v => v.type === 'authenticated').length;
                },

                checkForNewPeak() {
                    // Debounce peak checks (prevent multiple calls within 1 second)
                    const now = Date.now();
                    if (now - this.lastPeakCheck < 1000) return;
                    this.lastPeakCheck = now;

                    // If current count exceeds known peak, update it
                    if (this.totalCount > this.peakCount) {
                        @this.updatePeak(this.totalCount); // Livewire call
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
                            this.joinVisitorChannel();
                        }
                        if (!this.peakChannel) {
                            this.listenForPeakUpdates();
                        }
                    });

                    // Monitor connection state changes for better reconnection handling
                    pusher.connection.bind('state_change', (states) => {
                        // states = {previous: 'oldState', current: 'newState'}
                        if (states.current === 'connected' && states.previous !== 'connected') {
                            this.connectionError = false;
                            if (!this.channel) {
                                this.joinVisitorChannel();
                            }
                            if (!this.peakChannel) {
                                this.listenForPeakUpdates();
                            }
                        }
                    });
                },

                handleLivewireNavigation() {
                    if (!this.navigationHandlers) {
                        this.navigationHandlers = {
                            navigated: () => {
                                setTimeout(() => {
                                    this.connect();
                                }, 100);
                            },
                            navigating: () => {
                                this.disconnect();
                            }
                        };
                        document.addEventListener('livewire:navigated', this.navigationHandlers.navigated);
                        document.addEventListener('livewire:navigating', this.navigationHandlers.navigating);
                    }
                }
            }));
        });
    </script>
</div>
