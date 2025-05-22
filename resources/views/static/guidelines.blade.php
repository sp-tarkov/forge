<x-app-layout>
    <x-slot name="title">
        {{ __('Community Guidelines') }}
    </x-slot>
    <x-slot name="description">
        {{ __('Community Guidelines for SPT') }}
    </x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Community Guidelines') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg">
            <div
                class="px-4 py-8 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">
                <p class="font-semibold text-xl mb-1">Rules:</p>
                <ul class="list-disc list-inside mb-4">
                    <li>Don't break Discord's Terms of Use.</li>
                    <li>Don't ping or DM staff or developers with game support questions.</li>
                    <li>Don't promote other platforms and Discord servers.</li>
                    <li>Don't post, share or request Escape From Tarkov client files.</li>
                    <li>Don't show or discuss Escape From Tarkov cheats or exploits.</li>
                    <li>Don't steal other people's work.</li>
                    <li>Only post support related questions in the Discord Support Channel</li>
                    <li>Don't ask for money for services you provide (i.e. creating / distributing mods, selling in-game
                        roubles) or promote such acts or platforms.
                    </li>
                    <li>Don't discuss politics.</li>
                    <li>Only use English for communicating on the website.</li>
                </ul>

                <p class="font-semibold text-xl mb-1">Things to know:</p>

                <p class="font-semibold">External sources:</p>
                <p>Any site/platform that redistributes our work or content in any shape or form that isn't acquired
                    from:</p>
                <ul class="list-disc list-inside mb-4">
                    <li>*.sp-tarkov.com</li>
                    <li>
                        <a href="#" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white" target="_blank">Official discord</a>
                    </li>
                    <li>One of our affiliated communities</li>
                </ul>
                <p>Is not endorsed or affiliated with/by us.</p>
                <p class="mb-4">
                    Only <a href="https://www.reddit.com/r/sptarkov" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white" target="_blank">https://www.reddit.com/r/sptarkov</a>
                    is an external community which we also manage.
                </p>

                <p class="font-semibold">Affiliated communities:</p>
                <ul class="list-disc list-inside mb-4">
                    <li>
                        <a href="https://sns.oddba.cn/" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white" target="_blank">
                            Chinese SPTarkov community
                        </a>
                        (not managed by us!)
                    </li>
                    <li>
                        <a href="https://discord.gg/c4RuJNs" target="_blank" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                            Anomaly Discord
                        </a>
                    </li>
                </ul>

                <a href="https://hub.sp-tarkov.com/advertising-disclaimer/" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">Find more about unaffiliated links</a>

                <p class="font-semibold mt-4">You also need to take care of the following rules:</p>
                <ul class="list-disc list-inside">
                    <li><a href="https://hub.sp-tarkov.com/file-submission-guidelines/" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">File submission guidelines</a></li>
                    <li><a href="https://hub.sp-tarkov.com/terms-of-service/" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">Terms of service</a></li>
                    <li><a href="https://hub.sp-tarkov.com/forum-and-commenting-guidelines/" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">Forum and commenting guildelines</a></li>
                    <li><a href="https://hub.sp-tarkov.com/reporting-guidelines/" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">Reporting guidelines</a></li>
                    <li><a href="https://discord.com/terms" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white" target="_blank">Discord Terms of Use</a></li>
                    <li><a href="https://discord.com/guidelines" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white" target="_blank">Discord Community Guidelines</a></li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
