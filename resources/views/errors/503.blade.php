<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance - The Forge</title>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link href="//fonts.bunny.net" rel="preconnect">
    <link href="//fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
    <script defer src="https://unpkg.com/alpinejs@3"></script>
    <style type="text/tailwindcss">
        @custom-variant dark (&:where(.dark, .dark *));
        @theme {
            --font-sans: Figtree, ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
                'Segoe UI Symbol', 'Noto Color Emoji';
        }
    </style>
</head>
<body class="font-sans antialiased flex flex-col min-h-screen bg-gray-100 dark:bg-gray-800">
    <div class="flex-grow flex items-center justify-center px-4">
        <div class="max-w-2xl w-full">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg p-8 md:p-12" x-data="{ snarky: false }">
                <div class="text-center">
                    <button
                        class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-6 cursor-pointer transition-colors duration-300"
                        :class="snarky ? 'bg-red-100 dark:bg-red-900/20' : 'bg-cyan-100 dark:bg-cyan-900/20'"
                        @click="snarky = !snarky"
                    >
                        <svg class="w-8 h-8 transition-colors duration-300" :class="snarky ? 'text-red-600 dark:text-red-400' : 'text-cyan-600 dark:text-cyan-400'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                        </svg>
                    </button>
                    <template x-if="!snarky">
                        <div>
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">Down for Maintenance</h1>
                            <p class="text-lg text-gray-600 dark:text-gray-400">We're performing some scheduled maintenance and will be back shortly. Thanks for your patience.</p>
                        </div>
                    </template>
                    <template x-if="snarky">
                        <div>
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">Yes, It's Still Down</h1>
                            <p class="text-lg text-gray-600 dark:text-gray-400">Seriously, though, you need to be patient. I don't want to see you come into our Discord server and say, "The server is down." <strong>We know.</strong> We're the ones that put it down. Be patient. Good things are coming. Seriously. Touch grass, or something, idk... <em>nerds</em>.</p>
                            <!--
                            Ain't I a stinker? lul
                             - Refringe
                            -->
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
