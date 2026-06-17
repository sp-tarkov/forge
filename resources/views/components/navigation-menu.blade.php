<nav
    x-data="{ profileDropdownOpen: false, mobileMenuOpen: false }"
    class="relative bg-gradient-to-br from-gray-100 via-white to-gray-50 dark:from-gray-900 dark:via-black dark:to-gray-950 border-b border-gray-200 dark:border-gray-800 z-50"
    aria-label="{{ __('Main navigation') }}"
>
    <div class="absolute inset-0 overflow-hidden">
        <div
            class="absolute inset-0 bg-gradient-to-br from-gray-200/30 via-transparent to-gray-300/20 dark:from-gray-800/30 dark:via-transparent dark:to-black/40">
        </div>
        <div
            class="absolute inset-0 bg-gradient-to-tl from-gray-300/15 to-transparent dark:from-gray-700/20 dark:to-transparent">
        </div>
        <div
            class="absolute inset-0 opacity-20 dark:opacity-0"
            style="background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.08) 1px, transparent 0); background-size: 4px 4px;"
        ></div>
        <div
            class="absolute inset-0 opacity-0 dark:opacity-35"
            style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.08) 1px, transparent 0); background-size: 4px 4px;"
        ></div>
        <div class="absolute top-0 left-1/4 w-32 h-px bg-gray-400/25 dark:bg-gray-600/30 transform rotate-2"></div>
        <div class="absolute bottom-0 right-1/3 w-24 h-px bg-gray-400/20 dark:bg-gray-500/25 transform -rotate-1"></div>
        <div class="absolute top-1/3 right-0 w-px h-16 bg-gray-400/15 dark:bg-gray-600/20"></div>
        <div class="absolute inset-0 shadow-inner shadow-gray-400/30 dark:shadow-black/60"></div>
    </div>
    <div class="relative mx-auto max-w-7xl px-2 sm:px-4 lg:px-8 z-10">
        <div class="relative flex h-16 items-center justify-between">
            <div class="flex items-center px-2 lg:px-0">
                <div class="shrink-0">
                    <a
                        href="{{ route('home') }}"
                        wire:navigate
                    >
                        <x-application-mark class="block h-9 w-auto" />
                    </a>
                </div>
                <div class="hidden lg:ml-6 lg:block">
                    <div class="flex items-center space-x-4">
                        <x-nav-link
                            href="{{ route('static.installer') }}"
                            :active="request()->routeIs('static.installer')"
                        >{{ __('Install') }}</x-nav-link>
                        <flux:dropdown align="start">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white transition duration-150 ease-in-out"
                            >
                                {{ __('Mods') }}
                                <flux:icon.chevron-down class="size-3 ml-1" />
                            </button>
                            <flux:navmenu>
                                <flux:navmenu.item
                                    href="{{ route('mods') }}"
                                    wire:navigate
                                    icon="squares-2x2"
                                >{{ __('Browse All Mods') }}</flux:navmenu.item>
                                <flux:navmenu.item
                                    href="{{ route('list.index') }}"
                                    wire:navigate
                                    icon="list-bullet"
                                >{{ __('Browse Lists') }}</flux:navmenu.item>
                                @auth
                                    <flux:navmenu.item
                                        href="{{ route('mods.recently-created') }}"
                                        wire:navigate
                                        icon="sparkles"
                                    >
                                        <span class="flex items-center justify-between w-full gap-2">
                                            {{ __('Recently Created') }}
                                            <livewire:navigation-created-mods-badge />
                                        </span>
                                    </flux:navmenu.item>
                                    <flux:navmenu.item
                                        href="{{ route('mods.recently-updated') }}"
                                        wire:navigate
                                        icon="arrow-path"
                                    >
                                        <span class="flex items-center justify-between w-full gap-2">
                                            {{ __('Recently Updated') }}
                                            <livewire:navigation-updated-mods-badge />
                                        </span>
                                    </flux:navmenu.item>
                                @endauth
                            </flux:navmenu>
                        </flux:dropdown>
                        <a
                            href="https://wiki.sp-tarkov.com/"
                            target="_blank"
                            class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white transition duration-150 ease-in-out inline-flex items-center gap-1"
                        >
                            {{ __('Wiki') }}
                            <flux:icon.arrow-top-right-on-square class="h-3 w-3" />
                        </a>
                    </div>
                </div>
            </div>

            <livewire:global-search />

            <div class="flex lg:hidden">
                {{-- Mobile Menu Button --}}
                <button
                    x-on:click="mobileMenuOpen = !mobileMenuOpen"
                    type="button"
                    class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-gray-500 dark:focus:ring-white"
                    aria-controls="mobile-menu"
                    :aria-expanded="mobileMenuOpen"
                >
                    <span class="absolute -inset-0.5"></span>
                    <span class="sr-only">{{ __('Open main menu') }}</span>
                    <flux:icon.bars-3
                        x-show="!mobileMenuOpen"
                        class="h-6 w-6"
                    />
                    <flux:icon.x-mark
                        x-show="mobileMenuOpen"
                        class="h-6 w-6"
                    />
                </button>
            </div>
            <div class="hidden lg:ml-4 lg:block">
                <div class="flex items-center gap-4">
                    {{-- Theme Toggle and Chat grouped together --}}
                    <div class="flex items-center gap-2">
                        {{-- Desktop Theme Toggle --}}
                        <flux:dropdown
                            align="end"
                            x-data=""
                        >
                            <flux:button
                                variant="subtle"
                                class="group"
                                aria-label="Preferred color scheme"
                                square="true"
                            >
                                <flux:icon.sun
                                    x-cloak
                                    x-show="$flux.appearance === 'light'"
                                    variant="mini"
                                    class="text-zinc-500 dark:text-white"
                                />
                                <flux:icon.moon
                                    x-cloak
                                    x-show="$flux.appearance === 'dark'"
                                    variant="mini"
                                    class="text-zinc-500 dark:text-white"
                                />
                                <flux:icon.moon
                                    x-cloak
                                    x-show="$flux.appearance === 'system' && $flux.dark"
                                    variant="mini"
                                />
                                <flux:icon.sun
                                    x-cloak
                                    x-show="$flux.appearance === 'system' && ! $flux.dark"
                                    variant="mini"
                                />
                            </flux:button>
                            <flux:navmenu>
                                <flux:navmenu.item
                                    icon="sun"
                                    x-on:click="$flux.appearance = 'light'"
                                >Light</flux:navmenu.item>
                                <flux:navmenu.item
                                    icon="moon"
                                    x-on:click="$flux.appearance = 'dark'"
                                >Dark</flux:navmenu.item>
                                <flux:navmenu.item
                                    icon="computer-desktop"
                                    x-on:click="$flux.appearance = 'system'"
                                >System</flux:navmenu.item>
                            </flux:navmenu>
                        </flux:dropdown>

                        {{-- Notifications Dropdown --}}
                        @auth
                            <livewire:navigation-notifications />
                        @endauth

                        {{-- Chat Dropdown --}}
                        @auth
                            <livewire:navigation-chat />
                        @endauth

                        {{-- Moderation/Staff Dropdown --}}
                        @auth
                            @if (auth()->user()->isModOrAdmin())
                                <flux:dropdown align="end">
                                    <flux:button
                                        variant="subtle"
                                        class="group"
                                        aria-label="{{ __('Moderation and staff') }}"
                                        square="true"
                                    >
                                        <flux:icon.wrench-screwdriver
                                            variant="mini"
                                            class="text-zinc-500 dark:text-white"
                                        />
                                    </flux:button>
                                    <flux:navmenu class="w-56">
                                        {{-- Moderation --}}
                                        <div class="px-2 py-1.5">
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">{{ __('Moderation') }}</div>
                                        </div>
                                        <flux:navmenu.item
                                            icon="document-text"
                                            href="{{ route('report-centre') }}"
                                            wire:navigate
                                        >{{ __('Report Centre') }}</flux:navmenu.item>
                                        <flux:navmenu.item
                                            icon="shield-exclamation"
                                            href="{{ route('spam-review') }}"
                                            wire:navigate
                                        >{{ __('Spam Review') }}</flux:navmenu.item>
                                        <flux:navmenu.item
                                            icon="shield-check"
                                            href="{{ route('moderation-actions') }}"
                                            wire:navigate
                                        >{{ __('Moderation Actions') }}</flux:navmenu.item>

                                        @if (auth()->user()->isAdmin())
                                            {{-- Staff --}}
                                            <flux:navmenu.separator />
                                            <div class="px-2 py-1.5">
                                                <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">{{ __('Staff') }}</div>
                                            </div>
                                            <flux:navmenu.item
                                                icon="cube"
                                                href="{{ route('admin.spt-versions') }}"
                                                wire:navigate
                                            >{{ __('SPT Versions') }}</flux:navmenu.item>
                                            <flux:navmenu.item
                                                icon="queue-list"
                                                href="/horizon"
                                                wire:navigate
                                            >{{ __('Horizon Queue') }}</flux:navmenu.item>
                                            <flux:navmenu.item
                                                icon="chart-bar"
                                                href="{{ route('admin.visitor-analytics') }}"
                                                wire:navigate
                                            >{{ __('Visitor Analytics') }}</flux:navmenu.item>
                                            <flux:navmenu.item
                                                icon="users"
                                                href="{{ route('admin.user-management') }}"
                                                wire:navigate
                                            >{{ __('User Management') }}</flux:navmenu.item>
                                            <flux:navmenu.item
                                                icon="user-group"
                                                href="{{ route('admin.role-management') }}"
                                                wire:navigate
                                            >{{ __('Role Management') }}</flux:navmenu.item>
                                            <flux:navmenu.item
                                                icon="shield-check"
                                                href="{{ route('admin.file-verification') }}"
                                                wire:navigate
                                            >{{ __('File Verification') }}</flux:navmenu.item>
                                            <flux:navmenu.item
                                                icon="cube"
                                                href="{{ route('admin.oauth-clients') }}"
                                                wire:navigate
                                            >{{ __('OAuth Clients') }}</flux:navmenu.item>
                                        @endif
                                    </flux:navmenu>
                                </flux:dropdown>
                            @endif
                        @endauth
                    </div>

                    @auth
                        {{-- Profile Dropdown --}}
                        <flux:dropdown
                            align="end"
                            class="flex items-center"
                        >
                            <button
                                type="button"
                                class="rounded-full focus:outline-hidden focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800"
                            >
                                <img
                                    class="h-8 w-8 rounded-full"
                                    src="{{ auth()->user()->profile_photo_url }}"
                                    alt="{{ auth()->user()->name }}"
                                >
                            </button>

                            <flux:navmenu class="w-56">
                                {{-- Dashboard --}}
                                <flux:navmenu.item
                                    icon="home"
                                    href="{{ route('dashboard') }}"
                                    wire:navigate
                                >{{ __('Dashboard') }}</flux:navmenu.item>

                                <flux:navmenu.separator />
                                <flux:navmenu.item
                                    icon="user-circle"
                                    href="{{ auth()->user()->profile_url }}"
                                    wire:navigate
                                >{{ __('View Profile') }}</flux:navmenu.item>
                                <flux:navmenu.item
                                    icon="cog-6-tooth"
                                    href="{{ route('profile.show') }}"
                                    wire:navigate
                                >{{ __('Edit Profile') }}</flux:navmenu.item>

                                <flux:navmenu.separator />
                                <flux:navmenu.item
                                    icon="cube-transparent"
                                    href="{{ auth()->user()->profile_url }}#mods"
                                    wire:navigate
                                >{{ __('My Mods') }}</flux:navmenu.item>
                                <flux:navmenu.item
                                    icon="puzzle-piece"
                                    href="{{ auth()->user()->profile_url }}#addons"
                                    wire:navigate
                                >{{ __('My Addons') }}</flux:navmenu.item>
                                <flux:navmenu.item
                                    icon="list-bullet"
                                    href="{{ auth()->user()->profile_url }}#lists"
                                    wire:navigate
                                >{{ __('My Lists') }}</flux:navmenu.item>

                                <flux:navmenu.separator />
                                <flux:navmenu.item
                                    icon="book-open"
                                    href="/docs/index.html"
                                >{{ __('API Documentation') }}</flux:navmenu.item>
                                <flux:navmenu.item
                                    icon="key"
                                    href="{{ route('api-tokens.index') }}"
                                    wire:navigate
                                >{{ __('API Tokens') }}</flux:navmenu.item>
                                <flux:navmenu.item
                                    icon="cube"
                                    href="{{ route('oauth.developer-portal') }}"
                                    wire:navigate
                                >{{ __('OAuth Apps') }}</flux:navmenu.item>
                                <flux:navmenu.item
                                    icon="shield-check"
                                    href="{{ route('oauth.connected-apps') }}"
                                    wire:navigate
                                >{{ __('Connected Apps') }}</flux:navmenu.item>

                                {{-- Logout --}}
                                <flux:navmenu.separator />
                                <form
                                    method="POST"
                                    action="{{ route('logout') }}"
                                    x-data
                                >
                                    @csrf
                                    <flux:navmenu.item
                                        icon="arrow-right-start-on-rectangle"
                                        href="{{ route('logout') }}"
                                        x-on:click.prevent="$root.submit();"
                                    >{{ __('Log Out') }}</flux:navmenu.item>
                                </form>
                            </flux:navmenu>
                        </flux:dropdown>
                    @endauth
                    @guest
                        <div>
                            <a
                                href="{{ route('login') }}"
                                wire:navigate
                                class="text-sm font-semibold leading-6 text-gray-700 dark:text-gray-100 whitespace-nowrap"
                            >Log in <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    @endguest
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div
        class="lg:hidden relative z-50"
        x-show="mobileMenuOpen"
        id="mobile-menu"
    >
        <div class="py-3">
            <div class="flex justify-center px-5">
                {{-- Mobile Theme Toggle --}}
                <flux:radio.group
                    x-data=""
                    variant="segmented"
                    x-model="$flux.appearance"
                >
                    <flux:radio
                        class="cursor-pointer"
                        value="light"
                        icon="sun"
                    />
                    <flux:radio
                        class="cursor-pointer"
                        value="dark"
                        icon="moon"
                    />
                    <flux:radio
                        class="cursor-pointer"
                        value="system"
                        icon="computer-desktop"
                    />
                </flux:radio.group>
            </div>
        </div>
        <div class="border-y border-gray-300 dark:border-gray-700 space-y-1 mx-3 py-3">
            <x-responsive-nav-link
                href="{{ route('static.installer') }}"
                :active="request()->routeIs('static.installer')"
            >{{ __('Install') }}</x-responsive-nav-link>
            <x-responsive-nav-link
                href="{{ route('mods') }}"
                :active="request()->routeIs('mods')"
            >{{ __('Mods') }}</x-responsive-nav-link>
            <x-responsive-nav-link
                href="{{ route('list.index') }}"
                :active="request()->routeIs('list.index')"
            >{{ __('Lists') }}</x-responsive-nav-link>
            @auth
                <x-responsive-nav-link
                    href="{{ route('mods.recently-created') }}"
                    :active="request()->routeIs('mods.recently-created')"
                >
                    <span class="flex items-center justify-between w-full">
                        {{ __('Recently Created') }}
                        <livewire:navigation-created-mods-badge />
                    </span>
                </x-responsive-nav-link>
                <x-responsive-nav-link
                    href="{{ route('mods.recently-updated') }}"
                    :active="request()->routeIs('mods.recently-updated')"
                >
                    <span class="flex items-center justify-between w-full">
                        {{ __('Recently Updated') }}
                        <livewire:navigation-updated-mods-badge />
                    </span>
                </x-responsive-nav-link>
            @endauth
            <a
                href="https://wiki.sp-tarkov.com/"
                target="_blank"
                class="block rounded-md px-3 py-2 text-base font-medium text-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-800 dark:hover:text-white transition duration-150 ease-in-out flex items-center gap-2"
            >
                {{ __('Wiki') }}
                <flux:icon.arrow-top-right-on-square class="h-4 w-4" />
            </a>
        </div>
        @auth
            <div class="flex items-center px-5 pt-3">
                <div class="shrink-0">
                    <img
                        class="h-10 w-10 rounded-full"
                        src="{{ auth()->user()->profile_photo_url }}"
                        alt="{{ auth()->user()->name }}"
                    >
                </div>
                <div class="ml-3 flex-1">
                    <div class="text-base font-medium text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</div>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ auth()->user()->email }}</div>
                </div>
                {{-- Mobile Notifications Button --}}
                <a
                    href="{{ route('dashboard') }}"
                    wire:navigate
                    class="relative inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white transition duration-150 ease-in-out"
                >
                    <flux:icon.bell class="h-5 w-5" />
                    @if (($mobileNotifCount = auth()->user()->unreadNotifications()->count()) > 0)
                        <span
                            class="absolute top-1 left-2 flex h-3 w-3 items-center justify-center rounded-full bg-red-600 text-[8px] font-bold text-white"
                        >
                            {{ $mobileNotifCount > 9 ? '9+' : $mobileNotifCount }}
                        </span>
                    @endif
                </a>
                {{-- Mobile Chat Button --}}
                <a
                    href="{{ route('chat') }}"
                    wire:navigate
                    class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white transition duration-150 ease-in-out"
                >
                    <flux:icon.chat-bubble-left-ellipsis class="h-5 w-5" />
                    <span>{{ __('Chat') }}</span>
                </a>
            </div>
            <div class="mx-3 py-3">
                <div class="space-y-1">
                    {{-- Dashboard --}}
                    <x-responsive-nav-link
                        href="{{ route('dashboard') }}"
                        :active="request()->routeIs('dashboard')"
                    >
                        <span class="flex items-center gap-2">
                            <flux:icon.home class="h-5 w-5" />
                            {{ __('Dashboard') }}
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link
                        href="{{ auth()->user()->profile_url }}"
                        :active="request()->routeIs('user.show')"
                    >
                        <span class="flex items-center gap-2">
                            <flux:icon.user-circle class="h-5 w-5" />
                            {{ __('View Profile') }}
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link
                        href="{{ route('profile.show') }}"
                        :active="request()->routeIs('profile.show')"
                    >
                        <span class="flex items-center gap-2">
                            <flux:icon.cog-6-tooth class="h-5 w-5" />
                            {{ __('Edit Profile') }}
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ auth()->user()->profile_url }}#mods">
                        <span class="flex items-center gap-2">
                            <flux:icon.cube-transparent class="h-5 w-5" />
                            {{ __('My Mods') }}
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ auth()->user()->profile_url }}#addons">
                        <span class="flex items-center gap-2">
                            <flux:icon.puzzle-piece class="h-5 w-5" />
                            {{ __('My Addons') }}
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ auth()->user()->profile_url }}#lists">
                        <span class="flex items-center gap-2">
                            <flux:icon.list-bullet class="h-5 w-5" />
                            {{ __('My Lists') }}
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link href="/docs/index.html">
                        <span class="flex items-center gap-2">
                            <flux:icon.book-open class="h-5 w-5" />
                            {{ __('API Documentation') }}
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link
                        href="{{ route('api-tokens.index') }}"
                        :active="request()->routeIs('api-tokens.index')"
                    >
                        <span class="flex items-center gap-2">
                            <flux:icon.key class="h-5 w-5" />
                            {{ __('API Tokens') }}
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link
                        href="{{ route('oauth.developer-portal') }}"
                        :active="request()->routeIs('oauth.developer-portal')"
                    >
                        <span class="flex items-center gap-2">
                            <flux:icon.cube class="h-5 w-5" />
                            {{ __('OAuth Apps') }}
                        </span>
                    </x-responsive-nav-link>
                    <x-responsive-nav-link
                        href="{{ route('oauth.connected-apps') }}"
                        :active="request()->routeIs('oauth.connected-apps')"
                    >
                        <span class="flex items-center gap-2">
                            <flux:icon.shield-check class="h-5 w-5" />
                            {{ __('Connected Apps') }}
                        </span>
                    </x-responsive-nav-link>
                    {{-- Logout --}}
                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                        x-data
                    >
                        @csrf
                        <x-responsive-nav-link
                            href="{{ route('logout') }}"
                            x-on:click.prevent="$root.submit();"
                            :active="request()->routeIs('logout')"
                        >
                            <span class="flex items-center gap-2">
                                <flux:icon.arrow-right-start-on-rectangle class="h-5 w-5" />
                                {{ __('Log Out') }}
                            </span>
                        </x-responsive-nav-link>
                    </form>
                </div>

                @if (auth()->user()->isModOrAdmin())
                    {{-- Moderation --}}
                    <div class="mt-3 pt-3 border-t border-gray-300 dark:border-gray-700">
                        <div class="px-3 pb-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                            {{ __('Moderation') }}
                        </div>
                    </div>
                    <div class="space-y-1">
                        <x-responsive-nav-link
                            href="{{ route('report-centre') }}"
                            :active="request()->routeIs('report-centre')"
                        >
                            <span class="flex items-center gap-2">
                                <flux:icon.document-text class="h-5 w-5" />
                                {{ __('Report Centre') }}
                            </span>
                        </x-responsive-nav-link>
                        <x-responsive-nav-link
                            href="{{ route('spam-review') }}"
                            :active="request()->routeIs('spam-review')"
                        >
                            <span class="flex items-center gap-2">
                                <flux:icon.shield-exclamation class="h-5 w-5" />
                                {{ __('Spam Review') }}
                            </span>
                        </x-responsive-nav-link>
                        <x-responsive-nav-link
                            href="{{ route('moderation-actions') }}"
                            :active="request()->routeIs('moderation-actions')"
                        >
                            <span class="flex items-center gap-2">
                                <flux:icon.shield-check class="h-5 w-5" />
                                {{ __('Moderation Actions') }}
                            </span>
                        </x-responsive-nav-link>
                    </div>
                @endif

                @if (auth()->user()->isAdmin())
                    {{-- Staff --}}
                    <div class="mt-3 pt-3 border-t border-gray-300 dark:border-gray-700">
                        <div class="px-3 pb-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                            {{ __('Staff') }}
                        </div>
                    </div>
                    <div class="space-y-1">
                        <x-responsive-nav-link
                            href="{{ route('admin.spt-versions') }}"
                            :active="request()->routeIs('admin.spt-versions')"
                        >
                            <span class="flex items-center gap-2">
                                <flux:icon.cube class="h-5 w-5" />
                                {{ __('SPT Versions') }}
                            </span>
                        </x-responsive-nav-link>
                        <x-responsive-nav-link href="/horizon">
                            <span class="flex items-center gap-2">
                                <flux:icon.queue-list class="h-5 w-5" />
                                {{ __('Horizon Queue') }}
                            </span>
                        </x-responsive-nav-link>
                        <x-responsive-nav-link
                            href="{{ route('admin.visitor-analytics') }}"
                            :active="request()->routeIs('admin.visitor-analytics')"
                        >
                            <span class="flex items-center gap-2">
                                <flux:icon.chart-bar class="h-5 w-5" />
                                {{ __('Visitor Analytics') }}
                            </span>
                        </x-responsive-nav-link>
                        <x-responsive-nav-link
                            href="{{ route('admin.user-management') }}"
                            :active="request()->routeIs('admin.user-management')"
                        >
                            <span class="flex items-center gap-2">
                                <flux:icon.users class="h-5 w-5" />
                                {{ __('User Management') }}
                            </span>
                        </x-responsive-nav-link>
                        <x-responsive-nav-link
                            href="{{ route('admin.role-management') }}"
                            :active="request()->routeIs('admin.role-management')"
                        >
                            <span class="flex items-center gap-2">
                                <flux:icon.user-group class="h-5 w-5" />
                                {{ __('Role Management') }}
                            </span>
                        </x-responsive-nav-link>
                        <x-responsive-nav-link
                            href="{{ route('admin.file-verification') }}"
                            :active="request()->routeIs('admin.file-verification')"
                        >
                            <span class="flex items-center gap-2">
                                <flux:icon.shield-check class="h-5 w-5" />
                                {{ __('File Verification') }}
                            </span>
                        </x-responsive-nav-link>
                    </div>
                @endif
            </div>
        @endauth
        @guest
            <div class="space-y-1 mx-3 py-3">
                <x-responsive-nav-link
                    href="{{ route('login') }}"
                    :active="request()->routeIs('login')"
                >{{ __('Log in') }}</x-responsive-nav-link>
                <x-responsive-nav-link
                    href="{{ route('register') }}"
                    :active="request()->routeIs('register')"
                >{{ __('Register') }}</x-responsive-nav-link>
            </div>
        @endguest
    </div>
</nav>
