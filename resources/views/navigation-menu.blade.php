<nav
    x-data="{ profileDropdownOpen: false, mobileMenuOpen: false }"
    class="bg-white dark:bg-gray-950 border-b border-gray-100 dark:border-gray-800"
>
    <div class="mx-auto max-w-7xl px-2 sm:px-4 lg:px-8">
        <div class="relative flex h-16 items-center justify-between">
            <div class="flex items-center px-2 lg:px-0">
                <div class="shrink-0">
                    <a href="{{ route('home') }}">
                        <x-application-mark class="block h-9 w-auto"/>
                    </a>
                </div>
                <div class="hidden lg:ml-6 lg:block">
                    <div class="flex space-x-4">
                        <x-nav-link href="{{ route('installer') }}" :active="request()->routeIs('installer')">{{ __('Install') }}</x-nav-link>
                        <x-nav-link href="{{ route('mods') }}" :active="request()->routeIs('mods')">{{ __('Mods') }}</x-nav-link>
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
                    class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-700 hover:text-white focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-white"
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
                                class="absolute top-11 right-0 z-10 flex w-full min-w-[12rem] flex-col divide-y divide-slate-300 overflow-hidden rounded-xl border border-gray-300 bg-gray-100 dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800"
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
                            <a href="{{ route('login') }}" class="text-sm font-semibold leading-6 text-gray-900 dark:text-gray-100 whitespace-nowrap">Log in <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    @endguest
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div
        class="lg:hidden"
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
            <x-responsive-nav-link href="{{ route('installer') }}" :active="request()->routeIs('installer')">{{ __('Install') }}</x-responsive-nav-link>
            <x-responsive-nav-link href="{{ route('mods') }}" :active="request()->routeIs('mods')">{{ __('Mods') }}</x-responsive-nav-link>
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
