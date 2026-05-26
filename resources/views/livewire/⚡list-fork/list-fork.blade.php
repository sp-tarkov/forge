<div>
    @if ($canFork)
        <flux:button
            icon="share"
            variant="outline"
            size="sm"
            x-on:click="$wire.showForkModal = true"
        >
            {{ $isOwnList ? __('Duplicate') : __('Fork') }}
        </flux:button>

        <flux:modal
            name="list-fork-{{ $sourceId }}"
            wire:model.self="showForkModal"
            class="md:w-[500px]"
        >
            <form
                wire:submit="submit"
                class="space-y-4"
            >
                <div>
                    <flux:heading size="lg">
                        {{ $isOwnList ? __('Duplicate this list') : __('Fork this list') }}
                    </flux:heading>
                    <flux:subheading>
                        {{ $isOwnList
                            ? __('Create a copy of this list in your own account. The new list starts as Private.')
                            : __('Create your own copy of this list. The new list starts as Private and credits the original author.') }}
                    </flux:subheading>
                </div>

                <flux:input
                    wire:model="title"
                    :label="__('Title')"
                    :placeholder="__('Give your new list a name')"
                    autofocus
                    required
                />

                <div class="flex justify-end gap-3 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button
                        type="submit"
                        variant="primary"
                        icon="share"
                    >
                        {{ $isOwnList ? __('Duplicate list') : __('Fork list') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
