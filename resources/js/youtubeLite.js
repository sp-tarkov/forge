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

// Listen for Livewire events to reinitialize after content updates
if (window.Livewire) {
    document.addEventListener("livewire:navigated", () => {
        initializeYouTubeLite();
    });

    document.addEventListener("livewire:init", () => {
        Livewire.hook("commit", ({ component, succeed }) => {
            succeed(() => {
                requestAnimationFrame(() => {
                    initializeYouTubeLite(component.el);
                });
            });
        });
    });
}

// Listen for custom content-updated events
document.addEventListener("content-updated", () => {
    initializeYouTubeLite();
});
