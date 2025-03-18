<a class="{{ $classes }}">
    <button wire:click="toggleDownloadDialog" class="text-lg font-extrabold hover:bg-cyan-400 dark:hover:bg-cyan-600 shadow-md dark:shadow-gray-950 drop-shadow-2xl bg-cyan-500 dark:bg-cyan-700 rounded-xl w-full h-20">
        {{ __('Download Latest Version') }} ({{ $this->mod->latestVersion->version }})
    </button>
</a>

@push('modals')
    <x-dialog-modal wire:model="showDownloadDialog">
        <x-slot name="title">
            <h2 class="text-2xl">Latest Version: {{ $this->mod->latestVersion->version }}</h2>
            @if ($this->mod->latestVersion->latestSptVersion)
                <span class="badge-version {{ $this->mod->latestVersion->latestSptVersion->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                    {{ $this->mod->latestVersion->latestSptVersion->version_formatted }}
                </span>
            @endif
            <span>{{ __('Updated') }} {{ Carbon::dynamicFormat($this->mod->latestVersion->updated_at) }}</span>
        </x-slot>
        <x-slot name="content">
            <div class="overflow-y-auto">
                <span>Version Notes:</span>
                <p>{!! Str::markdown($this->mod->latestVersion->description) !!}</p>
            </div>
        </x-slot>
        <x-slot name="footer">
            <a href="{{ $mod->downloadUrl() }}" class="inline-flex items-center px-4 py-2 mx-4 border border-transparent rounded-md font-semibold text-xs uppercase transition ease-in-out duration-150 cursor-pointer text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700 outline-none disabled:opacity-50" target="_blank">
                Download
            </a>
            <x-button x-on:click="show = false">
                {{ __('Close') }}
            </x-button>
        </x-slot>
    </x-dialog-modal>
@endpush
