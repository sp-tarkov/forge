<div>
    <flux:pillbox
        wire:model.live="selectedUsers"
        variant="combobox"
        multiple
        :filter="false"
        :label="$label"
        :description="$description ?: null"
        :placeholder="$placeholder"
    >
        <x-slot name="input">
            <flux:pillbox.input
                wire:model.live="search"
                :placeholder="count($selectedUsers) >= $maxUsers ? __('Maximum authors reached') : $placeholder"
                :disabled="count($selectedUsers) >= $maxUsers"
                class="bg-transparent border-0 p-0 shadow-none focus:ring-0"
            />
        </x-slot>

        @foreach ($this->searchResults as $user)
            <flux:pillbox.option :value="$user->id">
                {{ $user->name }}
            </flux:pillbox.option>
        @endforeach

        <x-slot name="empty">
            <flux:pillbox.option.empty when-loading="{{ __('Searching...') }}">
                {{ __('No users found.') }}
            </flux:pillbox.option.empty>
        </x-slot>
    </flux:pillbox>
</div>
