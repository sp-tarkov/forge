<x-app-layout>
    <x-slot name="title">
        {{ __('Releases') }}
    </x-slot>
    <x-slot name="description">
        {{ __('Releases page for SPT') }}
    </x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                {{ __('Releases') }}
            </h2>
            <a href="https://github.com/sp-tarkov/build/releases" target="_blank">
                <button
                    class="text-lg font-extrabold hover:bg-cyan-400 dark:hover:bg-cyan-600 shadow-md dark:shadow-gray-950 drop-shadow-2xl bg-cyan-500 dark:bg-cyan-700 rounded-sm w-full h-10 px-4">
                    Latest Release
                </button>
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg">
            <div
                class="px-4 py-8 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">

                <p class="pb-4">Pre-built SPT Releases</p>

                <h2 class="font-semibold text-xl">Requirements:</h2>
                <ul class="list-disc list-inside mt-2">
                    <li>Escape From Tarkov.</li>
                    <li>
                        <a href="https://dotnet.microsoft.com/download/dotnet-framework/thank-you/net472-developer-pack-offline-installer"
                           target="_blank"
                           class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">.NET
                            Framework 4.7.2</a>.
                    </li>
                    <li>
                        <a href="https://dotnet.microsoft.com/en-us/download/dotnet/thank-you/runtime-desktop-8.0.3-windows-x64-installer"
                           target="_blank"
                           class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">.NET
                            Runtime 8</a>.
                    </li>
                </ul>

                <hr class="my-4">

                <h2 class="font-semibold text-xl">Installation:</h2>
                <p>Use the <a href="/installer"
                              class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">automated
                        installer</a>. Manual install is no longer officially supported</p>

                <hr class="my-4">

                <h2 class="font-semibold text-xl">Run:</h2>
                <ul class="list-decimal list-inside my-2">
                    <li>Start Server.exe.</li>
                    <li>Start Launcher.exe.</li>
                    <li>Launcher > Make a new account.</li>
                    <li>Launcher > Start game.</li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
