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
<body>
    <div class="font-sans text-gray-900 antialiased">
        {{ $slot }}
    </div>

    @livewireScriptConfig
    @fluxScripts
    @vite(['resources/js/app.js'])
</body>
</html>
