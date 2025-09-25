@props([
    'title' => 'The Forge - Home of Single Player Tarkov Mods',
    'description' => 'The greatest resource available for Single Player Tarkov modifications. Where modding legends are made. Discover powerful tools, expert-written guides, and exclusive mods. Transform the game.',
    'header' => null,
    'variant' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @auth
            <meta name="user-id" content="{{ auth()->id() }}">
        @endauth

        <title>{{ $title }}</title>
        <meta property="og:title" content="{{ $title }}">

        <meta name="description" content="{{ $description }}">
        <meta property="og:description" content="{{ $description }}">

        <link rel="canonical" href="{{ url()->current() }}">
        <meta property="og:url" content="{{ url()->current() }}">

        @if (isset($openGraphImage) && ! empty($openGraphImage->toHtml()))
            @openGraphImageTags($openGraphImage->toHtml(), $title)
        @endif

        <link rel="icon" href="data:image/x-icon;base64,AA">

        <link href="//fonts.bunny.net" rel="preconnect">
        <link href="//fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">
        <link href="{{ config('app.asset_url') }}" rel="dns-prefetch">

        {{ $rssFeeds ?? '' }}

        @livewireStyles
        @fluxAppearance
        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased flex flex-col min-h-screen">
        @if ($variant !== 'simple')
            <x-warning />
            <x-banner />
        @endif

        <div class="flex-grow bg-gray-100 dark:bg-gray-800">
            @if ($variant !== 'simple')
                <livewire:navigation-menu />
            @endif

            @if (filled($header) && $variant !== 'simple')
                <header class="bg-gray-50 dark:bg-gray-900 shadow-sm dark:shadow-gray-950">
                    <div class="max-w-7xl min-h-[80px] flex items-center mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <main class="{{ $variant === 'simple' ? '' : 'pb-6 sm:py-12' }}">
                {{ $slot }}
            </main>
        </div>

        @if ($variant !== 'simple')
            <x-footer />
        @endif

        @stack('modals')
        @livewireScriptConfig
        @fluxScripts
        @vite(['resources/js/app.js'])
    </body>
</html>
