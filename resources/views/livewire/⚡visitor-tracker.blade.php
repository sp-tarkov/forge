<?php

declare(strict_types=1);

use App\Events\PeakVisitorUpdated;
use App\Models\Visitor;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component {
    /**
     * The current peak visitor count.
     */
    public int $peakCount = 0;

    /**
     * The date when the peak was reached.
     */
    public ?string $peakDate = null;

    /**
     * Initialize the component with current peak data.
     */
    public function mount(): void
    {
        // Load initial peak from database (cached for 5 minutes)
        $peakData = Cache::flexible('peak_visitor_data', [3, 5], function () {
            $peak = Visitor::getPeakStats();

            $date = null;
            if ($peak['count'] > 0 && $peak['date']) {
                $date = $peak['date']->format('M j, Y');
            }

            return [
                'count' => $peak['count'],
                'date' => $date,
            ];
        });

        $this->peakCount = $peakData['count'];
        $this->peakDate = $peakData['date'];
    }

    /**
     * Update the peak visitor count if the new count is higher.
     * This is called directly from AlpineJS when the presence channel detects a new peak.
     */
    public function updatePeak(int $count): void
    {
        // Use a mutex lock to prevent race conditions
        Cache::lock('peak-visitor-update', 5)->get(function () use ($count): void {
            // Get current peak from database
            $currentPeak = Visitor::getPeakStats();

            // Only update if the new count is actually higher
            if ($count > $currentPeak['count']) {
                // Update the peak in the database
                Visitor::updatePeak($count);

                // Clear the cache so next load gets fresh data
                Cache::forget('peak_visitor_data');

                // Broadcast the update to all clients
                broadcast(new PeakVisitorUpdated($count, now()->format('M j, Y')));

                // Update local component state
                $this->peakCount = $count;
                $this->peakDate = now()->format('M j, Y');
            }
        });
    }
};
?>

<div
    wire:ignore.self
    x-data="visitorTracker(@js($peakCount), @js($peakDate), $wire)"
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
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </div>
                <div>
                    <span
                        class="font-medium text-gray-300"
                        x-text="totalCount"
                    ></span>
                    <span x-text="totalCount === 1 ? 'user currently online' : 'users currently online'"></span>
                    <template x-if="authCount > 0">
                        <span
                            class="text-gray-500"
                            x-text="`(${authCount} ${authCount === 1 ? 'member' : 'members'})`"
                        ></span>
                    </template>
                </div>
            </div>

            {{-- Peak display --}}
            <template x-if="peakCount > 0 && peakDate">
                <div class="text-right mt-1">
                    <span class="text-gray-500">Peak:</span>
                    <span
                        class="font-medium text-gray-400"
                        x-text="peakCount"
                    ></span>
                    <span
                        class="text-gray-500"
                        x-text="`on ${peakDate}`"
                    ></span>
                </div>
            </template>
        </div>
    </template>
</div>

<script>
    Alpine.data("visitorTracker", (initialPeak, initialDate, wire) => ({
        wire: wire,
        visitors: new Map(),
        leavingTimers: new Map(),
        totalCount: 0,
        authCount: 0,
        peakCount: initialPeak || 0,
        peakDate: initialDate || "",
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
            this.leavingTimers.forEach((timer) => clearTimeout(timer));
            this.leavingTimers.clear();
            if (this.channel) {
                window.Echo.leave("visitors");
                this.channel = null;
            }
            if (this.peakChannel) {
                window.Echo.leave("peak-visitors");
                this.peakChannel = null;
            }
        },

        joinVisitorChannel() {
            if (!window.Echo) {
                console.error("Echo not initialized");
                this.connectionError = true;
                return;
            }

            try {
                this.channel = window.Echo.join("visitors")
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
                        console.error("Channel error:", error);
                        this.connectionError = true;
                    });
            } catch (error) {
                console.error("Failed to join visitors channel:", error);
                this.connectionError = true;
            }
        },

        listenForPeakUpdates() {
            if (!window.Echo) return;

            this.peakChannel = window.Echo.channel("peak-visitors").listen("PeakVisitorUpdated", (data) => {
                this.peakCount = data.count;
                this.peakDate = data.date;
            });
        },

        updateCounts() {
            this.totalCount = this.visitors.size;
            this.authCount = Array.from(this.visitors.values()).filter((v) => v.type === "authenticated")
                .length;
        },

        checkForNewPeak() {
            const now = Date.now();
            if (now - this.lastPeakCheck < 1000) return;
            this.lastPeakCheck = now;

            if (this.totalCount > this.peakCount && this.wire) {
                this.wire.updatePeak(this.totalCount);
            }
        },

        setupEchoListeners() {
            if (!window.Echo) {
                console.error("Echo not initialized");
                this.connectionError = true;
                return;
            }

            const pusher = window.Echo.connector.pusher;

            pusher.connection.unbind("error");
            pusher.connection.unbind("unavailable");
            pusher.connection.unbind("failed");
            pusher.connection.unbind("disconnected");
            pusher.connection.unbind("connected");
            pusher.connection.unbind("state_change");

            pusher.connection.bind("error", () => {
                this.connectionError = true;
            });
            pusher.connection.bind("unavailable", () => {
                this.connectionError = true;
            });
            pusher.connection.bind("failed", () => {
                this.connectionError = true;
            });
            pusher.connection.bind("disconnected", () => {
                this.connectionError = true;
            });
            pusher.connection.bind("connected", () => {
                this.connectionError = false;
                if (!this.channel) {
                    this.joinVisitorChannel();
                }
                if (!this.peakChannel) {
                    this.listenForPeakUpdates();
                }
            });

            pusher.connection.bind("state_change", (states) => {
                if (states.current === "connected" && states.previous !== "connected") {
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
                    },
                };
                document.addEventListener("livewire:navigated", this.navigationHandlers.navigated);
                document.addEventListener("livewire:navigating", this.navigationHandlers.navigating);
            }
        },
    }));
</script>
