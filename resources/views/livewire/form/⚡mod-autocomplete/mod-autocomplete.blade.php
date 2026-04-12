<div>
    <flux:select
        wire:model.live="selectedModId"
        variant="combobox"
        :filter="false"
        :placeholder="$placeholder"
        :label="$label"
        clearable
    >
        <x-slot name="input">
            <flux:select.input wire:model.live.debounce.300ms="search" />
        </x-slot>

        @foreach ($this->filteredMods as $mod)
            <flux:select.option :value="$mod->id">{{ $mod->name }}</flux:select.option>
        @endforeach

        <x-slot name="empty">
            @if (strlen($search) > 0)
                <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('No mods found matching ":search"', ['search' => $search]) }}
                </div>
            @endif
        </x-slot>
    </flux:select>
</div>
