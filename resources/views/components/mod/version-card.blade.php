@props([
    'version',
])

<div {{ $attributes->merge(['class' => 'relative p-4 mb-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl filter-none']) }}>

    <livewire:ribbon
        wire:key="mod-version-show-ribbon-{{ $version->id }}"
        :id="$version->id"
        :disabled="$version->disabled"
    />

    <div class="pb-6 border-b-2 border-gray-200 dark:border-gray-800">
        @if (auth()->user()?->isModOrAdmin())
            <livewire:mod.version-moderation wire:key="mod-version-show-moderation-{{ $version->id }}" :version="$version" />
        @endif

        <div class="flex flex-col items-start sm:flex-row sm:justify-between">
            <div class="flex flex-col">
                <a href="{{ $version->downloadUrl() }}" class="self-center text-3xl font-extrabold text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                    {{ __('Version') }} {{ $version->version }}
                    <flux:tooltip content="Download Mod Version" position="right">
                        <flux:icon icon="arrow-down-on-square-stack" class="inline-block size-6 ml-2 relative -top-1" />
                    </flux:tooltip>
                </a>
                <div class="mt-3 flex flex-row justify-start">
                    <flux:tooltip content="Latest Compatible SPT Version" position="right">
                        <p>
                            @if ($version->latestSptVersion)
                                <span class="badge-version {{ $version->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                                    {{ $version->latestSptVersion->version_formatted }}
                                </span>
                            @else
                                <span class="badge-version bg-gray-100 text-gray-700 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                                    {{ __('Unknown SPT Version') }}
                                </span>
                            @endif
                        </p>
                    </flux:tooltip>
                    <p class="ml-2.5 self-center text-sm text-gray-800 dark:text-gray-300" title="{{ __('Exactly') }} {{ $version->downloads }}">
                        {{ Number::downloads($version->downloads) }} {{ __('Downloads') }}
                    </p>
                </div>
            </div>
            <div class="flex flex-col items-start text-gray-700 dark:text-gray-400 sm:items-end mt-4 sm:mt-0">
                <p class="text-left sm:text-right">{{ __('Created') }} {{ Carbon::dynamicFormat($version->created_at) }}</p>
                <p class="text-left sm:text-right">{{ __('Updated') }} {{ Carbon::dynamicFormat($version->updated_at) }}</p>
                <a href="{{ $version->virus_total_link }}" class="text-left sm:text-right underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                    {{__('Virus Total Results')}}
                </a>
            </div>
        </div>

        {{-- Display latest resolved dependencies --}}
        @if ($version->latestResolvedDependencies->isNotEmpty())
            <p class="mt-3 text-gray-700 dark:text-gray-400">
                {{ __('Dependencies:') }}
            </p>
            <ul>
                @foreach ($version->latestResolvedDependencies as $resolvedDependency)
                    <li>
                        <a href="{{ $resolvedDependency->mod->detailUrl() }}" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">
                            {{ $resolvedDependency->mod->name }}&nbsp;({{ $resolvedDependency->version }})
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    <div class="py-3 user-markdown text-gray-700 dark:text-gray-400">
        {{--
        The description below is safe to write directly because it has been run though HTMLPurifier during the import.
        TODO: Push the parsed markdown HTML through HTMLPurifier again on display.
        --}}
        {!! Str::markdown($version->description) !!}
    </div>
</div>
