@props([
    'title' => 'The Forge - Home of Single Player Tarkov Mods',
    'description' =>
        'The greatest resource available for Single Player Tarkov modifications. Where modding legends are made. Discover powerful tools, expert-written guides, and exclusive mods. Transform the game.',
    'header' => null,
    'variant' => null,
    'robots' => null,
])
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="dark"
>

<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >
    @auth
        <meta
            name="user-id"
            content="{{ auth()->id() }}"
        >
    @endauth
    @if ($robots)
        <meta
            name="robots"
            content="{{ $robots }}"
        >
    @endif
    <title>{{ $title }}</title>
    <meta
        property="og:title"
        content="{{ $title }}"
    >
    <meta
        name="description"
        content="{{ $description }}"
    >
    <meta
        property="og:description"
        content="{{ $description }}"
    >
    <link
        rel="canonical"
        href="{{ url()->current() }}"
    >
    <meta
        property="og:url"
        content="{{ url()->current() }}"
    >
    @if (isset($openGraphImage) && !empty($openGraphImage->toHtml()))
        @openGraphImageTags($openGraphImage->toHtml(), $title)
    @endif
    <link
        rel="icon"
        type="image/png"
        href="/favicon-96x96.png"
        sizes="96x96"
    />
    <link
        rel="icon"
        type="image/svg+xml"
        href="/favicon.svg"
    />
    <link
        rel="shortcut icon"
        href="/favicon.ico"
    />
    <link
        rel="apple-touch-icon"
        sizes="180x180"
        href="/apple-touch-icon.png"
    />
    <meta
        name="apple-mobile-web-app-title"
        content="TheForge"
    />
    <link
        rel="manifest"
        href="/site.webmanifest"
    />
    <link
        href="//fonts.bunny.net"
        rel="preconnect"
    >
    <link
        href="//fonts.bunny.net/css?family=figtree:400,500,600&display=swap"
        rel="stylesheet"
    >
    <link
        href="{{ config('app.asset_url') }}"
        rel="dns-prefetch"
    >

    {{ $rssFeeds ?? '' }}

    @livewireStyles
    @vite(['resources/css/app.css'])
</head>

<body class="flex min-h-screen flex-col font-sans antialiased">
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-[100] focus:rounded-md focus:bg-gray-900 focus:px-4 focus:py-2 focus:text-gray-100 focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
    >{{ __('Skip to main content') }}</a>

    @persist('toast')
        <flux:toast.group position="top end">
            <flux:toast />
        </flux:toast.group>
    @endpersist

    <div class="flex-grow bg-gray-800">
        @if ($variant !== 'simple')
            <x-navigation-menu />
        @endif

        @if (filled($header) && $variant !== 'simple')
            <header class="bg-gray-900 shadow-sm shadow-gray-950">
                <div class="mx-auto flex min-h-[80px] max-w-7xl items-center px-4 py-6 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <main
            id="main-content"
            class="{{ $variant === 'simple' ? '' : 'pb-6 sm:py-12' }}"
        >
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
    @stack('scripts')
</body>

</html>
