<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ filled($title) ? $title : 'The Forge - Home of Single Player Tarkov Mods' }}</title>
    <meta property="og:title" content="{{ filled($title) ? $title : 'The Forge - Home of Single Player Tarkov Mods' }}">

    <meta name="description" content="{{ filled($description) ? $description : 'The greatest resource available for Single Player Tarkov modifications. Where modding legends are made. Discover powerful tools, expert-written guides, and exclusive mods. Transform the game.' }}">
    <meta property="og:description" content="{{ filled($description) ? $description : 'The greatest resource available for Single Player Tarkov modifications. Where modding legends are made. Discover powerful tools, expert-written guides, and exclusive mods. Transform the game.' }}">

    <link rel="icon" href="data:image/x-icon;base64,AA">

    <link rel="canonical" href="{{ url()->current() }}">

    <link href="//fonts.bunny.net" rel="preconnect">
    <link href="//fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">

    <link href="{{ config('app.asset_url') }}" rel="dns-prefetch">

    @livewireStyles
    @fluxAppearance
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased">
    <x-warning/>

    <x-banner/>

    <div class="min-h-screen bg-gray-100 dark:bg-gray-800">
        @livewire('navigation-menu')

        @if (filled($header))
            <header class="bg-gray-50 dark:bg-gray-900 shadow-sm dark:shadow-gray-950">
                <div class="max-w-7xl min-h-[80px] flex items-center mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <main class="pb-6 sm:py-12">
            {{ $slot }}
        </main>
    </div>

    <x-footer/>

    @stack('modals')
    @livewireScriptConfig
    @fluxScripts
    @vite(['resources/js/app.js'])
</body>
</html>
