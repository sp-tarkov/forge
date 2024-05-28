<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Mod Details') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 sm:rounded-lg">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <div>
                        <img src="{{ $mod->thumbnail }}" alt="{{ $mod->name }}" />
                        <h2>{{ $mod->name }}</h2>
                        <p>{{ $mod->latestSptVersion->sptVersion->version }}</p>
                        <p>{{ $mod->user->name }}</p>
                        <p>{{ $mod->total_downloads }}</p>
                    </div>
                    <div>
                        <a href="{{ $mod->latestSptVersion->link }}">{{ __('Download') }}</a>
                    </div>
                </div>
                <div>
                    <h2>{{ __('Details') }}</h2>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
