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

// Listen for Livewire events
if (window.Livewire) {
    document.addEventListener("livewire:init", () => {
        Livewire.hook("commit", ({ component, commit, respond, succeed, fail }) => {
            succeed(() => {
                requestAnimationFrame(() => {
                    highlightCodeBlocks(component.el);
                });
            });
        });
    });
}

// Export for manual usage if needed
window.highlightCodeBlocks = highlightCodeBlocks;
