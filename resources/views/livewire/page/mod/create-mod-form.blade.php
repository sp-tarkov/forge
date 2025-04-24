<div>
    <form wire:submit="save" class="space-y-4 m-6">
        <flux:input type="text" wire:model="modName" placeholder="{{ __('Mod Name') }} ..."/>

        <flux:input type="text" wire:model="modDescription" placeholder="{{ __('Mod Description') }} ..."/>

        <flux:input type="date" wire:model="publishDate"/>

        <flux:button type="submit">{{ __('Create Mod') }}</flux:button>
    </form>
</div>
