import hljs from "highlight.js";
import "highlight.js/styles/github-dark.css";

// Function to highlight code blocks
function highlightCodeBlocks(container = document) {
    const selector = ".user-markdown pre code, .user-markdown-message pre code, .static-content pre code";
    const codeBlocks = container.querySelectorAll
        ? container.querySelectorAll(selector)
        : document.querySelectorAll(selector);

    codeBlocks.forEach((el) => {
        // Only highlight if not already highlighted
        if (!el.classList.contains("hljs")) {
            hljs.highlightElement(el);
        }
    });
}

// Initial highlighting on page load
highlightCodeBlocks();

// Listen for content updates (e.g., Livewire updates, markdown preview)
document.addEventListener("content-updated", () => {
    highlightCodeBlocks();
});

// Use MutationObserver to detect when code blocks are added to the DOM.
// This is more reliable than Livewire hooks for lazy-loaded content.
const codeBlockObserver = new MutationObserver((mutations) => {
    let shouldHighlight = false;

    for (const mutation of mutations) {
        if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    // Check if the added node contains unhighlighted code blocks
                    if (
                        (node.tagName === "CODE" && !node.classList?.contains("hljs")) ||
                        node.querySelector?.("pre code:not(.hljs)")
                    ) {
                        shouldHighlight = true;
                        break;
                    }
                }
            }
        }
        if (shouldHighlight) break;
    }

    if (shouldHighlight) {
        requestAnimationFrame(() => {
            highlightCodeBlocks();
        });
    }
});

// Start observing the document body for added nodes
codeBlockObserver.observe(document.body, {
    childList: true,
    subtree: true,
});

// Export for manual usage if needed
window.highlightCodeBlocks = highlightCodeBlocks;
