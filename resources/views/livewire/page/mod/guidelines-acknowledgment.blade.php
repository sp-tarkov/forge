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
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-cyan-100 dark:bg-cyan-900 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-cyan-600 dark:text-cyan-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Important Guidelines</h1>
                    <p class="text-lg text-gray-600 dark:text-gray-400">Please review these key points before submitting your mod</p>
                </div>

                <div class="space-y-6 mb-8">
                    <!-- AI-Generated Content Warning -->
                    <div class="border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600 dark:text-red-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-red-800 dark:text-red-300">No AI-Generated Mods</h3>
                                <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                                    <strong>We do not accept mods that have been substantially or entirely written by AI.</strong> This includes mods where you've taken an existing mod, fed it to AI to modify, and then uploaded the result. You must fully understand and be able to explain every part of your code. Using AI for minor code completion is acceptable, but you remain fully responsible for all code.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Permission Required -->
                    <div class="border-l-4 border-orange-500 bg-orange-50 dark:bg-orange-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-orange-600 dark:text-orange-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-orange-800 dark:text-orange-300">Get Permission First</h3>
                                <p class="mt-1 text-sm text-orange-700 dark:text-orange-400">
                                    You must obtain <strong>explicit permission</strong> from original authors before uploading or modifying their work. This applies even if you've made changes. Proper attribution alone does not substitute for permission.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Source Code Required -->
                    <div class="border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-blue-600 dark:text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-300">Source Code Must Be Public</h3>
                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-400">
                                    All executable files (.dll, .exe) must include links to publicly accessible source code. No obfuscated code is permitted. This ensures transparency and security for all users.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Testing Required -->
                    <div class="border-l-4 border-green-500 bg-green-50 dark:bg-green-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-green-600 dark:text-green-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-green-800 dark:text-green-300">Test Your Mod Thoroughly</h3>
                                <p class="mt-1 text-sm text-green-700 dark:text-green-400">
                                    Test your mod on a fresh SPT installation with all documented dependencies. All advertised features must work as described without causing errors or unintended changes.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Semantic Versioning -->
                    <div class="border-l-4 border-purple-500 bg-purple-50 dark:bg-purple-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-purple-600 dark:text-purple-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-purple-800 dark:text-purple-300">Use Semantic Versioning</h3>
                                <p class="mt-1 text-sm text-purple-700 dark:text-purple-400">
                                    All mods must use semantic versioning (MAJOR.MINOR.PATCH) such as 1.2.3. Version numbers must be consistent across all mod files and match your code declarations.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Prohibited Content -->
                    <div class="border-l-4 border-gray-500 bg-gray-50 dark:bg-gray-800 p-5 rounded-r-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 dark:text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-300">Prohibited Content</h3>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-400">
                                    The following are strictly prohibited: adult/sexual content, cheating tools that could work in multiplayer, mod compilations/modpacks, payment requirements, and content that violates copyright.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <div class="bg-gray-50 dark:bg-gray-800 p-5 rounded-lg mb-8">
                        <p class="text-sm text-gray-700 dark:text-gray-300 text-center">
                            For complete details, please review the full
                            <a href="{{ route('static.content-guidelines') }}" target="_blank" class="font-semibold underline text-cyan-600 dark:text-cyan-400 hover:text-cyan-800 dark:hover:text-cyan-200">Content Guidelines</a>
                            and
                            <a href="{{ route('static.community-standards') }}" target="_blank" class="font-semibold underline text-cyan-600 dark:text-cyan-400 hover:text-cyan-800 dark:hover:text-cyan-200">Community Standards</a>.
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
