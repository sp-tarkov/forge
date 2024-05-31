<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'The Forge') }}</title>

    <link rel="icon" href="data:image/x-icon;base64,AA">
    <link href="//fonts.bunny.net" rel="preconnect">
    <link href="//fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">

    <script>
        // Immediately set the theme to prevent a flash of the default theme when another is set.
        // Must be located inline, in the head, and before any CSS is loaded.
        (function () {
            let theme = localStorage.getItem('forge-theme');
            if (!theme) {
                theme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                localStorage.setItem('forge-theme', theme);
            }
            document.documentElement.classList.add(theme);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>

<div class="font-sans text-gray-900 antialiased">
    {{ $slot }}
</div>

@livewireScripts

</body>
</html>
