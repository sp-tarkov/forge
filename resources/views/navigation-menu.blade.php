<nav
    x-data="{ profileDropdownOpen: false, mobileMenuOpen: false }"
    class="relative bg-gradient-to-br from-gray-100 via-white to-gray-50 dark:from-gray-900 dark:via-black dark:to-gray-950 border-b border-gray-200 dark:border-gray-800 z-50"
>
    {{-- Background container with overflow hidden --}}
    <div class="absolute inset-0 overflow-hidden">
        {{-- Light/Dark gritty texture overlays --}}
        <div class="absolute inset-0 bg-gradient-to-br from-gray-200/30 via-transparent to-gray-300/20 dark:from-gray-800/30 dark:via-transparent dark:to-black/40"></div>
        <div class="absolute inset-0 bg-gradient-to-tl from-gray-300/15 to-transparent dark:from-gray-700/20 dark:to-transparent"></div>

        {{-- Noise/grain pattern for texture --}}
        <div class="absolute inset-0 opacity-20 dark:opacity-0" style="background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.08) 1px, transparent 0); background-size: 4px 4px;"></div>
        <div class="absolute inset-0 opacity-0 dark:opacity-35" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.08) 1px, transparent 0); background-size: 4px 4px;"></div>

        {{-- Industrial wear patterns --}}
        <div class="absolute top-0 left-1/4 w-32 h-px bg-gray-400/25 dark:bg-gray-600/30 transform rotate-2"></div>
        <div class="absolute bottom-0 right-1/3 w-24 h-px bg-gray-400/20 dark:bg-gray-500/25 transform -rotate-1"></div>
        <div class="absolute top-1/3 right-0 w-px h-16 bg-gray-400/15 dark:bg-gray-600/20"></div>

        {{-- Subtle inner shadow for depth --}}
        <div class="absolute inset-0 shadow-inner shadow-gray-400/30 dark:shadow-black/60"></div>
    </div>
    <div class="relative mx-auto max-w-7xl px-2 sm:px-4 lg:px-8 z-10">
        <div class="relative flex h-16 items-center justify-between">
            <div class="flex items-center px-2 lg:px-0">
                <div class="shrink-0">
                    <a href="{{ route('home') }}">
                        <x-application-mark class="block h-9 w-auto"/>
                    </a>
                </div>
                <div class="hidden lg:ml-6 lg:block">
                    <div class="flex space-x-4">
                        <x-nav-link href="{{ route('static.installer') }}" :active="request()->routeIs('static.installer')">{{ __('Install') }}</x-nav-link>
                        <x-nav-link href="{{ route('mods') }}" :active="request()->routeIs('mods')">{{ __('Mods') }}</x-nav-link>
                        <a href="https://wiki.sp-tarkov.com/" target="_blank" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300/50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-white transition duration-150 ease-in-out inline-flex items-center gap-1">
                            {{ __('Wiki') }}
                            <flux:icon.arrow-top-right-on-square class="h-3 w-3" />
                        </a>
                        {{-- additional menu links here --}}
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
                    <flux:icon.bars-3 x-show="!mobileMenuOpen" class="h-6 w-6" />
                    <flux:icon.x-mark x-show="mobileMenuOpen" class="h-6 w-6" />
                </button>
            </div>
            <div class="hidden lg:ml-4 lg:block">
                <div class="flex items-center gap-4">
                    {{-- Desktop Theme Toggle --}}
                    <flux:dropdown align="end" x-data="">
                        <flux:button variant="subtle" class="group" aria-label="Preferred color scheme" square="true">
                            <flux:icon.sun x-show="$flux.appearance === 'light'" variant="mini" class="text-zinc-500 dark:text-white" />
                            <flux:icon.moon x-show="$flux.appearance === 'dark'" variant="mini" class="text-zinc-500 dark:text-white" />
                            <flux:icon.moon x-show="$flux.appearance === 'system' && $flux.dark" variant="mini" />
                            <flux:icon.sun x-show="$flux.appearance === 'system' && ! $flux.dark" variant="mini" />
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item icon="sun" x-on:click="$flux.appearance = 'light'">Light</flux:menu.item>
                            <flux:menu.item icon="moon" x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.item>
                            <flux:menu.item icon="computer-desktop" x-on:click="$flux.appearance = 'system'">System</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>

                    @auth
                        {{-- Profile Dropdown --}}
                        <div
                            x-data="{ profileDropdownOpen: false, openedWithKeyboard: false }"
                            x-on:keydown.esc.window="profileDropdownOpen = false, openedWithKeyboard = false"
                            class="relative"
                        >
                            <button
                                id="user-menu-button"
                                type="button"
                                x-on:click="profileDropdownOpen = ! profileDropdownOpen"
                                x-on:keydown.space.prevent="openedWithKeyboard = true"
                                x-on:keydown.enter.prevent="openedWithKeyboard = true"
                                x-on:keydown.down.prevent="openedWithKeyboard = true"
                                class="relative flex rounded-full bg-gray-800 text-sm text-white focus:outline-hidden focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-800"
                                :class="profileDropdownOpen || openedWithKeyboard ? 'text-black dark:text-white' : 'text-slate-700 dark:text-slate-300'"
                                :aria-expanded="profileDropdownOpen || openedWithKeyboard"
                                aria-haspopup="true"
                            >
                                <span class="absolute -inset-1.5"></span>
                                <span class="sr-only">{{ __('Open user menu') }}</span>
                                <img class="h-8 w-8 rounded-full" src="{{ auth()->user()->profile_photo_url }}" alt="{{ auth()->user()->name }}">
                            </button>
                            <div
                                x-cloak
                                x-show="profileDropdownOpen || openedWithKeyboard"
                                x-transition
                                x-trap="openedWithKeyboard"
                                x-on:click.outside="profileDropdownOpen = false, openedWithKeyboard = false"
                                x-on:keydown.down.prevent="$focus.wrap().next()"
                                x-on:keydown.up.prevent="$focus.wrap().previous()"
                                class="absolute top-11 right-0 z-[100] flex w-full min-w-[12rem] flex-col divide-y divide-slate-300 overflow-hidden rounded-xl border border-gray-300 bg-gray-100 dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800"
                                role="menu"
                            >
                                <div class="flex flex-col py-1.5">
                                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white" role="menuitem">
                                        <flux:icon.home class="w-4 h-4" />
                                        {{ __('Dashboard') }}
                                    </a>
                                    <a href="{{ auth()->user()->profile_url }}" class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white" role="menuitem">
                                        <flux:icon.user class="w-4 h-4" />
                                        {{ __('Profile') }}
                                    </a>
                                </div>
                                <div class="flex flex-col py-1.5">
                                    <a href="{{ route('profile.show') }}" class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white" role="menuitem">
                                        <flux:icon.cog-6-tooth class="w-4 h-4" />
                                        {{ __('Edit Profile') }}
                                    </a>
                                    <a href="{{ route('api-tokens.index') }}" class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white" role="menuitem">
                                        <flux:icon.key class="w-4 h-4" />
                                        {{ __('API Tokens') }}
                                    </a>
                                </div>
                                @if (auth()->user()->isAdmin())
                                    <div class="flex flex-col py-1.5">
                                        <a href="/pulse" class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white" role="menuitem">
                                            <flux:icon.eye class="w-4 h-4" />
                                            {{ __('Pulse Stats') }}
                                        </a>
                                        <a href="/horizon" class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white" role="menuitem">
                                            <flux:icon.queue-list class="w-4 h-4" />
                                            {{ __('Horizon Queue') }}
                                        </a>
                                        <a href="{{ route('admin.visitor-analytics') }}" class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white" role="menuitem">
                                            <flux:icon.chart-bar class="w-4 h-4" />
                                            {{ __('Visitor Analytics') }}
                                        </a>
                                        <a href="{{ route('admin.user-management') }}" class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white" role="menuitem">
                                            <flux:icon.users class="w-4 h-4" />
                                            {{ __('User Management') }}
                                        </a>
                                    </div>
                                @endif
                                <div class="flex flex-col py-1.5">
                                    <form method="POST" action="{{ route('logout') }}" x-data>
                                        @csrf
                                        <a
                                            href="{{ route('logout') }}"
                                            x-on:click.prevent="$root.submit();"
                                            class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                                            role="menuitem"
                                        >
                                            <flux:icon.arrow-right-start-on-rectangle class="w-4 h-4" />
                                            {{ __('Log Out') }}
                                        </a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endauth

                    @guest
                        <div>
                            <a href="{{ route('login') }}" class="text-sm font-semibold leading-6 text-gray-700 dark:text-gray-100 whitespace-nowrap">Log in <span aria-hidden="true">&rarr;</span></a>
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
                <flux:radio.group x-data="" variant="segmented" x-model="$flux.appearance">
                    <flux:radio class="cursor-pointer" value="light" icon="sun" />
                    <flux:radio class="cursor-pointer" value="dark" icon="moon" />
                    <flux:radio class="cursor-pointer" value="system" icon="computer-desktop" />
                </flux:radio.group>
            </div>
        </div>
        <div class="border-y border-gray-300 dark:border-gray-700 space-y-1 mx-3 py-3">
            <x-responsive-nav-link href="{{ route('static.installer') }}" :active="request()->routeIs('static.installer')">{{ __('Install') }}</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('mods') }}" :active="request()->routeIs('mods')">{{ __('Mods') }}</x-responsive-nav-link>
            <a href="https://wiki.sp-tarkov.com/" target="_blank" class="block rounded-md px-3 py-2 text-base font-medium text-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-800 dark:hover:text-white transition duration-150 ease-in-out flex items-center gap-2">
                {{ __('Wiki') }}
                <flux:icon.arrow-top-right-on-square class="h-4 w-4" />
            </a>
            {{-- Additional menu links here --}}
        </div>
        @auth
            <div class="flex items-center px-5 pt-3">
                <div class="shrink-0">
                    <img class="h-10 w-10 rounded-full" src="{{ auth()->user()->profile_photo_url }}" alt="{{ auth()->user()->name }}">
                </div>
                <div class="ml-3">
                    <div class="text-base font-medium text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</div>
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ auth()->user()->email }}</div>
                </div>
            </div>
            <div class="space-y-1 mx-3 py-3">
                <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">{{ __('Dashboard') }}</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ auth()->user()->profile_url }}" :active="request()->routeIs('user.show')">{{ __('Profile') }}</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('profile.show') }}" :active="request()->routeIs('profile.show')">{{ __('Edit Profile') }}</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('api-tokens.index') }}" :active="request()->routeIs('api-tokens.index')">{{ __('API Token') }}</x-responsive-nav-link>
                @if (auth()->user()->isAdmin())
                    <x-responsive-nav-link href="/pulse" :active="request()->routeIs('api-tokens.index')">{{ __('Pulse Stats') }}</x-responsive-nav-link>
                    <x-responsive-nav-link href="/horizon" :active="request()->routeIs('api-tokens.index')">{{ __('Horizon Queue') }}</x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ route('admin.visitor-analytics') }}" :active="request()->routeIs('admin.visitor-analytics')">{{ __('Visitor Analytics') }}</x-responsive-nav-link>
                    <x-responsive-nav-link href="{{ route('admin.user-management') }}" :active="request()->routeIs('admin.user-management')">{{ __('User Management') }}</x-responsive-nav-link>
                @endif
                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf
                    <x-responsive-nav-link href="{{ route('logout') }}" x-on:click.prevent="$root.submit();" :active="request()->routeIs('logout')">{{ __('Log Out') }}</x-responsive-nav-link>
                </form>
            </div>
        @endauth
        @guest
            <div class="space-y-1 mx-3 py-3">
                <x-responsive-nav-link href="{{ route('login') }}" :active="request()->routeIs('login')">{{ __('Log in') }}</x-responsive-nav-link>
                <x-responsive-nav-link href="{{ route('register') }}" :active="request()->routeIs('register')">{{ __('Register') }}</x-responsive-nav-link>
            </div>
        @endguest
    </div>
</nav>
