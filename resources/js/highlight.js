import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css';

document.querySelectorAll('.user-markdown pre code, .user-markdown-message pre code, .static-content pre code').forEach(el => {
    hljs.highlightElement(el);
});
