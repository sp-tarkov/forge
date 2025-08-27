<x-slot:title>
    The Forge - Home of Single Player Tarkov Mods
</x-slot>

<x-slot:description>
    The greatest resource available for Single Player Tarkov modifications. Where modding legends are made. Discover powerful tools, expert-written guides, and exclusive mods. Transform the game.
</x-slot>

<x-slot:header></x-slot>

<div>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">
            <div class="relative isolate overflow-hidden bg-gray-900 dark:bg-gray-800 px-6 py-24 sm:py-32 lg:px-8 rounded-none sm:rounded-md">
                <video autoplay muted loop class="absolute inset-0 -z-10 h-full w-full object-cover z-0">
                    <source src="{{ Vite::asset('resources/video/welcome.mp4') }}" type="video/mp4">
                </video>
                <div class="hidden sm:absolute sm:-top-10 sm:right-1/2 sm:-z-11 sm:mr-10 sm:block sm:transform-gpu sm:blur-3xl" aria-hidden="true">
                    <div class="aspect-1097/845 w-[68.5625rem] bg-linear-to-tr from-[#333] to-[#000] opacity-20" style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)"></div>
                </div>
                <div class="absolute -top-52 left-1/2 -z-11 -translate-x-1/2 transform-gpu blur-3xl sm:top-[-28rem] sm:ml-16 sm:translate-x-0 sm:transform-gpu" aria-hidden="true">
                    <div class="aspect-1097/845 w-[68.5625rem] bg-linear-to-tr from-[#333] to-[#000] opacity-20" style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)"></div>
                </div>
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-4xl font-bold tracking-tight text-white sm:text-6xl drop-shadow-md">Step into
                        <em class="text-gray-200">{{ config('app.name', 'The Forge') }}</em></h2>
                    <p class="mt-6 text-lg leading-8 text-gray-300 drop-shadow-md">The greatest resource available for Single Player Tarkov modifications. Where modding legends are made. Discover powerful tools, expert-written guides, and exclusive mods. Craft your vision. Transform the game.</p>
                </div>
            </div>

            <div class="pb-10">
                <div class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
                    <!-- Large Installer Callout -->
                    <div class="relative overflow-hidden bg-gradient-to-br from-gray-900 via-black to-gray-950 dark:from-black dark:via-gray-950 dark:to-black border border-gray-800 dark:border-gray-900 rounded-xl shadow-2xl mb-16">
                        <!-- Dark gritty texture overlays -->
                        <div class="absolute inset-0 bg-gradient-to-br from-gray-800/20 via-transparent to-black/30"></div>
                        <div class="absolute inset-0 bg-gradient-to-tl from-gray-700/10 to-transparent"></div>

                        <!-- Noise/grain pattern for texture -->
                        <div class="absolute inset-0 opacity-25" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.05) 1px, transparent 0); background-size: 4px 4px;"></div>

                        <!-- Industrial wear patterns -->
                        <div class="absolute top-0 left-1/4 w-32 h-px bg-gray-600/20 transform rotate-2"></div>
                        <div class="absolute bottom-0 right-1/3 w-24 h-px bg-gray-500/15 transform -rotate-1"></div>
                        <div class="absolute top-1/3 right-0 w-px h-16 bg-gray-600/10"></div>

                        <!-- Subtle inner shadow for depth -->
                        <div class="absolute inset-0 rounded-xl shadow-inner shadow-black/40"></div>

                        <div class="relative px-6 py-8 sm:px-8 sm:py-10">
                            <div class="mx-auto max-w-4xl">
                                <div class="flex flex-col lg:flex-row items-center gap-8">
                                    <!-- Left side - Large colored icon -->
                                    <div class="flex-shrink-0">
                                        <div class="relative mx-auto lg:mx-0 flex h-30 w-30 items-center justify-center rounded-lg bg-gradient-to-br from-gray-800 via-gray-900 to-black dark:from-gray-900 dark:via-black dark:to-gray-950 shadow-2xl overflow-hidden border border-gray-700 dark:border-gray-800">
                                            <!-- Gritty texture overlays -->
                                            <div class="absolute inset-0 bg-gradient-to-br from-gray-700/30 via-transparent to-gray-900/40"></div>
                                            <div class="absolute inset-0 bg-gradient-to-tl from-gray-600/20 to-transparent"></div>

                                            <!-- Noise/grain effect -->
                                            <div class="absolute inset-0 opacity-40" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.1) 1px, transparent 0); background-size: 3px 3px;"></div>

                                            <!-- Industrial accent elements -->
                                            <div class="absolute top-1 left-1 w-4 h-0.5 bg-gray-600/60"></div>
                                            <div class="absolute top-1 left-1 w-0.5 h-4 bg-gray-600/60"></div>
                                            <div class="absolute bottom-1 right-1 w-4 h-0.5 bg-gray-600/60"></div>
                                            <div class="absolute bottom-1 right-1 w-0.5 h-4 bg-gray-600/60"></div>

                                            <!-- Scratched/worn effect lines -->
                                            <div class="absolute top-3 right-2 w-8 h-px bg-gray-500/30 transform rotate-12"></div>
                                            <div class="absolute bottom-4 left-3 w-6 h-px bg-gray-500/40 transform -rotate-45"></div>
                                            <div class="absolute top-1/2 right-3 w-3 h-px bg-gray-400/20 transform rotate-75"></div>

                                            <!-- Subtle inner shadow -->
                                            <div class="absolute inset-1 rounded-md shadow-inner shadow-black/50"></div>

                                            <flux:icon name="cloud-arrow-down" class="relative z-10 size-20 text-gray-200 drop-shadow-2xl" />
                                        </div>
                                    </div>

                                    <!-- Middle - Content -->
                                    <div class="flex-1 text-center lg:text-left">
                                        <div class="flex flex-col lg:flex-row lg:items-center lg:gap-3 mb-2">
                                            <h2 class="text-2xl font-bold tracking-tight text-gray-100 sm:text-3xl">
                                                SPT Installer
                                            </h2>
                                            <!-- Official badge moved next to title -->
                                            <flux:badge icon="check-circle" color="green" class="mt-2 lg:mt-0 mx-auto lg:mx-0 backdrop-blur-sm">
                                                Official
                                            </flux:badge>
                                        </div>

                                        <p class="text-gray-300 mb-4">
                                            The easiest way to get started with Single Player Tarkov. Our fully automated installer handles everything for you.
                                        </p>

                                        <!-- Compact features -->
                                        <div class="flex flex-wrap justify-center lg:justify-start gap-4 text-sm text-gray-400">
                                            <span class="flex items-center">
                                                <svg class="h-3 w-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                                Lightning Fast
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="h-3 w-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                                Reliable
                                            </span>
                                            <span class="flex items-center">
                                                <svg class="h-3 w-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                                User Friendly
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Right side - Centered CTA Button -->
                                    <div class="flex items-center justify-center w-full lg:w-auto lg:flex-shrink-0">
                                        <a href="{{ route('static.installer') }}" class="group relative inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-emerald-700 to-emerald-600 px-8 py-4 text-base font-semibold text-white shadow-xl border border-emerald-600 hover:from-emerald-600 hover:to-emerald-500 hover:border-emerald-500 hover:shadow-2xl hover:shadow-emerald-500/25 active:scale-95 transition-all duration-300 backdrop-blur-sm transform hover:-translate-y-0.5">
                                            <!-- Animated background effect -->
                                            <div class="absolute inset-0 rounded-lg bg-gradient-to-r from-emerald-600 to-emerald-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                            
                                            <!-- Button content -->
                                            <div class="relative flex items-center">
                                                <flux:icon name="cloud-arrow-down" class="mr-3 h-5 w-5 transform group-hover:scale-110 transition-transform duration-200" />
                                                Download Installer
                                                <flux:icon name="arrow-right" class="ml-3 h-4 w-4 transform group-hover:translate-x-1 group-hover:scale-110 transition-all duration-200" />
                                            </div>
                                            
                                            <!-- Shine effect -->
                                            <div class="absolute inset-0 rounded-lg overflow-hidden opacity-0 group-hover:opacity-100">
                                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/15 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-500 ease-out w-1/2"></div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <x-page-content-title :title="__('Featured Mods')" :button-text="__('View All')" button-link="/mods?featured=only" />
                    <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        @foreach ($featured as $mod)
                            <div wire:key="homepage-featured-{{ $mod->id }}">
                                <x-mod.card :mod="$mod" :version="$mod->latestVersion" section="featured" homepage-featured />
                            </div>
                        @endforeach
                    </div>

                    <x-page-content-title :title="__('Newest Mods')" :button-text="__('View All')" button-link="/mods" />
                    <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        @foreach ($newest as $mod)
                            <div wire:key="homepage-newest-{{ $mod->id }}">
                                <x-mod.card :mod="$mod" :version="$mod->latestVersion" section="newest" />
                            </div>
                        @endforeach
                    </div>

                    <x-page-content-title :title="__('Recently Updated Mods')" :button-text="__('View All')" button-link="/mods?order=updated" />
                    <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                        @foreach ($updated as $mod)
                            <div wire:key="homepage-updated-{{ $mod->id }}">
                                <x-mod.card :mod="$mod" :version="$mod->latestUpdatedVersion" section="updated" />
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
