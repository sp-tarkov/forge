<div>
    <flux:dropdown position="bottom" align="end" class="absolute top-1.5 right-1.5">
        <flux:button icon="cog-8-tooth" />
        <flux:menu>
            @can('viewActions', $mod)
                <flux:menu.group heading="Mod Actions">
                    @can('update', $mod)
                        <flux:menu.item href="{{ route('mod.edit', $mod->id) }}" icon:trailing="pencil-square">Edit Mod</flux:menu.item>
                    @endcan
                    @if ($mod->disabled)
                        @can('enable', $mod)
                            <flux:menu.item x-on:click.prevent="$wire.confirmModEnable = true" icon:trailing="eye">Enable Mod</flux:menu.item>
                        @endcan
                    @else
                        @can('disable', $mod)
                            <flux:menu.item x-on:click.prevent="$wire.confirmModDisable = true" icon:trailing="eye-slash">Disable Mod</flux:menu.item>
                        @endcan
                    @endif
                    @can('delete', $mod)
                        <flux:menu.item x-on:click.prevent="$wire.confirmModDelete = true" icon:trailing="trash" variant="danger">Delete Mod</flux:menu.item>
                    @endcan
                </flux:menu.group>
            @endcan
            @if (auth()->user()?->isModOrAdmin())
                <flux:menu.group heading="{{ auth()->user()->role->name }} Actions">
                    @can('update', $mod)
                        <flux:menu.item href="{{ route('mod.edit', $mod->id) }}" icon:trailing="pencil-square">Edit Mod</flux:menu.item>
                    @endcan
                    @if ($mod->featured)
                        @can('unfeature', $mod)
                            <flux:menu.item x-on:click.prevent="$wire.confirmModUnfeature = true" icon:trailing="arrow-trending-down">Remove Featured</flux:menu.item>
                        @endcan
                    @else
                        @can('feature', $mod)
                            <flux:menu.item x-on:click.prevent="$wire.confirmModFeature = true" icon:trailing="sparkles">Feature Mod</flux:menu.item>
                        @endcan
                    @endif
                    @if ($mod->disabled)
                        @can('enable', $mod)
                            <flux:menu.item x-on:click.prevent="$wire.confirmModEnable = true" icon:trailing="eye">Enable Mod</flux:menu.item>
                        @endcan
                    @else
                        @can('disable', $mod)
                            <flux:menu.item x-on:click.prevent="$wire.confirmModDisable = true" icon:trailing="eye-slash">Disable Mod</flux:menu.item>
                        @endcan
                    @endif
                    @can('delete', $mod)
                        <flux:menu.item x-on:click.prevent="$wire.confirmModDelete = true" icon:trailing="trash" variant="danger">Delete Mod</flux:menu.item>
                    @endcan
                </flux:menu.group>
            @endif
        </flux:menu>
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
            <x-secondary-button x-on:click="$wire.confirmModUnfeature = false" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            {{-- In the homepage featured section, the parent must handle the action so the listing can be updated --}}
            <x-danger-button wire:click="{{ ($homepageFeatured ? '$parent.unfeatureMod('.$mod->id.')' : 'unfeature') }}" wire:loading.attr="disabled" class="ms-3">
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
            <x-secondary-button x-on:click="$wire.confirmModFeature = false" wire:loading.attr="disabled">
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
            <x-secondary-button x-on:click="$wire.confirmModDisable = false" wire:loading.attr="disabled">
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
            <x-secondary-button x-on:click="$wire.confirmModEnable = false" wire:loading.attr="disabled">
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
            <x-secondary-button x-on:click="$wire.confirmModDelete = false" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="$parent.deleteMod({{ $mod->id }}, '{{ $routeName }}')" wire:loading.attr="disabled">
                {{ __('Delete') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
