<div>
    <flux:dropdown position="bottom" align="end" class="absolute top-1.5 right-1.5">
        <flux:button icon="cog-8-tooth" />
        <flux:menu>
            @can('viewActions', [App\Models\Mod::class, $version->mod])
                <flux:menu.group heading="Mod Version Actions">
                    @can('update', $version)
                        <flux:menu.item href="{{ route('mod.version.edit', [$version->mod->id, $version->id]) }}" icon="pencil">Edit Version</flux:menu.item>
                    @endcan
                    @if ($version->disabled)
                        @can('enable', $version)
                            <flux:menu.item href="#" wire:click.prevent="$toggle('confirmVersionEnable')" icon="eye">Enable Version</flux:menu.item>
                        @endcan
                    @else
                        @can('disable', $version)
                            <flux:menu.item href="#" wire:click.prevent="$toggle('confirmVersionDisable')" icon="eye-slash">Disable Version</flux:menu.item>
                        @endcan
                    @endif
                    @can('delete', $version)
                        <flux:menu.item href="#" wire:click.prevent="$toggle('confirmVersionDelete')" icon="trash" variant="danger">Delete Version</flux:menu.item>
                    @endcan
                </flux:menu.group>
            @endcan
            @if (auth()->user()?->isModOrAdmin())
                <flux:menu.group heading="{{ auth()->user()->role->name }} Actions">
                    @can('update', $version)
                        <flux:menu.item href="{{ route('mod.version.edit', [$version->mod->id, $version->id]) }}" icon="pencil">Edit Version</flux:menu.item>
                    @endcan
                    @if ($version->disabled)
                        @can('enable', $version)
                            <flux:menu.item href="#" wire:click.prevent="$toggle('confirmVersionEnable')" icon="eye">Enable Version</flux:menu.item>
                        @endcan
                    @else
                        @can('disable', $version)
                            <flux:menu.item href="#" wire:click.prevent="$toggle('confirmVersionDisable')" icon="eye-slash">Disable Version</flux:menu.item>
                        @endcan
                    @endif
                    @can('delete', $version)
                        <flux:menu.item href="#" wire:click.prevent="$toggle('confirmVersionDelete')" icon="trash" variant="danger">Delete Version</flux:menu.item>
                    @endcan
                </flux:menu.group>
            @endif
        </flux:menu>
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
