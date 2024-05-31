<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Mod Details') }}
        </h2>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 max-w-7xl mx-auto pb-6 px-4 gap-6 sm:px-6 lg:px-8">

        <div class="lg:col-span-2 p-4 sm:p-6 text-center sm:text-left bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="flex flex-col sm:flex-row gap-4 sm:gap-6">
                <div class="grow-0 flex justify-center items-center">
                    @if(empty($mod->thumbnail))
                        <img src="https://placehold.co/144x144/EEE/31343C?font=source-sans-pro&text={{ $mod->name }}" alt="{{ $mod->name }}" class="block dark:hidden w-36 rounded-lg">
                        <img src="https://placehold.co/144x144/31343C/EEE?font=source-sans-pro&text={{ $mod->name }}" alt="{{ $mod->name }}" class="hidden dark:block w-36 rounded-lg">
                    @else
                        <img src="{{ $mod->thumbnail }}" alt="{{ $mod->name }}" class="w-36 rounded-lg">
                    @endif
                </div>
                <div class="grow flex flex-col justify-center items-center sm:items-start text-gray-800 dark:text-gray-200">
                    <h2 class="pb-1 sm:p-0 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $mod->name }}
                        <span class="font-light text-nowrap text-gray-700 dark:text-gray-400">
                            {{ $mod->latestSptVersion->version }}
                        </span>
                    </h2>
                    <p>{{ __('Created by') }} {{ $mod->user->name }}</p>
                    <p>{{ $mod->latestSptVersion->sptVersion->version }} {{ __('Compatible') }}</p>
                    <p>{{ $mod->total_downloads }} {{ __('Downloads') }}</p>
                </div>
            </div>
        </div>

        <div class="col-span-1 flex flex-col gap-6">
            <a href="{{ $mod->latestSptVersion->link }}" class="block">
                <button type="button" class="w-full">{{ __('Download Latest Version') }}</button>
            </a>

            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('Details') }}</h2>
                <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-900 dark:text-gray-100">
                    @if($mod->license)
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('License') }}</h3>
                            <p class="truncate">
                                <a href="{{ $mod->license->link }}" title="{{ $mod->license->name }}" target="_blank">
                                    {{ $mod->license->name }}
                                </a>
                            </p>
                        </li>
                    @endif
                    @if($mod->source_code_link)
                        <li class="px-4 py-4 sm:px-0">
                            <h3>{{ __('Source Code') }}</h3>
                            <p class="truncate">
                                <a href="{{ $mod->source_code_link }}" title="{{ $mod->source_code_link }}" target="_blank">
                                    {{ $mod->source_code_link }}
                                </a>
                            </p>
                        </li>
                    @endif
                    <li class="px-4 py-4 sm:px-0">
                        <h3>{{ __('Latest VirusTotal Result') }}</h3>
                        <p class="truncate">
                            <a href="{{ $mod->latestSptVersion->virus_total_link }}" title="{{ $mod->latestSptVersion->virus_total_link }}" target="_blank">
                                {{ $mod->latestSptVersion->virus_total_link }}
                            </a>
                        </p>
                    </li>
                    <li class="px-4 py-4 sm:px-0">
                        <h3>{{ __('Includes advertising?') }}</h3>
                    </li>
                    <li class="px-4 py-4 sm:px-0">
                        <h3>{{ __('Includes AI generated content?') }}</h3>
                    </li>
                </ul>
            </div>
        </div>
    </div>

</x-app-layout>
