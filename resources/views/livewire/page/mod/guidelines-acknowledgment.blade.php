<x-slot:title>
    {!! __('Content Guidelines - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __('Please review and acknowledge the content guidelines before creating a new mod.') !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
        {{ __('Before You Create a Mod') }}
    </h2>
</x-slot>

<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg overflow-hidden">
            <div class="p-6 sm:p-10">
                <div class="text-center mb-8">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-cyan-100 dark:bg-cyan-900 mb-4">
                        <flux:icon.exclamation-circle class="size-8 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Important Guidelines</h1>
                    <p class="text-lg text-gray-600 dark:text-gray-400">Please review these key points before submitting
                        your mod</p>
                </div>

                <div class="space-y-6 mb-8">
                    <!-- AI-Generated Content Warning -->
                    <div class="border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.exclamation-triangle class="size-6 text-red-600 dark:text-red-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-red-800 dark:text-red-300">No AI-Generated Mods
                                </h3>
                                <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                                    <strong>We do not accept mods that have been substantially or entirely written by
                                        AI.</strong> This includes mods where you've taken an existing mod, fed it to AI
                                    to modify, and then uploaded the result. You must fully understand and be able to
                                    explain every part of your code. Using AI for minor code completion is acceptable,
                                    but you remain fully responsible for all code.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Permission Required -->
                    <div class="border-l-4 border-orange-500 bg-orange-50 dark:bg-orange-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.user-group class="size-6 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-orange-800 dark:text-orange-300">Get Permission
                                    First</h3>
                                <p class="mt-1 text-sm text-orange-700 dark:text-orange-400">
                                    You must obtain <strong>explicit permission</strong> from original authors before
                                    uploading or modifying their work. This applies even if you've made changes. Proper
                                    attribution alone does not substitute for permission.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Source Code Required -->
                    <div class="border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="w-6 h-6 text-blue-600 dark:text-blue-400"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5"
                                    />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-300">Source Code Must Be
                                    Public</h3>
                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">
                                    All executable files (.dll, .exe) must include links to publicly accessible source
                                    code. No obfuscated code is permitted. This ensures transparency and security for
                                    all users.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Testing Required -->
                    <div class="border-l-4 border-green-500 bg-green-50 dark:bg-green-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.check class="size-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-green-800 dark:text-green-300">Test Your Mod
                                    Thoroughly</h3>
                                <p class="mt-1 text-sm text-green-700 dark:text-green-400">
                                    Test your mod on a fresh SPT installation with all documented dependencies. All
                                    advertised features must work as described without causing errors or unintended
                                    changes.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Semantic Versioning -->
                    <div class="border-l-4 border-purple-500 bg-purple-50 dark:bg-purple-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.tag class="size-6 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-purple-800 dark:text-purple-300">Use Semantic
                                    Versioning</h3>
                                <p class="mt-1 text-sm text-purple-700 dark:text-purple-400">
                                    All mods must use semantic versioning (MAJOR.MINOR.PATCH) such as 1.2.3. Version
                                    numbers must be consistent across all mod files and match your code declarations.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Prohibited Content -->
                    <div class="border-l-4 border-gray-500 bg-gray-50 dark:bg-gray-800 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.no-symbol class="size-6 text-gray-600 dark:text-gray-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-300">Prohibited Content
                                </h3>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-400">
                                    The following are strictly prohibited: adult/sexual content, cheating tools that
                                    could work in multiplayer, mod compilations/modpacks, payment requirements, and
                                    content that violates copyright.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <div class="bg-gray-50 dark:bg-gray-800 p-5 rounded-lg mb-8">
                        <p class="text-sm text-gray-700 dark:text-gray-300 text-center">
                            For complete details, please review the full
                            <a
                                href="{{ route('static.content-guidelines') }}"
                                target="_blank"
                                class="font-semibold underline text-cyan-600 dark:text-cyan-400 hover:text-cyan-800 dark:hover:text-cyan-200"
                            >Content Guidelines</a>
                            and
                            <a
                                href="{{ route('static.community-standards') }}"
                                target="_blank"
                                class="font-semibold underline text-cyan-600 dark:text-cyan-400 hover:text-cyan-800 dark:hover:text-cyan-200"
                            >Community Standards</a>.
                        </p>
                    </div>

                    <div class="flex flex-col gap-4 max-w-md mx-auto">
                        <flux:button
                            wire:click="agree"
                            variant="primary"
                            class="w-full bg-green-500 dark:bg-green-700 hover:bg-green-400 dark:hover:bg-green-600 text-white"
                        >
                            {{ __('I Understand') }}
                        </flux:button>
                        <flux:button
                            href="{{ route('mods') }}"
                            variant="ghost"
                            class="w-full"
                        >
                            {{ __('Go Back') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
