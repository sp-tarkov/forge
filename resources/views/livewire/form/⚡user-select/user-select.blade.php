<div>
    <flux:pillbox
        wire:model="selectedUsers"
        variant="combobox"
        multiple
        :filter="false"
        :label="$label"
        :description="$description ?: null"
        :placeholder="$placeholder"
    >
        <x-slot name="input">
            <flux:pillbox.input
                wire:model.live.debounce.300ms="search"
                :placeholder="count($selectedUsers) >= $maxUsers ? __('Maximum authors reached') : $placeholder"
                :disabled="count($selectedUsers) >= $maxUsers"
            />
        </x-slot>

        @foreach ($this->searchResults as $user)
            <flux:pillbox.option :value="$user->id">
                {{ $user->name }}
            </flux:pillbox.option>
        @endforeach

        <x-slot name="empty">
            @if (strlen($search) >= 2)
                <flux:pillbox.option.empty>
                    {{ __('No users found matching ":search"', ['search' => $search]) }}
                </flux:pillbox.option.empty>
            @endif
        </x-slot>
    </flux:pillbox>
</div>
