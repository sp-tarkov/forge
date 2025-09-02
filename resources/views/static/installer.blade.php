<x-layouts.base>
    <x-slot name="title">
        {{ __('SPT Installer') }}
    </x-slot>
    <x-slot name="description">
        {{ __('Simple installer for SPT, fully automated. Get up and running with Single Player Tarkov in minutes.') }}
    </x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <flux:icon name="cloud-arrow-down" class="size-8 text-gray-800 dark:text-gray-100" />
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                    {{ __('SPT Installer') }}
                </h2>
                <flux:badge icon="check-circle" color="green" class="backdrop-blur-sm">
                    Official
                </flux:badge>
            </div>
            <a href="https://ligma.waffle-lord.net/SPTInstaller.exe" target="_blank" class="group relative inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-3 text-base font-semibold text-white shadow-xl border border-emerald-600 hover:from-emerald-600 hover:to-emerald-500 hover:border-emerald-500 hover:shadow-2xl hover:shadow-emerald-500/25 active:scale-95 transition-all duration-300 backdrop-blur-sm transform hover:-translate-y-0.5">
                {{-- Animated background effect --}}
                <div class="absolute inset-0 rounded-lg bg-gradient-to-r from-emerald-600 to-emerald-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                {{-- Button content --}}
                <div class="relative flex items-center">
                    <flux:icon name="cloud-arrow-down" class="mr-2 h-5 w-5 transform group-hover:scale-110 transition-transform duration-200" />
                    Download Installer
                </div>

                {{-- Shine effect --}}
                <div class="absolute inset-0 rounded-lg overflow-hidden opacity-0 group-hover:opacity-100">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/15 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-500 ease-out w-1/2"></div>
                </div>
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">
            <div class="px-4 py-8 sm:px-6 lg:px-8">

                {{-- Hero Section --}}
                <div class="relative overflow-hidden bg-gradient-to-br from-gray-100 via-white to-gray-50 dark:from-gray-900 dark:via-black dark:to-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl shadow-2xl mb-12">
                    {{-- Light/Dark gritty texture overlays --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-gray-200/30 via-transparent to-gray-300/20 dark:from-gray-800/20 dark:via-transparent dark:to-black/30"></div>
                    <div class="absolute inset-0 bg-gradient-to-tl from-gray-300/15 to-transparent dark:from-gray-700/10 dark:to-transparent"></div>

                    {{-- Noise/grain pattern for texture --}}
                    <div class="absolute inset-0 opacity-20 dark:opacity-25" style="background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.08) 1px, transparent 0); background-size: 4px 4px;"></div>
                    <div class="absolute inset-0 opacity-0 dark:opacity-25" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.05) 1px, transparent 0); background-size: 4px 4px;"></div>

                    {{-- Industrial wear patterns --}}
                    <div class="absolute top-0 left-1/4 w-32 h-px bg-gray-400/25 dark:bg-gray-600/20 transform rotate-2"></div>
                    <div class="absolute bottom-0 right-1/3 w-24 h-px bg-gray-400/20 dark:bg-gray-500/15 transform -rotate-1"></div>
                    <div class="absolute top-1/3 right-0 w-px h-16 bg-gray-400/15 dark:bg-gray-600/10"></div>

                    {{-- Subtle inner shadow for depth --}}
                    <div class="absolute inset-0 rounded-xl shadow-inner shadow-gray-400/30 dark:shadow-black/40"></div>

                    <div class="relative px-6 py-10 sm:px-8 sm:py-12">
                        <div class="mx-auto max-w-4xl text-center">
                            <div class="mb-6 flex justify-center">
                                <div class="relative flex h-24 w-24 items-center justify-center rounded-lg bg-gradient-to-br from-gray-200 via-gray-300 to-gray-400 dark:from-gray-800 dark:via-gray-900 dark:to-black shadow-2xl overflow-hidden border border-gray-300 dark:border-gray-700">
                                    {{-- Light/Dark gritty texture overlays --}}
                                    <div class="absolute inset-0 bg-gradient-to-br from-gray-400/20 via-transparent to-gray-500/30 dark:from-gray-700/30 dark:via-transparent dark:to-gray-900/40"></div>
                                    <div class="absolute inset-0 bg-gradient-to-tl from-gray-500/15 to-transparent dark:from-gray-600/20 dark:to-transparent"></div>

                                    {{-- Noise/grain effect --}}
                                    <div class="absolute inset-0 opacity-30 dark:opacity-40" style="background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.15) 1px, transparent 0); background-size: 3px 3px;"></div>
                                    <div class="absolute inset-0 opacity-0 dark:opacity-40" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.1) 1px, transparent 0); background-size: 3px 3px;"></div>

                                    <flux:icon name="cloud-arrow-down" class="relative z-10 size-12 text-gray-700 dark:text-gray-200 drop-shadow-2xl" />
                                </div>
                            </div>

                            <h1 class="text-3xl font-bold tracking-tight text-gray-800 dark:text-gray-100 sm:text-4xl mb-4">
                                SPT Installer
                            </h1>
                            <p class="text-lg text-gray-600 dark:text-gray-300 mb-6">
                                Get up and running with Single Player Tarkov in a few clicks. Our installer handles all the complex setup for you.
                            </p>

                            {{-- Compact features --}}
                            <div class="flex flex-wrap justify-center gap-6 text-sm text-gray-500 dark:text-gray-400">
                                <span class="flex items-center">
                                    <svg class="h-4 w-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    Lightning Fast Setup
                                </span>
                                <span class="flex items-center">
                                    <svg class="h-4 w-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    Fully Automated Reliability
                                </span>
                                <span class="flex items-center">
                                    <svg class="h-4 w-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    Beginner Friendly
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Two Column Layout for Installer Info & Warning --}}
                <div class="mb-8 grid grid-cols-1 lg:grid-cols-2 gap-8 lg:items-stretch">
                    {{-- What the Installer Does --}}
                    <div class="relative h-full overflow-hidden">
                        {{-- Background Icon --}}
                        <div class="absolute -top-8 -right-8 text-blue-600/15 dark:text-blue-400/10 select-none pointer-events-none z-0">
                            <flux:icon name="cog-6-tooth" class="size-48" />
                        </div>
                        <div class="relative z-10 h-full bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
                            <h3 class="font-semibold text-lg text-blue-900 dark:text-blue-100 mb-4">The Installer Will:</h3>
                            <ul class="space-y-3">
                                <li class="flex items-start">
                                    <flux:icon name="check-circle" class="size-4 mt-1 mr-3 text-blue-600 flex-shrink-0" />
                                    <span class="text-blue-800 dark:text-blue-200">Check you have the correct version of .NET installed</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon name="check-circle" class="size-4 mt-1 mr-3 text-blue-600 flex-shrink-0" />
                                    <span class="text-blue-800 dark:text-blue-200">Duplicate your EFT installation to a selected folder</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon name="check-circle" class="size-4 mt-1 mr-3 text-blue-600 flex-shrink-0" />
                                    <span class="text-blue-800 dark:text-blue-200">Patch the duplicated EFT files to the version required for SPT</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon name="check-circle" class="size-4 mt-1 mr-3 text-blue-600 flex-shrink-0" />
                                    <span class="text-blue-800 dark:text-blue-200">Download most recent version of SPT and install it into the duplicated folder</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    {{-- Warning Section --}}
                    <div class="relative h-full overflow-hidden">
                        {{-- Background Icon --}}
                        <div class="absolute -top-8 -right-8 text-yellow-600/15 dark:text-yellow-400/10 select-none pointer-events-none z-0">
                            <flux:icon name="exclamation-triangle" class="size-48" />
                        </div>
                        <div class="relative z-10 h-full bg-yellow-50 dark:bg-yellow-950/50 border border-yellow-200 dark:border-yellow-800 rounded-xl p-6">
                            <h3 class="font-semibold text-lg text-yellow-900 dark:text-yellow-100 mb-4">Things to Note</h3>
                            <ul class="space-y-3">
                                <li class="flex items-start">
                                    <flux:icon name="arrow-right" class="size-4 mt-1 mr-3 text-yellow-600 flex-shrink-0" />
                                    <span class="text-yellow-800 dark:text-yellow-200">Make sure you have a legitimate copy of Escape From Tarkov installed and up-to-date before running the installer</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon name="arrow-right" class="size-4 mt-1 mr-3 text-yellow-600 flex-shrink-0" />
                                    <span class="text-yellow-800 dark:text-yellow-200">Do not place this installer on your desktop and run it</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon name="arrow-right" class="size-4 mt-1 mr-3 text-yellow-600 flex-shrink-0" />
                                    <span class="text-yellow-800 dark:text-yellow-200">The installer downloads SPT and patcher files to: <br /><code class="bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded text-xs whitespace-nowrap">%appdata%\spt-installer\cache</code></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Two Column Layout for Usage Guide & How to Run SPT --}}
                <div class="mb-8 grid grid-cols-1 lg:grid-cols-2 gap-8 lg:items-stretch">
                    {{-- Usage Guide --}}
                    <div class="relative h-full overflow-hidden">
                        {{-- Background Icon --}}
                        <div class="absolute -top-8 -right-8 text-green-600/15 dark:text-green-400/10 select-none pointer-events-none z-0">
                            <flux:icon name="book-open" class="size-48" />
                        </div>
                        <div class="relative z-10 h-full bg-green-50 dark:bg-green-950/50 border border-green-200 dark:border-green-800 rounded-xl p-6">
                            <h3 class="font-semibold text-lg text-green-900 dark:text-green-100 mb-4">Step-by-Step Usage Guide</h3>
                            <ol class="space-y-3">
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">1</span>
                                    <span class="text-green-800 dark:text-green-200">Download installer</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">2</span>
                                    <span class="text-green-800 dark:text-green-200">Place in a new folder. We recommend: <br /><code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-sm whitespace-nowrap">C:\Games\SPT-3.11\</code></span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">3</span>
                                    <span class="text-green-800 dark:text-green-200">Ensure your EFT install is updated to the latest version</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">4</span>
                                    <div class="text-green-800 dark:text-green-200">
                                        <span>Run installer</span>
                                        <div class="mt-1 ml-2 text-sm text-red-600 dark:text-red-400">
                                            <flux:icon name="exclamation-triangle" class="size-4 inline mr-1" />
                                            If something shows as <span class="font-medium">RED</span>, you should read carefully!
                                        </div>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">5</span>
                                    <span class="text-green-800 dark:text-green-200">Wait for it to say it has completed</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">6</span>
                                    <span class="text-green-800 dark:text-green-200">Close the installer. You are ready to play!</span>
                                </li>
                            </ol>
                        </div>
                    </div>

                    {{-- How to Run SPT --}}
                    <div class="relative h-full overflow-hidden">
                        {{-- Background Icon --}}
                        <div class="absolute -top-8 -right-8 text-cyan-600/15 dark:text-cyan-400/10 select-none pointer-events-none z-0">
                            <flux:icon name="play" class="size-48" />
                        </div>
                        <div class="relative z-10 h-full bg-cyan-50 dark:bg-cyan-950/50 border border-cyan-200 dark:border-cyan-800 rounded-xl p-6">
                            <h3 class="font-semibold text-lg text-cyan-900 dark:text-cyan-100 mb-4">How to Run SPT After Installation</h3>
                            <ol class="space-y-3">
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-cyan-100 dark:bg-cyan-900 text-cyan-800 dark:text-cyan-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">1</span>
                                    <span class="text-cyan-800 dark:text-cyan-200">Start the <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-sm">Spt.Server.exe</code> and wait for it to say <span class="text-green-600 font-medium">Happy Playing!</span></span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-cyan-100 dark:bg-cyan-900 text-cyan-800 dark:text-cyan-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">2</span>
                                    <span class="text-cyan-800 dark:text-cyan-200">Start the <code class="bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-sm">Spt.Launcher.exe</code></span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-cyan-100 dark:bg-cyan-900 text-cyan-800 dark:text-cyan-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">3</span>
                                    <span class="text-cyan-800 dark:text-cyan-200">Create a profile</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="flex-shrink-0 w-6 h-6 bg-cyan-100 dark:bg-cyan-900 text-cyan-800 dark:text-cyan-200 rounded-full flex items-center justify-center text-sm font-medium mr-3 mt-0.5">4</span>
                                    <span class="text-cyan-800 dark:text-cyan-200">Launch the game</span>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>

                {{-- Video Guide Section --}}
                <div id="video-guide" class="text-center">
                    <h3 class="font-semibold text-lg mb-2 text-gray-800 dark:text-gray-200">Video Installation Guide</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Created by McDewgle</p>
                    <div class="max-w-4xl mx-auto">
                        <div class="relative overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 rounded-xl shadow-2xl p-2">
                            <div class="aspect-video w-full">
                                <video
                                    class="w-full h-full rounded-lg"
                                    controls
                                    preload="metadata"
                                    poster="{{ Vite::asset('resources/images/installation-guide-poster.png') }}"
                                >
                                    <source src="{{ Vite::asset('resources/video/installation-guide.mp4') }}" type="video/mp4">
                                    <p class="text-center p-4 text-gray-600 dark:text-gray-400">
                                        Your browser does not support the video element. Please upgrade your browser to view the installation guide.
                                    </p>
                                </video>
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                            Step-by-step walkthrough of the SPT installation process
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.base>
