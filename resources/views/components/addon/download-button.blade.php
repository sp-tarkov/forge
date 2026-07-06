@props([
    'name',
    'addonId',
    'latestVersionId',
    'downloadUrl',
    'versionString',
    'versionDescriptionHtml',
    'versionUpdatedAt',
    'fileSize' => null,
])

<div class="{{ $name === 'download-show-mobile' ? 'lg:hidden block' : 'hidden lg:block' }}">
    <flux:modal.trigger name="{{ $name }}">
        <button
            class="h-20 w-full rounded-xl bg-cyan-700 text-lg font-extrabold shadow-md shadow-gray-950 drop-shadow-2xl hover:bg-cyan-600"
        >
            <div class="flex flex-col items-center justify-center">
                <div>{{ __('Download Latest Version') }} ({{ $versionString }})</div>
                @if ($fileSize)
                    <div class="text-sm font-normal opacity-75">{{ $fileSize }}</div>
                @endif
            </div>
        </button>
    </flux:modal.trigger>

    {{-- Download Dialog Modal --}}
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
                            {{ __('Latest Version') }} {{ $versionString }}
                        </flux:heading>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
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
                        {{--
                            !DANGER ZONE!

                            This field is cleaned by the backend, so we can trust it. Other fields are not. Only write out
                            fields like this when you're absolutely sure that the data is safe. Which is almost never.
                        --}}
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
                    <flux:button
                        variant="primary"
                        size="sm"
                        x-on:click="$flux.modal('{{ $name }}').close(); window.open('{{ $downloadUrl }}', '_blank')"
                        icon="arrow-down"
                    >
                        {{ __('Download') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
