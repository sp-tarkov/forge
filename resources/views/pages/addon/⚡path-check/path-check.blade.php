<x-slot:title>
    {!! __('Choose the Right Path - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __('Confirm that an add-on is the right kind of submission before continuing.') !!}
</x-slot>

<x-slot:header>
    <h2 class="text-xl font-semibold leading-tight text-gray-200">
        {{ __('Choose the Right Path') }}
    </h2>
</x-slot>

<div>
    <div class="mx-auto max-w-4xl py-10 sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-gray-900 shadow-sm sm:rounded-lg">
            <div class="p-6 sm:p-10">
                <div class="mb-8 text-center">
                    <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-cyan-900">
                        <flux:icon.puzzle-piece class="size-8 text-cyan-400" />
                    </div>
                    <h1 class="mb-2 text-3xl font-bold text-gray-100">Choose the Right Path</h1>
                    <p class="text-lg text-gray-400">Let's make sure an add-on is what you need for
                        <strong>{{ $mod->name }}</strong>
                    </p>
                </div>

                <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Right place: add-on -->
                    <div class="rounded-r-lg border-l-4 border-green-500 bg-green-900/20 p-5">
                        <div class="mb-3 flex items-center">
                            <flux:icon.check-circle class="size-6 flex-shrink-0 text-green-400" />
                            <h3 class="ml-3 text-lg font-semibold text-green-300">An add-on fits
                            </h3>
                        </div>
                        <p class="mb-3 text-sm text-green-400">
                            You're extending <strong>{{ $mod->name }}</strong> with content it loads itself, not
                            compiling new code that runs alongside it.
                        </p>
                        <ul class="space-y-2 text-sm text-green-400">
                            <li class="flex items-start">
                                <flux:icon.check class="mr-2 mt-0.5 size-4 flex-shrink-0 text-green-400" />
                                <span>Config, preset, or JSON/YAML files the parent mod reads</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check class="mr-2 mt-0.5 size-4 flex-shrink-0 text-green-400" />
                                <span>Texture, icon, UI, or image replacements</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check class="mr-2 mt-0.5 size-4 flex-shrink-0 text-green-400" />
                                <span>Translation or localization packs</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check class="mr-2 mt-0.5 size-4 flex-shrink-0 text-green-400" />
                                <span>Sound or music swaps</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check class="mr-2 mt-0.5 size-4 flex-shrink-0 text-green-400" />
                                <span>Content packs the parent mod loads at runtime (items, loot tables, bot
                                    templates)</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.check class="mr-2 mt-0.5 size-4 flex-shrink-0 text-green-400" />
                                <span>Documentation or companion tooling that doesn't run inside SPT</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Wrong place: its own mod -->
                    <div class="rounded-r-lg border-l-4 border-red-500 bg-red-900/20 p-5">
                        <div class="mb-3 flex items-center">
                            <flux:icon.x-circle class="size-6 flex-shrink-0 text-red-400" />
                            <h3 class="ml-3 text-lg font-semibold text-red-300">This should be its
                                own mod</h3>
                        </div>
                        <p class="mb-3 text-sm text-red-400">
                            You're shipping your own compiled code or a module that SPT loads directly. Publish it as
                            a mod with a dependency on <strong>{{ $mod->name }}</strong> instead.
                        </p>
                        <ul class="space-y-2 text-sm text-red-400">
                            <li class="flex items-start">
                                <flux:icon.x-mark class="mr-2 mt-0.5 size-4 flex-shrink-0 text-red-400" />
                                <span>You compile and ship a <code
                                        class="rounded bg-red-900/40 px-1 font-mono text-xs">.dll</code>
                                </span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.x-mark class="mr-2 mt-0.5 size-4 flex-shrink-0 text-red-400" />
                                <span>Your work is loaded by BepInEx or the SPT server as its own plugin, not by the
                                    parent mod</span>
                            </li>
                            <li class="flex items-start">
                                <flux:icon.x-mark class="mr-2 mt-0.5 size-4 flex-shrink-0 text-red-400" />
                                <span>You have dependencies beyond the parent mod that need to be declared
                                    separately</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mb-8 rounded-lg bg-gray-800 p-5">
                    <div class="flex items-start">
                        <flux:icon.information-circle class="mt-0.5 size-6 flex-shrink-0 text-cyan-400" />
                        <div class="ml-3 text-sm text-gray-300">
                            <p class="mb-1 font-semibold">The quick test</p>
                            <p>
                                If <strong>{{ $mod->name }}</strong> loads your work as data or assets, it's an
                                <strong>add-on</strong>. If SPT or BepInEx loads your work as its own module alongside
                                <strong>{{ $mod->name }}</strong>, it's <strong>its own mod</strong> with a
                                dependency.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mx-auto flex max-w-md flex-col gap-4">
                    <flux:button
                        wire:click="proceed"
                        variant="primary"
                        class="w-full bg-green-700 text-white hover:bg-green-600"
                    >
                        {{ __('Yes, continue as an add-on') }}
                    </flux:button>
                    <flux:button
                        href="{{ route('mod.create') }}"
                        variant="primary"
                        class="w-full bg-red-700 text-white hover:bg-red-600"
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
