{{-- Version Notes Modal --}}
<flux:modal
    name="{{ $name }}"
    class="md:w-[600px] lg:w-[700px]"
>
    <div class="space-y-0">
        {{-- Header Section --}}
        <div class="mb-6 border-b border-gray-700 pb-6">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <flux:heading
                        size="xl"
                        class="text-gray-100"
                    >
                        {{ __('Version') }} {{ $versionString }}
                    </flux:heading>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        @if ($sptVersionFormatted)
                            <span
                                class="badge-version {{ $sptVersionColorClass }} inline-flex items-center text-nowrap rounded-full px-3 py-1 text-xs font-semibold shadow-sm"
                            >
                                {{ $sptVersionFormatted }}
                            </span>
                        @endif

                        <flux:text class="text-sm text-gray-400">
                            {{ __('Updated') }} {{ $versionUpdatedAt->dynamicFormat() }}
                        </flux:text>

                        @if ($fileSize)
                            <flux:text class="text-sm text-gray-400">
                                {{ $fileSize }}
                            </flux:text>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Not Latest Version Warning --}}
        @unless ($isLatest)
            <div class="mb-4 flex items-start gap-3 rounded-lg border border-amber-800 bg-amber-950/50 p-4">
                <flux:icon
                    name="exclamation-triangle"
                    class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-400"
                />
                <div class="text-sm text-amber-200">
                    <p class="font-semibold">{{ __('This is not the latest version of this mod.') }}</p>
                    <p class="mt-1">
                        {{ __('For the best compatibility, it is always recommended to download the latest version for the SPT version you are currently running.') }}
                    </p>
                </div>
            </div>
        @endunless

        {{-- Content Section --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <flux:icon
                    name="document-text"
                    class="h-5 w-5 text-gray-400"
                />
                <flux:heading
                    size="md"
                    class="text-gray-100"
                >
                    {{ __('Version Notes') }}
                </flux:heading>
            </div>

            <div class="rounded-lg border border-gray-700 bg-gray-900 shadow-sm">
                <div class="prose prose-sm prose-invert max-h-80 max-w-none overflow-y-auto p-6 text-gray-300">
                    {!! $versionDescriptionHtml !!}
                </div>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="mt-6 flex items-center justify-between border-t border-gray-700 pt-6">
            <div class="flex max-w-sm items-center text-xs text-amber-400">
                <flux:icon
                    name="exclamation-triangle"
                    class="mr-2 h-4 w-4 flex-shrink-0"
                />
                <span class="leading-tight">
                    {{ __('This download is externally hosted.') }}<br />
                    {{ __('Always scan for viruses.') }}
                </span>
            </div>

            <div class="flex gap-3">
                @if ($hasDependencies())
                    <flux:button
                        variant="primary"
                        size="sm"
                        x-on:click="$flux.modal('{{ $name }}').close(); $nextTick(() => $flux.modal('{{ $depsModalName() }}').show())"
                        icon="arrow-right"
                    >
                        {{ __('Continue') }}
                    </flux:button>
                @else
                    <flux:button
                        variant="primary"
                        size="sm"
                        x-on:click="$flux.modal('{{ $name }}').close(); window.open('{{ $downloadUrl }}', '_blank')"
                        icon="arrow-down"
                    >
                        {{ __('Download') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>
</flux:modal>

{{-- Dependencies Modal --}}
@if ($hasDependencies())
    <flux:modal
        name="{{ $depsModalName() }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header --}}
            <div class="mb-4 border-b border-gray-700 pb-4">
                <div class="flex items-center gap-2">
                    <flux:icon
                        name="exclamation-triangle"
                        class="h-6 w-6 text-amber-500"
                    />
                    <flux:heading
                        size="xl"
                        class="text-gray-100"
                    >
                        {{ $dependencies->count() === 1 ? __('Required Dependency') : __('Required Dependencies') }}
                    </flux:heading>
                </div>
                <flux:text class="mt-2 text-gray-400">
                    {{ $dependencies->count() === 1
                        ? __('This mod requires the following mod to be installed. Please download and install it before using this mod.')
                        : __(
                            'This mod requires the following mods to be installed. Please download and install them before using this mod.',
                        ) }}
                </flux:text>
            </div>

            {{-- Dependency List --}}
            <ul
                role="list"
                class="divide-y divide-gray-700"
            >
                @foreach ($dependencies as $dependency)
                    @continue($dependency->mod === null)
                    <li class="py-3 first:pt-0 last:pb-0">
                        <a
                            href="{{ route('mod.show', [$dependency->mod->id, $dependency->mod->slug]) }}"
                            wire:navigate
                            class="group flex items-center gap-3"
                            x-on:click="$flux.modal('{{ $depsModalName() }}').close()"
                        >
                            @if ($dependency->mod->thumbnail)
                                <img
                                    src="{{ $dependency->mod->thumbnailUrl }}"
                                    alt="{{ $dependency->mod->name }}"
                                    class="h-12 w-12 flex-shrink-0 rounded-lg object-cover"
                                >
                            @else
                                <div
                                    class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-gray-800">
                                    <flux:icon.cube-transparent class="h-6 w-6 text-gray-600" />
                                </div>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-100 group-hover:text-cyan-400">
                                    {{ $dependency->mod->name }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ __('Requires') }} v{{ $dependency->version }}
                                    @if ($dependency->mod->owner)
                                        &middot;
                                        <x-user-name :user="$dependency->mod->owner" />
                                    @endif
                                </p>
                            </div>

                            <flux:icon.arrow-top-right-on-square
                                class="h-4 w-4 flex-shrink-0 text-gray-400 group-hover:text-cyan-500"
                            />
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Footer --}}
            <div class="mt-4 flex items-center justify-between border-t border-gray-700 pt-4">
                <div class="flex max-w-sm items-center text-xs text-amber-400">
                    <flux:icon
                        name="exclamation-triangle"
                        class="mr-2 h-4 w-4 flex-shrink-0"
                    />
                    <span class="leading-tight">
                        {{ __('This download is externally hosted.') }}<br />
                        {{ __('Always scan for viruses.') }}
                    </span>
                </div>

                <flux:button
                    variant="primary"
                    size="sm"
                    x-on:click="$flux.modal('{{ $depsModalName() }}').close(); window.open('{{ $downloadUrl }}', '_blank')"
                    icon="arrow-down"
                >
                    {{ __('Download') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
@endif
