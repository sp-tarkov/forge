import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import csharp from 'highlight.js/lib/languages/csharp';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import yaml from 'highlight.js/lib/languages/yaml';
import 'highlight.js/styles/github-dark.css';

// Languages available for user markdown code blocks. Unregistered languages fall back to unstyled plain text.
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('csharp', csharp);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('json', json);
hljs.registerLanguage('php', php);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('yaml', yaml);

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
