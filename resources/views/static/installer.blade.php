<x-layouts::base>
    <x-slot name="title">
        {{ __('SPT Installer') }}
    </x-slot>
    <x-slot name="description">
        {{ __('Simple installer for SPT, fully automated. Get up and running with Single Player Tarkov in minutes.') }}
    </x-slot>

    <x-slot name="header">
        <div class="flex w-full items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:icon
                    name="cloud-arrow-down"
                    class="size-8 text-gray-100"
                />
                <h2 class="text-xl font-semibold leading-tight text-gray-100">
                    {{ __('SPT Installer') }}
                </h2>
                <flux:badge
                    icon="check-circle"
                    color="green"
                    class="backdrop-blur-sm"
                >
                    Official
                </flux:badge>
            </div>
            <a
                href="https://ligma.waffle-lord.net/SPTInstaller.exe"
                target="_blank"
                class="group relative inline-flex transform items-center justify-center rounded-lg border border-emerald-600 bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-3 text-base font-semibold text-white shadow-xl backdrop-blur-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-emerald-500 hover:from-emerald-600 hover:to-emerald-500 hover:shadow-2xl hover:shadow-emerald-500/25 active:scale-95"
            >
                {{-- Animated background effect --}}
                <div
                    class="absolute inset-0 rounded-lg bg-gradient-to-r from-emerald-600 to-emerald-500 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                </div>

                {{-- Button content --}}
                <div class="relative flex items-center">
                    <flux:icon
                        name="cloud-arrow-down"
                        class="mr-2 h-5 w-5 transform transition-transform duration-200 group-hover:scale-110"
                    />
                    Download Installer
                </div>

                {{-- Shine effect --}}
                <div class="absolute inset-0 overflow-hidden rounded-lg opacity-0 group-hover:opacity-100">
                    <div
                        class="absolute inset-0 w-1/2 -translate-x-full -skew-x-12 transform bg-gradient-to-r from-transparent via-white/15 to-transparent transition-transform duration-500 ease-out group-hover:translate-x-full">
                    </div>
                </div>
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-none bg-gray-900 shadow-xl shadow-gray-900 sm:rounded-lg">
            <div class="px-4 py-8 sm:px-6 lg:px-8">

                {{-- Hero Section --}}
                <div
                    class="relative mb-12 overflow-hidden rounded-xl border border-gray-800 bg-gradient-to-br from-gray-900 via-black to-gray-950 shadow-2xl">
                    {{-- Light/Dark gritty texture overlays --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-gray-800/20 via-transparent to-black/30">
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-tl from-gray-700/10 to-transparent">
                    </div>

                    {{-- Noise/grain pattern for texture --}}
                    <div
                        class="absolute inset-0 opacity-25"
                        style="background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.08) 1px, transparent 0); background-size: 4px 4px;"
                    ></div>
                    <div
                        class="absolute inset-0 opacity-25"
                        style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.05) 1px, transparent 0); background-size: 4px 4px;"
                    ></div>

                    {{-- Industrial wear patterns --}}
                    <div class="absolute left-1/4 top-0 h-px w-32 rotate-2 transform bg-gray-600/20">
                    </div>
                    <div class="absolute bottom-0 right-1/3 h-px w-24 -rotate-1 transform bg-gray-500/15">
                    </div>
                    <div class="absolute right-0 top-1/3 h-16 w-px bg-gray-600/10"></div>

                    {{-- Subtle inner shadow for depth --}}
                    <div class="absolute inset-0 rounded-xl shadow-inner shadow-black/40"></div>

                    <div class="relative px-6 py-10 sm:px-8 sm:py-12">
                        <div class="mx-auto max-w-4xl text-center">
                            <div class="mb-6 flex justify-center">
                                <div
                                    class="relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-lg border border-gray-700 bg-gradient-to-br from-gray-800 via-gray-900 to-black shadow-2xl">
                                    {{-- Light/Dark gritty texture overlays --}}
                                    <div
                                        class="absolute inset-0 bg-gradient-to-br from-gray-700/30 via-transparent to-gray-900/40">
                                    </div>
                                    <div class="absolute inset-0 bg-gradient-to-tl from-gray-600/20 to-transparent">
                                    </div>

                                    {{-- Noise/grain effect --}}
                                    <div
                                        class="absolute inset-0 opacity-40"
                                        style="background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.15) 1px, transparent 0); background-size: 3px 3px;"
                                    ></div>
                                    <div
                                        class="absolute inset-0 opacity-40"
                                        style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.1) 1px, transparent 0); background-size: 3px 3px;"
                                    ></div>

                                    <flux:icon
                                        name="cloud-arrow-down"
                                        class="relative z-10 size-12 text-gray-200 drop-shadow-2xl"
                                    />
                                </div>
                            </div>

                            <h1 class="mb-4 text-3xl font-bold tracking-tight text-gray-100 sm:text-4xl">
                                SPT Installer
                            </h1>
                            <p class="mb-6 text-lg text-gray-300">
                                Get up and running with Single Player Tarkov in a few clicks. Our installer handles all
                                the complex setup for you.
                            </p>

                            {{-- Compact features --}}
                            <div class="flex flex-wrap justify-center gap-6 text-sm text-gray-400">
                                <span class="flex items-center">
                                    <flux:icon.check-circle
                                        variant="mini"
                                        class="mr-2 size-4 text-green-500"
                                    />
                                    Lightning Fast Setup
                                </span>
                                <span class="flex items-center">
                                    <flux:icon.check-circle
                                        variant="mini"
                                        class="mr-2 size-4 text-green-500"
                                    />
                                    Fully Automated Reliability
                                </span>
                                <span class="flex items-center">
                                    <flux:icon.check-circle
                                        variant="mini"
                                        class="mr-2 size-4 text-green-500"
                                    />
                                    Beginner Friendly
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Two Column Layout for Installer Info & Warning --}}
                <div class="mb-8 grid grid-cols-1 gap-8 lg:grid-cols-2 lg:items-stretch">
                    {{-- What the Installer Does --}}
                    <div class="relative h-full overflow-hidden">
                        {{-- Background Icon --}}
                        <div class="pointer-events-none absolute -right-8 -top-8 z-0 select-none text-blue-400/10">
                            <flux:icon
                                name="cog-6-tooth"
                                class="size-48"
                            />
                        </div>
                        <div class="relative z-10 h-full rounded-xl border border-blue-800 bg-blue-950/50 p-6">
                            <h3 class="mb-4 text-lg font-semibold text-blue-100">The Installer Will:
                            </h3>
                            <ul class="space-y-3">
                                <li class="flex items-start">
                                    <flux:icon
                                        name="check-circle"
                                        class="mr-3 mt-1 size-4 flex-shrink-0 text-blue-600"
                                    />
                                    <span class="text-blue-200">Check you have the correct version of
                                        .NET installed</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon
                                        name="check-circle"
                                        class="mr-3 mt-1 size-4 flex-shrink-0 text-blue-600"
                                    />
                                    <span class="text-blue-200">Duplicate your EFT installation to a
                                        selected folder</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon
                                        name="check-circle"
                                        class="mr-3 mt-1 size-4 flex-shrink-0 text-blue-600"
                                    />
                                    <span class="text-blue-200">Patch the duplicated EFT files to the
                                        version required for SPT</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon
                                        name="check-circle"
                                        class="mr-3 mt-1 size-4 flex-shrink-0 text-blue-600"
                                    />
                                    <span class="text-blue-200">Download the most recent version of
                                        SPT and install it into the duplicated folder</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    {{-- Warning Section --}}
                    <div class="relative h-full overflow-hidden">
                        {{-- Background Icon --}}
                        <div class="pointer-events-none absolute -right-8 -top-8 z-0 select-none text-yellow-400/10">
                            <flux:icon
                                name="exclamation-triangle"
                                class="size-48"
                            />
                        </div>
                        <div class="relative z-10 h-full rounded-xl border border-yellow-800 bg-yellow-950/50 p-6">
                            <h3 class="mb-4 text-lg font-semibold text-yellow-100">Things to Note
                            </h3>
                            <ul class="space-y-3">
                                <li class="flex items-start">
                                    <flux:icon
                                        name="arrow-right"
                                        class="mr-3 mt-1 size-4 flex-shrink-0 text-yellow-600"
                                    />
                                    <span class="text-yellow-200">Make sure you have a legitimate
                                        copy of Escape From Tarkov installed and up-to-date before running the
                                        installer</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon
                                        name="arrow-right"
                                        class="mr-3 mt-1 size-4 flex-shrink-0 text-yellow-600"
                                    />
                                    <span class="text-yellow-200">Do not place this installer on
                                        your desktop and run it</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon
                                        name="arrow-right"
                                        class="mr-3 mt-1 size-4 flex-shrink-0 text-yellow-600"
                                    />
                                    <span class="text-yellow-200">The installer downloads SPT and
                                        patcher files to: <br /><code
                                            class="whitespace-nowrap rounded bg-gray-800 px-1 py-0.5 text-xs"
                                        >%appdata%\spt-installer\cache</code></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Two Column Layout for Usage Guide & How to Run SPT --}}
                <div class="mb-8 grid grid-cols-1 gap-8 lg:grid-cols-2 lg:items-stretch">
                    {{-- Usage Guide --}}
                    <div class="relative h-full overflow-hidden">
                        {{-- Background Icon --}}
                        <div class="pointer-events-none absolute -right-8 -top-8 z-0 select-none text-green-400/10">
                            <flux:icon
                                name="book-open"
                                class="size-48"
                            />
                        </div>
                        <div class="relative z-10 h-full rounded-xl border border-green-800 bg-green-950/50 p-6">
                            <h3 class="mb-4 text-lg font-semibold text-green-100">Step-by-Step
                                Usage Guide</h3>
                            <ol class="space-y-3">
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-900 text-sm font-medium text-green-200"
                                    >1</span>
                                    <span class="text-green-200">Download installer</span>
                                </li>
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-900 text-sm font-medium text-green-200"
                                    >2</span>
                                    <span class="text-green-200">Place in a new folder. We
                                        recommend: <br /><code
                                            class="whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-sm"
                                        >C:\Games\SPT-4.0\</code></span>
                                </li>
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-900 text-sm font-medium text-green-200"
                                    >3</span>
                                    <span class="text-green-200">Ensure your EFT install is updated
                                        to the latest version</span>
                                </li>
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-900 text-sm font-medium text-green-200"
                                    >4</span>
                                    <div class="text-green-200">
                                        <span>Run installer</span>
                                        <div class="ml-2 mt-1 text-sm text-red-400">
                                            <flux:icon
                                                name="exclamation-triangle"
                                                class="mr-1 inline size-4"
                                            />
                                            If something shows as <span class="font-medium">RED</span>, you should read
                                            carefully!
                                        </div>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-900 text-sm font-medium text-green-200"
                                    >5</span>
                                    <span class="text-green-200">Wait for it to say it has
                                        completed</span>
                                </li>
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-green-900 text-sm font-medium text-green-200"
                                    >6</span>
                                    <span class="text-green-200">Close the installer. You are ready
                                        to play!</span>
                                </li>
                            </ol>
                        </div>
                    </div>

                    {{-- How to Run SPT --}}
                    <div class="relative h-full overflow-hidden">
                        {{-- Background Icon --}}
                        <div class="pointer-events-none absolute -right-8 -top-8 z-0 select-none text-cyan-400/10">
                            <flux:icon
                                name="play"
                                class="size-48"
                            />
                        </div>
                        <div class="relative z-10 h-full rounded-xl border border-cyan-800 bg-cyan-950/50 p-6">
                            <h3 class="mb-4 text-lg font-semibold text-cyan-100">How to Run SPT
                                After Installation</h3>
                            <ol class="space-y-3">
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-cyan-900 text-sm font-medium text-cyan-200"
                                    >1</span>
                                    <span class="text-cyan-200">Start the <code
                                            class="rounded bg-gray-800 px-2 py-1 text-sm"
                                        >Spt.Server.exe</code> and wait for it to say <span
                                            class="font-medium text-green-600"
                                        >Happy Playing!</span></span>
                                </li>
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-cyan-900 text-sm font-medium text-cyan-200"
                                    >2</span>
                                    <span class="text-cyan-200">Start the <code
                                            class="rounded bg-gray-800 px-2 py-1 text-sm"
                                        >Spt.Launcher.exe</code></span>
                                </li>
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-cyan-900 text-sm font-medium text-cyan-200"
                                    >3</span>
                                    <span class="text-cyan-200">Create a profile</span>
                                </li>
                                <li class="flex items-start">
                                    <span
                                        class="mr-3 mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-cyan-900 text-sm font-medium text-cyan-200"
                                    >4</span>
                                    <span class="text-cyan-200">Launch the game</span>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>

                {{-- Video Guide Section --}}
                <div
                    id="video-guide"
                    class="text-center"
                >
                    <h3 class="mb-2 text-lg font-semibold text-gray-200">Video Installation Guide
                    </h3>
                    <p class="mb-6 text-sm text-gray-400">Created by McDewgle</p>
                    <div class="mx-auto max-w-4xl">
                        <div
                            class="relative overflow-hidden rounded-xl bg-gradient-to-br from-gray-800 to-gray-900 p-2 shadow-2xl">
                            <div class="aspect-video w-full">
                                <video
                                    class="h-full w-full rounded-lg"
                                    controls
                                    preload="metadata"
                                    poster="{{ Vite::asset('resources/images/installation-guide-poster.png') }}"
                                >
                                    <source
                                        src="{{ Vite::asset('resources/video/installation-guide.mp4') }}"
                                        type="video/mp4"
                                    >
                                    <p class="p-4 text-center text-gray-400">
                                        Your browser does not support the video element. Please upgrade your browser to
                                        view the installation guide.
                                    </p>
                                </video>
                            </div>
                        </div>
                        <p class="mt-4 text-sm text-gray-400">
                            Step-by-step walkthrough of the SPT installation process
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::base>
