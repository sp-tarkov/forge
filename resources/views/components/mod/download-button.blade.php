@props([
    'name',
    'modId',
    'latestVersionId',
    'downloadUrl',
    'versionString',
    'sptVersionFormatted' => null,
    'sptVersionColorClass' => null,
    'versionDescriptionHtml',
    'versionUpdatedAt',
    'fileSize' => null,
])

<div class="{{ $name === 'download-show-mobile' ? 'lg:hidden block' : 'hidden lg:block' }}">
    <flux:modal.trigger name="{{ $name }}">
        <button class="text-lg font-extrabold hover:bg-cyan-400 dark:hover:bg-cyan-600 shadow-md dark:shadow-gray-950 drop-shadow-2xl bg-cyan-500 dark:bg-cyan-700 rounded-xl w-full h-20">
            <div class="flex flex-col justify-center items-center">
                <div>{{ __('Download Latest Version') }} ({{ $versionString }})</div>
                @if ($fileSize)
                    <div class="text-sm font-normal opacity-75">{{ $fileSize }}</div>
                @endif
            </div>
        </button>
    </flux:modal.trigger>

    {{-- Download Dialog Modal --}}
    <flux:modal name="{{ $name }}" class="md:w-[600px] lg:w-[700px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Latest Version') }} {{ $versionString }}
                        </flux:heading>

                        <div class="flex items-center gap-3 mt-3 flex-wrap">
                            @if ($sptVersionFormatted)
                                <span class="badge-version {{ $sptVersionColorClass }} inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold text-nowrap shadow-sm">
                                    {{ $sptVersionFormatted }}
                                </span>
                            @endif

                            <flux:text class="text-gray-600 dark:text-gray-400 text-sm">
                                {{ __('Updated') }} {{ $versionUpdatedAt->dynamicFormat() }}
                            </flux:text>

                            @if ($fileSize)
                                <flux:text class="text-gray-600 dark:text-gray-400 text-sm">
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
                    <flux:icon name="document-text" class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                    <flux:heading size="md" class="text-gray-900 dark:text-gray-100">
                        {{ __('Version Notes') }}
                    </flux:heading>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                    <div class="p-6 prose prose-sm dark:prose-invert max-w-none overflow-y-auto max-h-80 text-gray-700 dark:text-gray-300">
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
            <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center text-xs text-amber-600 dark:text-amber-400 max-w-sm">
                    <flux:icon name="exclamation-triangle" class="w-4 h-4 mr-2 flex-shrink-0" />
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
                        icon="arrow-down">
                        {{ __('Download') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
