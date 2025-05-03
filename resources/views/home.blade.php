<x-app-layout>
    <x-slot name="title">
        {{ __('The Forge - Single Player Tarkov Mods') }}
    </x-slot>

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
                <livewire:mod.homepage />
            </div>
        </div>
    </div>
</x-app-layout>
