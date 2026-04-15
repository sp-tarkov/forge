import { globSync } from 'node:fs';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    fmt: {
        printWidth: 120,
        tabWidth: 4,
        useTabs: false,
        semi: true,
        singleQuote: true,
        singleAttributePerLine: true,
        sortTailwindcss: {
            functions: ['clsx', 'cn'],
            stylesheet: 'resources/css/app.css',
        },
        sortImports: {
            groups: ['builtin', 'external', 'internal', 'parent', 'sibling', 'index'],
            newlinesBetween: false,
        },
        ignorePatterns: ['resources/views/mail/*'],
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                ...globSync('resources/{images,video}/**/*'),
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
