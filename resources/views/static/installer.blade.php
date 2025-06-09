<x-layouts.base>
    <x-slot name="title">
        {{ __('Installer') }}
    </x-slot>
    <x-slot name="description">
        {{ __('The dashboard for your account on The Forge.') }}
    </x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                {{ __('Installer') }}
            </h2>
            <a href="https://ligma.waffle-lord.net/SPTInstaller.exe" target="_blank">
                <button
                    class="text-lg font-extrabold hover:bg-cyan-400 dark:hover:bg-cyan-600 shadow-md dark:shadow-gray-950 drop-shadow-2xl bg-cyan-500 dark:bg-cyan-700 rounded-sm w-full h-10 px-4">
                    Download Installer
                </button>
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg">
            <div
                class="px-4 py-8 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">

                <p class="pb-4">Simple installer for SPT, fully automated</p>

                <h2 class="font-semibold text-xl">Warning:</h2>
                <ul class="list-disc list-inside mt-2">
                    <li>Make sure live EFT is updated before running the installer.</li>
                    <li>Do not place the install on your desktop and run it.</li>
                </ul>

                <hr class="my-4">

                <h2 class="font-semibold text-xl">The Installer Will:</h2>
                <ul class="list-disc list-inside mt-2">
                    <li>Check you have .Net installed, as you will need this for the launcher.</li>
                    <li>Copy EFT for you into the selected folder.</li>
                    <li>Patch the copied EFT files to the needed version for SPT.</li>
                    <li>Download most recently released SPT from the SPT website and install it into the same folder.
                    </li>
                </ul>

                <hr class="my-4">

                <h2 class="font-semibold text-xl">Usage Guide:</h2>
                <ul class="list-decimal list-inside my-2">
                    <li>Download installer.</li>
                    <li>Place in new folder e.g. `SPT`.</li>
                    <li>Ensure live EFT is updated to latest.</li>
                    <li>Run installer.
                        <ul class="list-disc list-inside">
                            <li class="ml-4">If something shows as RED, you should pay attention to it.</li>
                        </ul>
                    </li>
                    <li>Wait for it to say it has completed.</li>
                    <li>Close the installer box, you are ready to play.</li>
                    <li>From this point onwards, any issues please use the FAQ and the support channel in discord.</li>
                </ul>
                <p>
                    <a href="#" target="_blank" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">Video Guide</a> made by McDewgle</p>

                <hr class="my-4">

                <h2 class="font-semibold text-xl">How to run SPT:</h2>
                <ul class="list-decimal list-inside mt-2 mb-6">
                    <li>Start the Spt.Server.exe wait for it to say Happy Playing!</li>
                    <li>Start the Spt.Launcher.exe.</li>
                    <li>Create a profile.</li>
                    <li>Launch the game.</li>
                </ul>

                <p>Before you download and use this, understand that it will download SPT and the relevant Patcher required for your client version from our GIT Repo. (the same place as if you download them separately)</p>

                <hr class="my-4">

                <p>Example of the installer:</p>
                <img src="https://i.imgur.com/pdYoolR.png" alt="Image of the installer" class="my-4">
                <p>Cached files are located in %appdata%/spt-installer/cache</p>
                <p>You can locate this folder by typing %appdata% into windows startbar, find spt-installer/cache and
                    inside there are the zips</p>
            </div>
        </div>
    </div>
</x-layouts.base>
