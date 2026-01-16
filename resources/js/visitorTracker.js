import { Alpine } from "../../vendor/livewire/livewire/dist/livewire.esm";

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
        this.authCount = Array.from(this.visitors.values()).filter((v) => v.type === "authenticated").length;
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
