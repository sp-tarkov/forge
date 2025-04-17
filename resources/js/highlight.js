import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css';

document.querySelectorAll('.user-markdown pre code').forEach(el => {
    hljs.highlightElement(el);
});