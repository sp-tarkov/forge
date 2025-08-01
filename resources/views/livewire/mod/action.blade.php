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
                    <flux:menu.group heading="Mod Actions">
                        @if($this->permissions['update'] ?? false)
                            <flux:menu.item href="{{ route('mod.edit', $this->modId) }}" icon:trailing="pencil-square">Edit Mod</flux:menu.item>
                        @endif
                        @if ($this->modDisabled)
                            @if($this->permissions['enable'] ?? false)
                                <flux:menu.item x-on:click.prevent="$wire.openModal('enable')" icon:trailing="eye">Enable Mod</flux:menu.item>
                            @endif
                        @else
                            @if($this->permissions['disable'] ?? false)
                                <flux:menu.item x-on:click.prevent="$wire.openModal('disable')" icon:trailing="eye-slash">Disable Mod</flux:menu.item>
                            @endif
                        @endif
                        @if($this->permissions['delete'] ?? false)
                            <flux:menu.item x-on:click.prevent="$wire.openModal('delete')" icon:trailing="trash" variant="danger">Delete Mod</flux:menu.item>
                        @endif
                    </flux:menu.group>
                @endif
                @if ($this->permissions['isModOrAdmin'] ?? false)
                    <flux:menu.group heading="{{ auth()->user()->role->name }} Actions">
                        @if($this->permissions['update'] ?? false)
                            <flux:menu.item href="{{ route('mod.edit', $this->modId) }}" icon:trailing="pencil-square">Edit Mod</flux:menu.item>
                        @endif
                        @if ($this->modFeatured)
                            @if($this->permissions['unfeature'] ?? false)
                                <flux:menu.item x-on:click.prevent="$wire.openModal('unfeature')" icon:trailing="arrow-trending-down">Remove Featured</flux:menu.item>
                            @endif
                        @else
                            @if($this->permissions['feature'] ?? false)
                                <flux:menu.item x-on:click.prevent="$wire.openModal('feature')" icon:trailing="sparkles">Feature Mod</flux:menu.item>
                            @endif
                        @endif
                        @if ($this->modDisabled)
                            @if($this->permissions['enable'] ?? false)
                                <flux:menu.item x-on:click.prevent="$wire.openModal('enable')" icon:trailing="eye">Enable Mod</flux:menu.item>
                            @endif
                        @else
                            @if($this->permissions['disable'] ?? false)
                                <flux:menu.item x-on:click.prevent="$wire.openModal('disable')" icon:trailing="eye-slash">Disable Mod</flux:menu.item>
                            @endif
                        @endif
                        @if($this->permissions['delete'] ?? false)
                            <flux:menu.item x-on:click.prevent="$wire.openModal('delete')" icon:trailing="trash" variant="danger">Delete Mod</flux:menu.item>
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
                    @case('unfeature')
                        <x-confirmation-modal wire:model.live="activeModal">
                            <x-slot name="title">
                                {{ __('Unfeature Mod') }}
                            </x-slot>

                            <x-slot name="content">
                                <p class="my-2.5">Are you sure you want to unfeature the "{{ $this->modName }}" mod?</p>
                                <p class="my-2.5">This will remove the mod from the homepage featured section and remove the "featured" ribbon on it's listing card.</p>
                            </x-slot>

                            <x-slot name="footer">
                                <x-secondary-button x-on:click="$wire.closeModal()" wire:loading.attr="disabled">
                                    {{ __('Cancel') }}
                                </x-secondary-button>

                                {{-- In the homepage featured section, the parent must handle the action so the listing can be updated --}}
                                <x-danger-button wire:click="{{ ($homepageFeatured ? '$parent.unfeatureMod('.$this->modId.')' : 'unfeature') }}" wire:loading.attr="disabled" class="ms-3">
                                    {{ __('Unfeature') }}
                                </x-danger-button>
                            </x-slot>
                        </x-confirmation-modal>
                        @break

                    @case('feature')
                        <x-confirmation-modal wire:model.live="activeModal">
                            <x-slot name="title">
                                {{ __('Feature Mod') }}
                            </x-slot>

                            <x-slot name="content">
                                <p class="my-2.5">Are you sure you want to feature the "{{ $this->modName }}" mod?</p>
                                <p class="my-2.5">This will add the mod to the homepage featured section and add add the "featured" ribbon on it's listing card.</p>
                            </x-slot>

                            <x-slot name="footer">
                                <x-secondary-button x-on:click="$wire.closeModal()" wire:loading.attr="disabled">
                                    {{ __('Cancel') }}
                                </x-secondary-button>

                                <x-danger-button class="ms-3" wire:click="feature" wire:loading.attr="disabled">
                                    {{ __('Feature') }}
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
                                <p class="my-2.5">Are you sure you want to disable the "{{ $this->modName }}" mod?</p>
                                <p class="my-2.5">This will prevent visitors on the site from seeing the mod.</p>
                            </x-slot>

                            <x-slot name="footer">
                                <x-secondary-button x-on:click="$wire.closeModal()" wire:loading.attr="disabled">
                                    {{ __('Cancel') }}
                                </x-secondary-button>

                                <x-danger-button class="ms-3" wire:click="disable" wire:loading.attr="disabled">
                                    {{ __('Disable') }}
                                </x-danger-button>
                            </x-slot>
                        </x-confirmation-modal>
                        @break

                    @case('enable')
                        <x-confirmation-modal wire:model.live="activeModal">
                            <x-slot name="title">
                                {{ __('Enable Mod') }}
                            </x-slot>

                            <x-slot name="content">
                                <p class="my-2.5">Are you sure you want to enable the "{{ $this->modName }}" mod?</p>
                                <p class="my-2.5">This will allow visitors on the site to see the mod.</p>
                            </x-slot>

                            <x-slot name="footer">
                                <x-secondary-button x-on:click="$wire.closeModal()" wire:loading.attr="disabled">
                                    {{ __('Cancel') }}
                                </x-secondary-button>

                                <x-danger-button class="ms-3" wire:click="enable" wire:loading.attr="disabled">
                                    {{ __('Enable') }}
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
                                <p class="my-2.5">Are you sure you want to delete the "{{ $this->modName }}" mod?</p>
                                <p class="my-2.5">The mod will be permanently deleted, along with all mod versions, mod comments, and any other data tied directly to the mod.</p>
                                <p class="my-2.5">If this isn't necessary you should simply disable the mod instead.</p>
                            </x-slot>

                            <x-slot name="footer">
                                <x-secondary-button x-on:click="$wire.closeModal()" wire:loading.attr="disabled">
                                    {{ __('Cancel') }}
                                </x-secondary-button>

                                <x-danger-button class="ms-3" wire:click="$parent.deleteMod({{ $this->modId }}, '{{ $routeName }}')" wire:loading.attr="disabled">
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