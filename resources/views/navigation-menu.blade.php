<nav
    x-data="{ profileDropdownOpen: false, mobileMenuOpen: false }"
    class="relative bg-gradient-to-br from-gray-100 via-white to-gray-50 dark:from-gray-900 dark:via-black dark:to-gray-950 border-b border-gray-200 dark:border-gray-800 z-50"
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
                    <div class="flex space-x-4">
                        <x-nav-link
                            href="{{ route('static.installer') }}"
                            :active="request()->routeIs('static.installer')"
                        >{{ __('Install') }}</x-nav-link>
                        <x-nav-link
                            href="{{ route('mods') }}"
                            :active="request()->routeIs('mods')"
                        >{{ __('Mods') }}</x-nav-link>
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
                            <flux:menu>
                                <flux:menu.item
                                    icon="sun"
                                    x-on:click="$flux.appearance = 'light'"
                                >Light</flux:menu.item>
                                <flux:menu.item
                                    icon="moon"
                                    x-on:click="$flux.appearance = 'dark'"
                                >Dark</flux:menu.item>
                                <flux:menu.item
                                    icon="computer-desktop"
                                    x-on:click="$flux.appearance = 'system'"
                                >System</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>

                        {{-- Chat Dropdown --}}
                        @auth
                            <livewire:navigation-chat />
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

                            <flux:menu class="w-56">
                                {{-- Dashboard --}}
                                <flux:menu.item
                                    icon="home"
                                    href="{{ route('dashboard') }}"
                                    wire:navigate
                                >{{ __('Dashboard') }}</flux:menu.item>

                                {{-- Profile Submenu --}}
                                <flux:menu.submenu
                                    heading="{{ __('Profile') }}"
                                    icon="user"
                                >
                                    <flux:menu.item
                                        icon="user-circle"
                                        href="{{ auth()->user()->profile_url }}"
                                        wire:navigate
                                    >{{ __('View Profile') }}</flux:menu.item>
                                    <flux:menu.item
                                        icon="cube-transparent"
                                        href="{{ auth()->user()->profile_url }}#mods"
                                        wire:navigate
                                    >{{ __('My Mods') }}</flux:menu.item>
                                    <flux:menu.item
                                        icon="puzzle-piece"
                                        href="{{ auth()->user()->profile_url }}#addons"
                                        wire:navigate
                                    >{{ __('My Addons') }}</flux:menu.item>
                                    <flux:menu.item
                                        icon="cog-6-tooth"
                                        href="{{ route('profile.show') }}"
                                        wire:navigate
                                    >{{ __('Edit Profile') }}</flux:menu.item>
                                    <flux:menu.item
                                        icon="key"
                                        href="{{ route('api-tokens.index') }}"
                                        wire:navigate
                                    >{{ __('API Tokens') }}</flux:menu.item>
                                </flux:menu.submenu>

                                @if (auth()->user()->isAdmin())
                                    {{-- Staff Submenu --}}
                                    <flux:menu.submenu
                                        heading="{{ __('Staff') }}"
                                        icon="shield-check"
                                    >
                                        <flux:menu.item
                                            icon="cube"
                                            href="{{ route('admin.spt-versions') }}"
                                            wire:navigate
                                        >{{ __('SPT Versions') }}</flux:menu.item>
                                        <flux:menu.item
                                            icon="queue-list"
                                            href="/horizon"
                                            wire:navigate
                                        >{{ __('Horizon Queue') }}</flux:menu.item>
                                        <flux:menu.item
                                            icon="chart-bar"
                                            href="{{ route('admin.visitor-analytics') }}"
                                            wire:navigate
                                        >{{ __('Visitor Analytics') }}</flux:menu.item>
                                        <flux:menu.item
                                            icon="users"
                                            href="{{ route('admin.user-management') }}"
                                            wire:navigate
                                        >{{ __('User Management') }}</flux:menu.item>
                                    </flux:menu.submenu>
                                @endif

                                {{-- Logout (in its own group) --}}
                                <flux:menu.separator />
                                <form
                                    method="POST"
                                    action="{{ route('logout') }}"
                                    x-data
                                >
                                    @csrf
                                    <flux:menu.item
                                        icon="arrow-right-start-on-rectangle"
                                        href="{{ route('logout') }}"
                                        x-on:click.prevent="$root.submit();"
                                    >{{ __('Log Out') }}</flux:menu.item>
                                </form>
                            </flux:menu>
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
            <div class="space-y-1 mx-3 py-3">
                <x-responsive-nav-link
                    href="{{ route('dashboard') }}"
                    :active="request()->routeIs('dashboard')"
                >{{ __('Dashboard') }}</x-responsive-nav-link>
                <x-responsive-nav-link
                    href="{{ auth()->user()->profile_url }}"
                    :active="request()->routeIs('user.show')"
                >{{ __('Profile') }}</x-responsive-nav-link>
                <x-responsive-nav-link
                    href="{{ auth()->user()->profile_url }}#mods">{{ __('My Mods') }}</x-responsive-nav-link>
                <x-responsive-nav-link
                    href="{{ auth()->user()->profile_url }}#addons">{{ __('My Addons') }}</x-responsive-nav-link>
                <x-responsive-nav-link
                    href="{{ route('profile.show') }}"
                    :active="request()->routeIs('profile.show')"
                >{{ __('Edit Profile') }}</x-responsive-nav-link>
                <x-responsive-nav-link
                    href="{{ route('api-tokens.index') }}"
                    :active="request()->routeIs('api-tokens.index')"
                >{{ __('API Token') }}</x-responsive-nav-link>
                @if (auth()->user()->isAdmin())
                    <x-responsive-nav-link
                        href="{{ route('admin.spt-versions') }}"
                        :active="request()->routeIs('admin.spt-versions')"
                    >{{ __('SPT Versions') }}</x-responsive-nav-link>
                    <x-responsive-nav-link
                        href="/horizon"
                        :active="request()->routeIs('api-tokens.index')"
                    >{{ __('Horizon Queue') }}</x-responsive-nav-link>
                    <x-responsive-nav-link
                        href="{{ route('admin.visitor-analytics') }}"
                        :active="request()->routeIs('admin.visitor-analytics')"
                    >{{ __('Visitor Analytics') }}</x-responsive-nav-link>
                    <x-responsive-nav-link
                        href="{{ route('admin.user-management') }}"
                        :active="request()->routeIs('admin.user-management')"
                    >{{ __('User Management') }}</x-responsive-nav-link>
                @endif
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
                    >{{ __('Log Out') }}</x-responsive-nav-link>
                </form>
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
