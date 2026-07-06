<div>
    <flux:select
        wire:model.live="selectedModId"
        variant="combobox"
        :filter="false"
        :placeholder="$placeholder"
        :label="$label ? : null"
        clearable
    >
        <x-slot name="input">
            <flux:select.input wire:model.live="search" />
        </x-slot>

        @foreach ($this->filteredMods as $mod)
            <flux:select.option :value="$mod->id">{{ $mod->name }}</flux:select.option>
        @endforeach

        <x-slot name="empty">
            <flux:select.option.empty when-loading="{{ __('Searching...') }}">
                {{ __('No mods found.') }}
            </flux:select.option.empty>
        </x-slot>
    </flux:select>
</div>
