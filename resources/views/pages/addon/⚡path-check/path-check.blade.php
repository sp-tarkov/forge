<x-slot:title>
    {!! __('Choose the Right Path - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __('Confirm that an add-on is the right kind of submission before continuing.') !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
        {{ __('Choose the Right Path') }}
    </h2>
</x-slot>

<div>
    <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 shadow-sm sm:rounded-lg overflow-hidden">
            <div class="p-6 sm:p-10">
                <div class="text-center mb-8">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-cyan-100 dark:bg-cyan-900 mb-4">
                        <flux:icon.puzzle-piece class="size-8 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Choose the Right Path</h1>
                    <p class="text-lg text-gray-600 dark:text-gray-400">Let's make sure an add-on is what you need for
                        <strong>{{ $mod->name }}</strong></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <!-- Right place: add-on -->
                    <div class="border-l-4 border-green-500 bg-green-50 dark:bg-green-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center mb-3">
                            <flux:icon.check-circle class="size-6 text-green-600 dark:text-green-400 flex-shrink-0" />
                            <h3 class="ml-3 text-lg font-semibold text-green-800 dark:text-green-300">An add-on fits
                            </h3>
                        </div>
                        <p class="text-sm text-green-700 dark:text-green-400 mb-3">
                            You're extending <strong>{{ $mod->name }}</strong> with content it loads itself, not
                            compiling new code that runs alongside it.
                        </p>
                        <ul class="space-y-2 text-sm text-green-700 dark:text-green-400">
                            <li class="flex items-start">
                                <flux:icon.check
                                    class="size-4 mt-0.5 mr-2 text-green-600 dark:text-green-400 flex-shrink-0" />
                                <span>Config, preset, or JSON/YAML files the parent mod reads</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check
                                    class="size-4 mt-0.5 mr-2 text-green-600 dark:text-green-400 flex-shrink-0" />
                                <span>Texture, icon, UI, or image replacements</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check
                                    class="size-4 mt-0.5 mr-2 text-green-600 dark:text-green-400 flex-shrink-0" />
                                <span>Translation or localization packs</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check
                                    class="size-4 mt-0.5 mr-2 text-green-600 dark:text-green-400 flex-shrink-0" />
                                <span>Sound or music swaps</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check
                                    class="size-4 mt-0.5 mr-2 text-green-600 dark:text-green-400 flex-shrink-0" />
                                <span>Content packs the parent mod loads at runtime (items, loot tables, bot
                                    templates)</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check
                                    class="size-4 mt-0.5 mr-2 text-green-600 dark:text-green-400 flex-shrink-0" />
                                <span>Documentation or companion tooling that doesn't run inside SPT</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Wrong place: its own mod -->
                    <div class="border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20 p-5 rounded-r-lg">
                        <div class="flex items-center mb-3">
                            <flux:icon.x-circle class="size-6 text-red-600 dark:text-red-400 flex-shrink-0" />
                            <h3 class="ml-3 text-lg font-semibold text-red-800 dark:text-red-300">This should be its
                                own mod</h3>
                        </div>
                        <p class="text-sm text-red-700 dark:text-red-400 mb-3">
                            You're shipping your own compiled code or a module that SPT loads directly. Publish it as
                            a mod with a dependency on <strong>{{ $mod->name }}</strong> instead.
                        </p>
                        <ul class="space-y-2 text-sm text-red-700 dark:text-red-400">
                            <li class="flex items-start">
                                <flux:icon.x-mark
                                    class="size-4 mt-0.5 mr-2 text-red-600 dark:text-red-400 flex-shrink-0" />
                                <span>You compile and ship a <code
                                        class="font-mono text-xs bg-red-100 dark:bg-red-900/40 px-1 rounded">.dll</code>
                                </span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.x-mark
                                    class="size-4 mt-0.5 mr-2 text-red-600 dark:text-red-400 flex-shrink-0" />
                                <span>Your work is loaded by BepInEx or the SPT server as its own plugin, not by the
                                    parent mod</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.x-mark
                                    class="size-4 mt-0.5 mr-2 text-red-600 dark:text-red-400 flex-shrink-0" />
                                <span>You have dependencies beyond the parent mod that need to be declared
                                    separately</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 p-5 rounded-lg mb-8">
                    <div class="flex items-start">
                        <flux:icon.information-circle
                            class="size-6 text-cyan-600 dark:text-cyan-400 flex-shrink-0 mt-0.5" />
                        <div class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                            <p class="font-semibold mb-1">The quick test</p>
                            <p>
                                If <strong>{{ $mod->name }}</strong> loads your work as data or assets, it's an
                                <strong>add-on</strong>. If SPT or BepInEx loads your work as its own module alongside
                                <strong>{{ $mod->name }}</strong>, it's <strong>its own mod</strong> with a dependency.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 max-w-md mx-auto">
                    <flux:button
                        wire:click="proceed"
                        variant="primary"
                        class="w-full bg-green-500 dark:bg-green-700 hover:bg-green-400 dark:hover:bg-green-600 text-white"
                    >
                        {{ __('Yes, continue as an add-on') }}
                    </flux:button>
                    <flux:button
                        href="{{ route('mod.create') }}"
                        variant="primary"
                        class="w-full bg-red-500 dark:bg-red-700 hover:bg-red-400 dark:hover:bg-red-600 text-white"
                    >
                        {{ __('No, I need to publish a mod instead') }}
                    </flux:button>
                    <flux:button
                        href="{{ route('mod.show', [$mod->id, $mod->slug]) }}"
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
