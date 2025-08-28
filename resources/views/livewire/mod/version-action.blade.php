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
                    <flux:menu.group heading="Author Actions">
                        @if($this->permissions['update'] ?? false)
                            <flux:menu.item href="{{ route('mod.version.edit', [$this->modId, $this->versionId]) }}" icon="pencil">Edit Version</flux:menu.item>
                        @endif
                        @if ($this->versionDisabled)
                            @if($this->permissions['enable'] ?? false)
                                <flux:modal.trigger name="mod-version-action-enable-{{ $this->versionId }}">
                                    <flux:menu.item icon="eye">Enable Version</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @else
                            @if($this->permissions['disable'] ?? false)
                                <flux:modal.trigger name="mod-version-action-disable-{{ $this->versionId }}">
                                    <flux:menu.item icon="eye-slash">Disable Version</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @endif
                        @if($this->permissions['delete'] ?? false)
                            <flux:modal.trigger name="mod-version-action-delete-{{ $this->versionId }}">
                                <flux:menu.item icon="trash" variant="danger">Delete Version</flux:menu.item>
                            </flux:modal.trigger>
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
                                <flux:modal.trigger name="mod-version-action-enable-{{ $this->versionId }}">
                                    <flux:menu.item icon="eye">Enable Version</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @else
                            @if($this->permissions['disable'] ?? false)
                                <flux:modal.trigger name="mod-version-action-disable-{{ $this->versionId }}">
                                    <flux:menu.item icon="eye-slash">Disable Version</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @endif
                        @if($this->permissions['delete'] ?? false)
                            <flux:modal.trigger name="mod-version-action-delete-{{ $this->versionId }}">
                                <flux:menu.item icon="trash" variant="danger">Delete Version</flux:menu.item>
                            </flux:modal.trigger>
                        @endif
                    </flux:menu.group>
                @endif
            @else
                <flux:menu.item disabled>Loading...</flux:menu.item>
            @endif
        </flux:menu>
    </flux:dropdown>

    {{-- Modals --}}
    <flux:modal name="mod-version-action-enable-{{ $this->versionId }}" class="md:w-[500px] lg:w-[600px]">
                            <div class="space-y-0">
                                {{-- Header Section --}}
                                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                                    <div class="flex items-center gap-3">
                                        <flux:icon name="eye" class="w-8 h-8 text-green-600" />
                                        <div>
                                            <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                                                {{ __('Enable Mod Version') }}
                                            </flux:heading>
                                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                                {{ __('Make version available') }}
                                            </flux:text>
                                        </div>
                                    </div>
                                </div>

                                {{-- Content Section --}}
                                <div class="space-y-4">
                                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                                        Are you sure you want to enable version "{{ $this->versionNumber }}" of the "{{ $this->version->mod->name ?? 'Unknown' }}" mod?
                                    </flux:text>
                                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                                        This will allow visitors to view and download the version.
                                    </flux:text>
                                </div>

                                {{-- Footer Actions --}}
                                <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                                    <flux:button x-on:click="$flux.modal('mod-version-action-enable-{{ $this->versionId }}').close()" variant="outline" size="sm">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                    <flux:button x-on:click="$flux.modal('mod-version-action-enable-{{ $this->versionId }}').close(); $wire.enable()" variant="primary" size="sm" icon="eye" class="bg-green-600 hover:bg-green-700 text-white">
                                        {{ __('Enable') }}
                                    </flux:button>
                                </div>
                            </div>
    </flux:modal>

    <flux:modal name="mod-version-action-disable-{{ $this->versionId }}" class="md:w-[500px] lg:w-[600px]">
                            <div class="space-y-0">
                                {{-- Header Section --}}
                                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                                    <div class="flex items-center gap-3">
                                        <flux:icon name="eye-slash" class="w-8 h-8 text-amber-600" />
                                        <div>
                                            <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                                                {{ __('Disable Mod Version') }}
                                            </flux:heading>
                                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                                {{ __('Hide version from visitors') }}
                                            </flux:text>
                                        </div>
                                    </div>
                                </div>

                                {{-- Content Section --}}
                                <div class="space-y-4">
                                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                                        Are you sure you want to disable version "{{ $this->versionNumber }}" of the "{{ $this->version->mod->name ?? 'Unknown' }}" mod?
                                    </flux:text>
                                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                                        This will prevent visitors from viewing or downloading the version.
                                    </flux:text>
                                </div>

                                {{-- Footer Actions --}}
                                <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                                    <flux:button x-on:click="$flux.modal('mod-version-action-disable-{{ $this->versionId }}').close()" variant="outline" size="sm">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                    <flux:button x-on:click="$flux.modal('mod-version-action-disable-{{ $this->versionId }}').close(); $wire.disable()" variant="primary" size="sm" icon="eye-slash" class="bg-amber-600 hover:bg-amber-700 text-white">
                                        {{ __('Disable') }}
                                    </flux:button>
                                </div>
                            </div>
    </flux:modal>

    <flux:modal name="mod-version-action-delete-{{ $this->versionId }}" class="md:w-[500px] lg:w-[600px]">
                            <div class="space-y-0">
                                {{-- Header Section --}}
                                <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                                    <div class="flex items-center gap-3">
                                        <flux:icon name="trash" class="w-8 h-8 text-red-600" />
                                        <div>
                                            <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                                                {{ __('Delete Mod Version') }}
                                            </flux:heading>
                                            <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                                {{ __('Permanent deletion') }}
                                            </flux:text>
                                        </div>
                                    </div>
                                </div>

                                {{-- Content Section --}}
                                <div class="space-y-4">
                                    <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                                        Are you sure you want to delete version "{{ $this->versionNumber }}" of the "{{ $this->version->mod->name ?? 'Unknown' }}" mod?
                                    </flux:text>
                                    
                                    <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-700 rounded-lg p-4">
                                        <div class="flex items-start gap-3">
                                            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" />
                                            <div>
                                                <flux:text class="text-amber-900 dark:text-amber-200 text-sm font-medium">
                                                    {{ __('Attention!') }}
                                                </flux:text>
                                                <flux:text class="text-amber-800 dark:text-amber-300 text-sm mt-1">
                                                    The mod version will be permanently deleted, along with all data tied directly to the version. If this isn't necessary you should simply disable the version instead.
                                                </flux:text>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Footer Actions --}}
                                <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center text-xs text-red-600 dark:text-red-400">
                                        <flux:icon name="shield-exclamation" class="w-4 h-4 mr-2 flex-shrink-0" />
                                        <span class="leading-tight">
                                            {{ __('This action cannot be undone') }}
                                        </span>
                                    </div>

                                    <div class="flex gap-3">
                                        <flux:button x-on:click="$flux.modal('mod-version-action-delete-{{ $this->versionId }}').close()" variant="outline" size="sm">
                                            {{ __('Cancel') }}
                                        </flux:button>
                                        <flux:button x-on:click="$flux.modal('mod-version-action-delete-{{ $this->versionId }}').close(); $wire.$parent.deleteModVersion({{ $this->versionId }})" variant="primary" size="sm" icon="trash" class="bg-red-600 hover:bg-red-700 text-white">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </div>
    </flux:modal>
</div>