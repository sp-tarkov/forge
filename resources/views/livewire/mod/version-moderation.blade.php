<div>
    <flux:dropdown position="bottom" align="end" class="absolute top-1.5 right-1.5">
        <flux:button icon="shield-exclamation" />
        <flux:navmenu>
            @if ($version->disabled)
                <flux:navmenu.item href="#" wire:click.prevent="$toggle('confirmVersionEnable')" icon="eye">Enable Version</flux:navmenu.item>
            @else
                <flux:navmenu.item href="#" wire:click.prevent="$toggle('confirmVersionDisable')" icon="eye-slash">Disable Version</flux:navmenu.item>
            @endif
            <flux:navmenu.item href="#" wire:click.prevent="$toggle('confirmVersionDelete')" icon="trash" variant="danger">Delete Version</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>

    <x-confirmation-modal wire:model.live="confirmVersionEnable">
        <x-slot name="title">
            {{ __('Enable Mod') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to enable version "{{ $version->version }}" of the "{{ $version->mod->name }}" mod?</p>
            <p class="my-2.5">This will allow visitors to view and download the version.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmVersionEnable')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="enable" wire:loading.attr="disabled">
                {{ __('Enable') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmVersionDisable">
        <x-slot name="title">
            {{ __('Disable Mod') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to disable version "{{ $version->version }}" of the "{{ $version->mod->name }}" mod?</p>
            <p class="my-2.5">This will prevent visitors from viewing or downloading the version.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmVersionDisable')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="disable" wire:loading.attr="disabled">
                {{ __('Disable') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmVersionDelete">
        <x-slot name="title">
            {{ __('Delete Mod') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to delete version "{{ $version->version }}" of the "{{ $version->mod->name }}" mod?</p>
            <p class="my-2.5">The mod version will be permanently deleted, along with all data tied directly to the version. If this isn't necessary you should simply disable the version instead.</p>
            <p class="my-2.5">NOTE: You must refresh the page to see the change after deletion.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmVersionDelete')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="$parent.deleteModVersion({{ $version->id }})" wire:loading.attr="disabled">
                {{ __('Delete') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
