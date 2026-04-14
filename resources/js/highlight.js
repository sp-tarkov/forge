import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css';

// Language classes that should render as plain text without syntax highlighting.
const plainTextClasses = new Set(['language-text', 'language-txt', 'language-plaintext']);

// Function to highlight code blocks
function highlightCodeBlocks(container = document) {
    const selector = '.user-markdown pre code, .user-markdown-message pre code, .static-content pre code';
    const codeBlocks = container.querySelectorAll
        ? container.querySelectorAll(selector)
        : document.querySelectorAll(selector);

    codeBlocks.forEach((el) => {
        // Only highlight if not already highlighted
        if (!el.classList.contains('hljs')) {
            // Skip highlighting for plain text code blocks
            if ([...el.classList].some((cls) => plainTextClasses.has(cls))) {
                el.classList.add('hljs');
                return;
            }

            hljs.highlightElement(el);
        }
    });
}

// Initial highlighting on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        highlightCodeBlocks();
    });
} else {
    highlightCodeBlocks();
}

// Function to register Livewire hooks
function registerLivewireHooks() {
    // Fires when elements are added during DOM morphing (e.g., lazy-loaded content)
    Livewire.hook('morph.added', ({ el }) => {
        if (el.nodeType === Node.ELEMENT_NODE) {
            const hasCodeBlocks = el.tagName === 'CODE' || el.querySelector?.('pre code');

            if (hasCodeBlocks) {
                queueMicrotask(() => {
                    highlightCodeBlocks(el);
                });
            }
        }
    });

    // Fires after a component's DOM has been morphed (e.g., after lazy-load completes)
    Livewire.hook('morphed', ({ el }) => {
        queueMicrotask(() => {
            highlightCodeBlocks(el);
        });
    });
}

// Register Livewire hooks - handle both cases:
// 1. Livewire already started (scripts loaded after Livewire.start())
// 2. Livewire not yet started (livewire:init will fire later)
if (window.Livewire) {
    registerLivewireHooks();
} else {
    document.addEventListener('livewire:init', registerLivewireHooks);
}

// Listen for content updates (e.g., Livewire updates, markdown preview)
document.addEventListener('content-updated', () => {
    highlightCodeBlocks();
});

// Export for manual usage if needed
window.highlightCodeBlocks = highlightCodeBlocks;
