<div>
    <flux:dropdown position="bottom" align="end" class="absolute top-1.5 right-1.5">
        <flux:button icon="shield-exclamation" />
        <flux:navmenu>
            @if ($mod->featured)
                <flux:navmenu.item href="#" wire:click.prevent="$toggle('confirmModUnfeature')" icon="arrow-trending-down">Unfeature</flux:navmenu.item>
            @else
                <flux:navmenu.item href="#" wire:click.prevent="$toggle('confirmModFeature')" icon="sparkles">Feature</flux:navmenu.item>
            @endif
            @if ($mod->disabled)
                <flux:navmenu.item href="#" wire:click.prevent="$toggle('confirmModEnable')" icon="eye">Enable</flux:navmenu.item>
            @else
                <flux:navmenu.item href="#" wire:click.prevent="$toggle('confirmModDisable')" icon="eye-slash">Disable</flux:navmenu.item>
            @endif
            <flux:navmenu.item href="#" wire:click.prevent="$toggle('confirmModDelete')" icon="trash" variant="danger">Delete</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>

    <x-confirmation-modal wire:model.live="confirmModUnfeature">
        <x-slot name="title">
            {{ __('Unfeature Mod') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to unfeature the "{{ $mod->name }}" mod?</p>
            <p class="my-2.5">This will remove the mod from the homepage featured section and remove the "featured" ribbon on it's listing card.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmModUnfeature')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="unfeature" wire:loading.attr="disabled">
                {{ __('Unfeature') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmModFeature">
        <x-slot name="title">
            {{ __('Feature Mod') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to feature the "{{ $mod->name }}" mod?</p>
            <p class="my-2.5">This will add the mod to the homepage featured section and add add the "featured" ribbon on it's listing card.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmModFeature')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="feature" wire:loading.attr="disabled">
                {{ __('Feature') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmModDisable">
        <x-slot name="title">
            {{ __('Disable Mod') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to disable the "{{ $mod->name }}" mod?</p>
            <p class="my-2.5">This will prevent visitors on the site from seeing the mod.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmModDisable')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="disable" wire:loading.attr="disabled">
                {{ __('Disable') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmModEnable">
        <x-slot name="title">
            {{ __('Enable Mod') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to enable the "{{ $mod->name }}" mod?</p>
            <p class="my-2.5">This will allow visitors on the site to see the mod.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmModEnable')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="enable" wire:loading.attr="disabled">
                {{ __('Enable') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmModDelete">
        <x-slot name="title">
            {{ __('Delete Mod') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to delete the "{{ $mod->name }}" mod?</p>
            <p class="my-2.5">The mod will be permanently deleted, along with all mod versions, mod comments, and any other data tied directly to the mod.</p>
            <p class="my-2.5">If this isn't necessary you should simply disable the mod instead.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('confirmModDelete')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="delete" wire:loading.attr="disabled">
                {{ __('Delete') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
