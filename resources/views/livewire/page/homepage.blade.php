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
