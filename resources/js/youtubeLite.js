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

// Use MutationObserver to detect when youtube-lite elements are added to the DOM.
// This is more reliable than Livewire hooks for lazy-loaded content.
const youtubeLiteObserver = new MutationObserver((mutations) => {
    let shouldInitialize = false;

    for (const mutation of mutations) {
        if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    // Check if the added node contains uninitialized youtube-lite elements
                    if (
                        (node.classList?.contains("youtube-lite") && !node.hasAttribute("data-youtube-initialized")) ||
                        node.querySelector?.(".youtube-lite:not([data-youtube-initialized])")
                    ) {
                        shouldInitialize = true;
                        break;
                    }
                }
            }
        }
        if (shouldInitialize) break;
    }

    if (shouldInitialize) {
        requestAnimationFrame(() => {
            initializeYouTubeLite();
        });
    }
});

// Start observing the document body for added nodes
youtubeLiteObserver.observe(document.body, {
    childList: true,
    subtree: true,
});

// Listen for Livewire navigation events
document.addEventListener("livewire:navigated", () => {
    initializeYouTubeLite();
});

// Listen for custom content-updated events
document.addEventListener("content-updated", () => {
    initializeYouTubeLite();
});
