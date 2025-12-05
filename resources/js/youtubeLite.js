/**
 * YouTube Lite Embed - Facade Pattern
 *
 * Enhances .youtube-lite elements with click-to-play functionality.
 * The actual YouTube iframe is only loaded when the user clicks the
 * play button, dramatically improving page load performance.
 */

function initializeYouTubeLite(container = document) {
    const embeds = container.querySelectorAll(".youtube-lite:not([data-youtube-initialized])");

    embeds.forEach((embed) => {
        // Mark as initialized to prevent duplicate handlers
        embed.setAttribute("data-youtube-initialized", "true");

        embed.addEventListener("click", function handleClick() {
            const embedUrl = this.dataset.embedUrl;

            if (!embedUrl) {
                console.warn("YouTube lite embed missing data-embed-url", this);
                return;
            }

            // Create and insert the iframe
            const iframe = document.createElement("iframe");
            iframe.src = embedUrl;
            iframe.title = "YouTube video player";
            iframe.allow =
                "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share";
            iframe.referrerPolicy = "strict-origin-when-cross-origin";
            iframe.allowFullscreen = true;

            // Mark as activated (hides play button via CSS)
            this.classList.add("youtube-lite--active");

            // Replace content with iframe
            this.innerHTML = "";
            this.appendChild(iframe);

            // Remove click handler after activation
            this.removeEventListener("click", handleClick);
            this.style.cursor = "default";
        });
    });
}

// Initialize on DOMContentLoaded
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        initializeYouTubeLite();
    });
} else {
    initializeYouTubeLite();
}

// Function to register Livewire hooks
function registerLivewireHooks() {
    // Fires when elements are added during DOM morphing (e.g., lazy-loaded content)
    Livewire.hook("morph.added", ({ el }) => {
        if (el.nodeType === Node.ELEMENT_NODE) {
            const hasYouTubeLite = el.classList?.contains("youtube-lite") || el.querySelector?.(".youtube-lite");

            if (hasYouTubeLite) {
                queueMicrotask(() => {
                    initializeYouTubeLite(el);
                });
            }
        }
    });

    // Fires after a component's DOM has been morphed (e.g., after lazy-load completes)
    Livewire.hook("morphed", ({ el }) => {
        queueMicrotask(() => {
            initializeYouTubeLite(el);
        });
    });
}

// Register Livewire hooks - handle both cases:
// 1. Livewire already started (scripts loaded after Livewire.start())
// 2. Livewire not yet started (livewire:init will fire later)
if (window.Livewire) {
    registerLivewireHooks();
} else {
    document.addEventListener("livewire:init", registerLivewireHooks);
}

// Listen for Livewire navigation events
document.addEventListener("livewire:navigated", () => {
    initializeYouTubeLite();
});

// Listen for custom content-updated events
document.addEventListener("content-updated", () => {
    initializeYouTubeLite();
});

// Export for use in Livewire component scripts
window.initializeYouTubeLite = initializeYouTubeLite;
