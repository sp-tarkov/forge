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

    <link href="{{ config('app.asset_url') }}" rel="dns-prefetch">

    @livewireStyles
    @vite(['resources/css/app.css'])

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
            if (theme === 'dark') {
                document.documentElement.classList.add('fl-dark');
            }
        })();
    </script>
</head>
<body>
    <div class="font-sans text-gray-900 antialiased">
        {{ $slot }}
    </div>

    @vite(['resources/js/app.js']);
    @livewireScriptConfig
    @include('includes.analytics')
</body>
</html>
