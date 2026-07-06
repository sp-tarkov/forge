{{-- Version Notes Modal --}}
<flux:modal
    name="{{ $name }}"
    class="md:w-[600px] lg:w-[700px]"
>
    <div class="space-y-0">
        {{-- Header Section --}}
        <div class="border-b border-gray-700 pb-6 mb-6">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <flux:heading
                        size="xl"
                        class="text-gray-100"
                    >
                        {{ __('Version') }} {{ $versionString }}
                    </flux:heading>

                    <div class="flex items-center gap-3 mt-3 flex-wrap">
                        @if ($sptVersionFormatted)
                            <span
                                class="badge-version {{ $sptVersionColorClass }} inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold text-nowrap shadow-sm"
                            >
                                {{ $sptVersionFormatted }}
                            </span>
                        @endif

                        <flux:text class="text-gray-400 text-sm">
                            {{ __('Updated') }} {{ $versionUpdatedAt->dynamicFormat() }}
                        </flux:text>

                        @if ($fileSize)
                            <flux:text class="text-gray-400 text-sm">
                                {{ $fileSize }}
                            </flux:text>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Not Latest Version Warning --}}
        @unless ($isLatest)
            <div class="flex items-start gap-3 p-4 mb-4 rounded-lg bg-amber-950/50 border border-amber-800">
                <flux:icon
                    name="exclamation-triangle"
                    class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5"
                />
                <div class="text-sm text-amber-200">
                    <p class="font-semibold">{{ __('This is not the latest version of this mod.') }}</p>
                    <p class="mt-1">{{ __('For the best compatibility, it is always recommended to download the latest version for the SPT version you are currently running.') }}</p>
                </div>
            </div>
        @endunless

        {{-- Content Section --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2">
                <flux:icon
                    name="document-text"
                    class="w-5 h-5 text-gray-400"
                />
                <flux:heading
                    size="md"
                    class="text-gray-100"
                >
                    {{ __('Version Notes') }}
                </flux:heading>
            </div>

            <div class="bg-gray-900 border border-gray-700 rounded-lg shadow-sm">
                <div
                    class="p-6 prose prose-sm prose-invert max-w-none overflow-y-auto max-h-80 text-gray-300">
                    {!! $versionDescriptionHtml !!}
                </div>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-700">
            <div class="flex items-center text-xs text-amber-400 max-w-sm">
                <flux:icon
                    name="exclamation-triangle"
                    class="w-4 h-4 mr-2 flex-shrink-0"
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
            <div class="border-b border-gray-700 pb-4 mb-4">
                <div class="flex items-center gap-2">
                    <flux:icon
                        name="exclamation-triangle"
                        class="w-6 h-6 text-amber-500"
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
                        : __('This mod requires the following mods to be installed. Please download and install them before using this mod.') }}
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
                            class="flex items-center gap-3 group"
                            x-on:click="$flux.modal('{{ $depsModalName() }}').close()"
                        >
                            @if ($dependency->mod->thumbnail)
                                <img
                                    src="{{ $dependency->mod->thumbnailUrl }}"
                                    alt="{{ $dependency->mod->name }}"
                                    class="w-12 h-12 rounded-lg flex-shrink-0 object-cover"
                                >
                            @else
                                <div class="w-12 h-12 bg-gray-800 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <flux:icon.cube-transparent class="w-6 h-6 text-gray-600" />
                                </div>
                            @endif

                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-100 truncate group-hover:text-cyan-400">
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

                            <flux:icon.arrow-top-right-on-square class="w-4 h-4 text-gray-400 group-hover:text-cyan-500 flex-shrink-0" />
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Footer --}}
            <div class="flex justify-between items-center pt-4 mt-4 border-t border-gray-700">
                <div class="flex items-center text-xs text-amber-400 max-w-sm">
                    <flux:icon
                        name="exclamation-triangle"
                        class="w-4 h-4 mr-2 flex-shrink-0"
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
