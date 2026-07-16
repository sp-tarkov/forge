<div
    @if ($this->isActive) wire:poll.10s @endif
    class="inline-flex"
>
    @if ($this->displayStatus || ($this->canManage && $this->isEligible))
        <flux:modal.trigger name="{{ $modalName }}">
            <button
                type="button"
                data-test="verification-status-shield"
                class="cursor-pointer"
            >
                <flux:tooltip
                    content="{{ $this->tooltip }}"
                    position="right"
                >
                    <flux:icon
                        :icon="$this->shieldIcon"
                        :variant="$this->shieldVariant"
                        class="{{ $this->shieldClasses }} size-6"
                    />
                </flux:tooltip>
            </button>
        </flux:modal.trigger>
    @endif
</div>
