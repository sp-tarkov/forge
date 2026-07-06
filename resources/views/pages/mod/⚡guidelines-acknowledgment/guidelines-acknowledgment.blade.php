<x-slot:title>
    {!! __('Content Guidelines - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __('Please review and acknowledge the content guidelines before creating a new mod.') !!}
</x-slot>

<x-slot:header>
    <h2 class="text-xl font-semibold leading-tight text-gray-200">
        {{ __('Before You Create a Mod') }}
    </h2>
</x-slot>

<div>
    <div class="mx-auto max-w-4xl py-10 sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-gray-900 shadow-sm sm:rounded-lg">
            <div class="p-6 sm:p-10">
                <div class="mb-8 text-center">
                    <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-cyan-900">
                        <flux:icon.exclamation-circle class="size-8 text-cyan-400" />
                    </div>
                    <h1 class="mb-2 text-3xl font-bold text-gray-100">Important Guidelines</h1>
                    <p class="text-lg text-gray-400">Please review these key points before submitting
                        your mod</p>
                </div>

                <div class="mb-8 space-y-6">
                    <!-- AI-Generated Content Warning -->
                    <div class="rounded-r-lg border-l-4 border-red-500 bg-red-900/20 p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.exclamation-triangle class="size-6 text-red-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-red-300">No AI-Generated Mods
                                </h3>
                                <p class="mt-1 text-sm text-red-400">
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
                    <div class="rounded-r-lg border-l-4 border-orange-500 bg-orange-900/20 p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.user-group class="size-6 text-orange-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-orange-300">Get Permission
                                    First</h3>
                                <p class="mt-1 text-sm text-orange-400">
                                    You must obtain <strong>explicit permission</strong> from original authors before
                                    uploading or modifying their work. This applies even if you've made changes. Proper
                                    attribution alone does not substitute for permission.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Source Code Required -->
                    <div class="rounded-r-lg border-l-4 border-blue-500 bg-blue-900/20 p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="h-6 w-6 text-blue-400"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5"
                                    />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-blue-300">Source Code Must Be
                                    Public</h3>
                                <p class="mt-1 text-sm text-blue-400">
                                    All executable files (.dll, .exe) must include links to publicly accessible source
                                    code. No obfuscated code is permitted. This ensures transparency and security for
                                    all users.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Testing Required -->
                    <div class="rounded-r-lg border-l-4 border-green-500 bg-green-900/20 p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.check class="size-6 text-green-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-green-300">Test Your Mod
                                    Thoroughly</h3>
                                <p class="mt-1 text-sm text-green-400">
                                    Test your mod on a fresh SPT installation with all documented dependencies. All
                                    advertised features must work as described without causing errors or unintended
                                    changes.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Semantic Versioning -->
                    <div class="rounded-r-lg border-l-4 border-purple-500 bg-purple-900/20 p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.tag class="size-6 text-purple-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-purple-300">Use Semantic
                                    Versioning</h3>
                                <p class="mt-1 text-sm text-purple-400">
                                    All mods must use semantic versioning (MAJOR.MINOR.PATCH) such as 1.2.3. Version
                                    numbers must be consistent across all mod files and match your code declarations.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Prohibited Content -->
                    <div class="rounded-r-lg border-l-4 border-gray-500 bg-gray-800 p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <flux:icon.no-symbol class="size-6 text-gray-400" />
                            </div>
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-gray-300">Prohibited Content
                                </h3>
                                <p class="mt-1 text-sm text-gray-400">
                                    The following are strictly prohibited: adult/sexual content, cheating tools that
                                    could work in multiplayer, mod compilations/modpacks, payment requirements, and
                                    content that violates copyright.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <div class="mb-8 rounded-lg bg-gray-800 p-5">
                        <p class="text-center text-sm text-gray-300">
                            For complete details, please review the full
                            <a
                                href="{{ route('static.content-guidelines') }}"
                                target="_blank"
                                class="font-semibold text-cyan-400 underline hover:text-cyan-200"
                            >Content Guidelines</a>
                            and
                            <a
                                href="{{ route('static.community-standards') }}"
                                target="_blank"
                                class="font-semibold text-cyan-400 underline hover:text-cyan-200"
                            >Community Standards</a>.
                        </p>
                    </div>

                    <div class="mx-auto flex max-w-md flex-col gap-4">
                        <flux:button
                            wire:click="agree"
                            variant="primary"
                            class="w-full bg-green-700 text-white hover:bg-green-600"
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
