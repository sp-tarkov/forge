<div
    x-data="{
        menuLoaded: @entangle('menuOpen').live
    }">
    <flux:dropdown position="bottom" align="end" class="absolute top-1.5 right-1.5">
        <flux:button
            icon="cog-8-tooth"
            x-on:click="if (!menuLoaded) { $wire.loadMenu(); }"
        />
        <flux:menu>
            @if($this->menuOpen)
                @if($this->permissions['viewActions'] ?? false)
                    <flux:menu.group heading="Mod Version Actions">
                        @if($this->permissions['update'] ?? false)
                            <flux:menu.item href="{{ route('mod.version.edit', [$this->modId, $this->versionId]) }}" icon="pencil">Edit Version</flux:menu.item>
                        @endif
                        @if ($this->versionDisabled)
                            @if($this->permissions['enable'] ?? false)
                                <flux:menu.item href="#" wire:click.prevent="openModal('enable')" icon="eye">Enable Version</flux:menu.item>
                            @endif
                        @else
                            @if($this->permissions['disable'] ?? false)
                                <flux:menu.item href="#" wire:click.prevent="openModal('disable')" icon="eye-slash">Disable Version</flux:menu.item>
                            @endif
                        @endif
                        @if($this->permissions['delete'] ?? false)
                            <flux:menu.item href="#" wire:click.prevent="openModal('delete')" icon="trash" variant="danger">Delete Version</flux:menu.item>
                        @endif
                    </flux:menu.group>
                @endif
                @if ($this->permissions['isModOrAdmin'] ?? false)
                    <flux:menu.group heading="{{ auth()->user()->role->name }} Actions">
                        @if($this->permissions['update'] ?? false)
                            <flux:menu.item href="{{ route('mod.version.edit', [$this->modId, $this->versionId]) }}" icon="pencil">Edit Version</flux:menu.item>
                        @endif
                        @if ($this->versionDisabled)
                            @if($this->permissions['enable'] ?? false)
                                <flux:menu.item href="#" wire:click.prevent="openModal('enable')" icon="eye">Enable Version</flux:menu.item>
                            @endif
                        @else
                            @if($this->permissions['disable'] ?? false)
                                <flux:menu.item href="#" wire:click.prevent="openModal('disable')" icon="eye-slash">Disable Version</flux:menu.item>
                            @endif
                        @endif
                        @if($this->permissions['delete'] ?? false)
                            <flux:menu.item href="#" wire:click.prevent="openModal('delete')" icon="trash" variant="danger">Delete Version</flux:menu.item>
                        @endif
                    </flux:menu.group>
                @endif
            @else
                <flux:menu.item disabled>Loading...</flux:menu.item>
            @endif
        </flux:menu>
    </flux:dropdown>

    {{-- Lazy load modals only when needed --}}
    @if($this->activeModal)
        @teleport('body')
            <div wire:key="modal-{{ $this->activeModal }}">
                @switch($this->activeModal)
                    @case('enable')
                        <x-confirmation-modal wire:model.live="activeModal">
                            <x-slot name="title">
                                {{ __('Enable Mod') }}
                            </x-slot>

                            <x-slot name="content">
                                <p class="my-2.5">Are you sure you want to enable version "{{ $this->versionNumber }}" of the "{{ $this->version->mod->name ?? 'Unknown' }}" mod?</p>
                                <p class="my-2.5">This will allow visitors to view and download the version.</p>
                            </x-slot>

                            <x-slot name="footer">
                                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                                    {{ __('Cancel') }}
                                </x-secondary-button>

                                <x-danger-button class="ms-3" wire:click="enable" wire:loading.attr="disabled">
                                    {{ __('Enable') }}
                                </x-danger-button>
                            </x-slot>
                        </x-confirmation-modal>
                        @break

                    @case('disable')
                        <x-confirmation-modal wire:model.live="activeModal">
                            <x-slot name="title">
                                {{ __('Disable Mod') }}
                            </x-slot>

                            <x-slot name="content">
                                <p class="my-2.5">Are you sure you want to disable version "{{ $this->versionNumber }}" of the "{{ $this->version->mod->name ?? 'Unknown' }}" mod?</p>
                                <p class="my-2.5">This will prevent visitors from viewing or downloading the version.</p>
                            </x-slot>

                            <x-slot name="footer">
                                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                                    {{ __('Cancel') }}
                                </x-secondary-button>

                                <x-danger-button class="ms-3" wire:click="disable" wire:loading.attr="disabled">
                                    {{ __('Disable') }}
                                </x-danger-button>
                            </x-slot>
                        </x-confirmation-modal>
                        @break

                    @case('delete')
                        <x-confirmation-modal wire:model.live="activeModal">
                            <x-slot name="title">
                                {{ __('Delete Mod') }}
                            </x-slot>

                            <x-slot name="content">
                                <p class="my-2.5">Are you sure you want to delete version "{{ $this->versionNumber }}" of the "{{ $this->version->mod->name ?? 'Unknown' }}" mod?</p>
                                <p class="my-2.5">The mod version will be permanently deleted, along with all data tied directly to the version. If this isn't necessary you should simply disable the version instead.</p>
                            </x-slot>

                            <x-slot name="footer">
                                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                                    {{ __('Cancel') }}
                                </x-secondary-button>

                                <x-danger-button class="ms-3" wire:click="$parent.deleteModVersion({{ $this->versionId }})" wire:loading.attr="disabled">
                                    {{ __('Delete') }}
                                </x-danger-button>
                            </x-slot>
                        </x-confirmation-modal>
                        @break
                @endswitch
            </div>
        @endteleport
    @endif
</div>
