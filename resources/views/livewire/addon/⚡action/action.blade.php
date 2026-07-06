<div x-data="{
    menuLoaded: $wire.entangle('menuOpen').live
}">
    <flux:dropdown
        position="bottom"
        align="end"
        class="absolute right-4 top-4 sm:right-6 sm:top-6"
    >
        <flux:button
            icon="cog-8-tooth"
            size="sm"
            x-on:click="if (!menuLoaded) { $wire.loadMenu(); }"
        />
        <flux:menu>
            @if ($this->menuOpen)
                @cachedCan('viewActions', $this->addon)
                    <flux:menu.group heading="Author Actions">
                        @cachedCan('update', $this->addon)
                            <flux:menu.item
                                href="{{ route('addon.edit', $this->addonId) }}"
                                icon:trailing="pencil-square"
                            >Edit Addon</flux:menu.item>
                        @endcachedCan
                        @cachedCan('create', [App\Models\AddonVersion::class, $this->addon])
                            <flux:menu.item
                                href="{{ route('addon.version.create', $this->addonId) }}"
                                icon:trailing="arrow-up-tray"
                            >Upload New Version</flux:menu.item>
                        @endcachedCan
                        @if ($this->addonPublished)
                            @cachedCan('unpublish', $this->addon)
                                <flux:modal.trigger name="addon-action-unpublish-{{ $this->addonId }}">
                                    <flux:menu.item icon:trailing="eye-slash">Unpublish Addon</flux:menu.item>
                                </flux:modal.trigger>
                            @endcachedCan
                        @else
                            @cachedCan('publish', $this->addon)
                                <flux:modal.trigger name="addon-action-publish-{{ $this->addonId }}">
                                    <flux:menu.item icon:trailing="eye">Publish Addon</flux:menu.item>
                                </flux:modal.trigger>
                            @endcachedCan
                        @endif
                        @if ($this->addonDetached)
                            @cachedCan('attach', $this->addon)
                                <flux:modal.trigger name="addon-action-attach-{{ $this->addonId }}">
                                    <flux:menu.item icon:trailing="link">Attach Addon</flux:menu.item>
                                </flux:modal.trigger>
                            @endcachedCan
                        @else
                            @cachedCan('detach', $this->addon)
                                <flux:modal.trigger name="addon-action-detach-{{ $this->addonId }}">
                                    <flux:menu.item icon:trailing="link-slash">Detach Addon</flux:menu.item>
                                </flux:modal.trigger>
                            @endcachedCan
                        @endif
                        @cachedCan('delete', $this->addon)
                            <flux:modal.trigger name="addon-action-delete-{{ $this->addonId }}">
                                <flux:menu.item
                                    icon:trailing="trash"
                                    variant="danger"
                                >Delete Addon</flux:menu.item>
                            </flux:modal.trigger>
                        @endcachedCan
                    </flux:menu.group>
                @endcachedCan
                @if (auth()->user()?->isModOrAdmin())
                    <flux:menu.group heading="{{ auth()->user()->role->name }} Actions">
                        @cachedCan('update', $this->addon)
                            <flux:menu.item
                                href="{{ route('addon.edit', $this->addonId) }}"
                                icon:trailing="pencil-square"
                            >Edit Addon</flux:menu.item>
                        @endcachedCan
                        @if ($this->addonDisabled)
                            @cachedCan('enable', $this->addon)
                                <flux:modal.trigger name="addon-action-enable-{{ $this->addonId }}">
                                    <flux:menu.item icon:trailing="eye">Enable Addon</flux:menu.item>
                                </flux:modal.trigger>
                            @endcachedCan
                        @else
                            @cachedCan('disable', $this->addon)
                                <flux:modal.trigger name="addon-action-disable-{{ $this->addonId }}">
                                    <flux:menu.item icon:trailing="eye-slash">Disable Addon</flux:menu.item>
                                </flux:modal.trigger>
                            @endcachedCan
                        @endif
                        @if ($this->addonDetached)
                            @cachedCan('attach', $this->addon)
                                <flux:modal.trigger name="addon-action-attach-{{ $this->addonId }}">
                                    <flux:menu.item icon:trailing="link">Attach Addon</flux:menu.item>
                                </flux:modal.trigger>
                            @endcachedCan
                        @else
                            @cachedCan('detach', $this->addon)
                                <flux:modal.trigger name="addon-action-detach-{{ $this->addonId }}">
                                    <flux:menu.item icon:trailing="link-slash">Detach Addon</flux:menu.item>
                                </flux:modal.trigger>
                            @endcachedCan
                        @endif
                        @cachedCan('delete', $this->addon)
                            <flux:modal.trigger name="addon-action-delete-{{ $this->addonId }}">
                                <flux:menu.item
                                    icon:trailing="trash"
                                    variant="danger"
                                >Delete Addon</flux:menu.item>
                            </flux:modal.trigger>
                        @endcachedCan
                    </flux:menu.group>
                @endif
            @else
                <flux:menu.item disabled>Loading...</flux:menu.item>
            @endif
        </flux:menu>
    </flux:dropdown>

    {{-- Modals --}}
    <flux:modal
        name="addon-action-disable-{{ $this->addonId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="eye-slash"
                        class="h-8 w-8 text-amber-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Disable Addon') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Hide from visitors') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-sm text-gray-300">
                    Are you sure you want to disable the "{{ $this->addonName }}" addon?
                </flux:text>
                <flux:text class="text-sm text-gray-300">
                    This will prevent visitors on the site from seeing the addon.
                </flux:text>

                @if ($this->showModerationReason)
                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('addon-action-disable-{{ $this->addonId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('addon-action-disable-{{ $this->addonId }}').close(); $wire.disable()"
                    variant="primary"
                    size="sm"
                    icon="eye-slash"
                    class="bg-amber-600 text-white hover:bg-amber-700"
                >
                    {{ __('Disable') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="addon-action-enable-{{ $this->addonId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="eye"
                        class="h-8 w-8 text-green-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Enable Addon') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Make visible to visitors') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-sm text-gray-300">
                    Are you sure you want to enable the "{{ $this->addonName }}" addon?
                </flux:text>
                <flux:text class="text-sm text-gray-300">
                    This will allow visitors on the site to see the addon.
                </flux:text>

                @if ($this->showModerationReason)
                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('addon-action-enable-{{ $this->addonId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('addon-action-enable-{{ $this->addonId }}').close(); $wire.enable()"
                    variant="primary"
                    size="sm"
                    icon="eye"
                    class="bg-green-600 text-white hover:bg-green-700"
                >
                    {{ __('Enable') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="addon-action-delete-{{ $this->addonId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="trash"
                        class="h-8 w-8 text-red-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Delete Addon') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Permanent deletion') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-sm text-gray-300">
                    Are you sure you want to delete the "{{ $this->addonName }}" addon?
                </flux:text>

                <div class="rounded-lg border border-amber-700 bg-amber-950/30 p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon
                            name="exclamation-triangle"
                            class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600"
                        />
                        <div>
                            <flux:text class="text-sm font-medium text-amber-200">
                                {{ __('Attention!') }}
                            </flux:text>
                            <flux:text class="mt-1 text-sm text-amber-300">
                                The addon will be permanently deleted, along with all addon versions, addon comments,
                                and any
                                other data tied directly to the addon. If this isn't necessary, you should simply
                                disable
                                the addon instead.
                            </flux:text>
                        </div>
                    </div>
                </div>

                @if ($this->showModerationReason)
                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-between border-t border-gray-700 pt-6">
                <div class="flex items-center text-xs text-red-400">
                    <flux:icon
                        name="shield-exclamation"
                        class="mr-2 h-4 w-4 flex-shrink-0"
                    />
                    <span class="leading-tight">
                        {{ __('This action cannot be undone') }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button
                        x-on:click="$wire.moderationReason = ''; $flux.modal('addon-action-delete-{{ $this->addonId }}').close()"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        x-on:click="$flux.modal('addon-action-delete-{{ $this->addonId }}').close(); $wire.$parent.deleteAddon({{ $this->addonId }}, '{{ $routeName }}', $wire.moderationReason)"
                        variant="primary"
                        size="sm"
                        icon="trash"
                        class="bg-red-600 text-white hover:bg-red-700"
                    >
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="addon-action-publish-{{ $this->addonId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="eye"
                        class="h-8 w-8 text-green-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Publish Addon') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Make visible to visitors') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-sm text-gray-300">
                    Select the date and time the addon "{{ $this->addonName }}" will be published. If the addon is not
                    published, it will not be discoverable by other users.
                </flux:text>

                <flux:field>
                    <flux:label>{{ __('Publish Date') }}</flux:label>
                    <flux:description>
                        {!! __('Select the date and time the addon will be published.') !!}
                        @if (auth()->user()?->timezone === null)
                            <flux:callout
                                icon="exclamation-triangle"
                                color="orange"
                                inline="inline"
                                class="my-2"
                            >
                                <flux:callout.text>
                                    You have not selected a timezone for your account. The published date will be
                                    interpreted as a UTC date. You can <a
                                        href="/user/profile"
                                        class="text-white underline transition-colors hover:text-cyan-200"
                                    >edit your profile</a> to set a specific timezone.
                                </flux:callout.text>
                            </flux:callout>
                        @else
                            {{ __('Your timezone is set to :timezone.', ['timezone' => auth()->user()?->timezone]) }}
                        @endif
                    </flux:description>
                    <div class="flex items-center gap-2">
                        <flux:date-picker
                            wire:model="publishedAtDate"
                            clearable
                        />
                        <flux:time-picker
                            wire:model="publishedAtTime"
                            clearable
                        />
                        @if (auth()->user()?->timezone !== null)
                            <flux:button
                                size="sm"
                                variant="outline"
                                @click="$wire.set('publishedAtDate', '{{ now()->format('Y-m-d') }}'); $wire.set('publishedAtTime', '{{ now()->format('H:i') }}')"
                            >Now</flux:button>
                        @endif
                    </div>
                </flux:field>

                @if ($this->showModerationReason)
                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('addon-action-publish-{{ $this->addonId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('addon-action-publish-{{ $this->addonId }}').close(); $wire.publish()"
                    variant="primary"
                    size="sm"
                    icon="eye"
                    class="bg-green-600 text-white hover:bg-green-700"
                >
                    {{ __('Publish') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="addon-action-unpublish-{{ $this->addonId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="eye-slash"
                        class="h-8 w-8 text-amber-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Unpublish Addon') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Hide from visitors') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-sm text-gray-300">
                    Are you sure you want to unpublish the "{{ $this->addonName }}" addon?
                </flux:text>
                <flux:text class="text-sm text-gray-300">
                    This will remove the published date and prevent visitors on the site from discovering the addon.
                </flux:text>

                @if ($this->showModerationReason)
                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('addon-action-unpublish-{{ $this->addonId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('addon-action-unpublish-{{ $this->addonId }}').close(); $wire.unpublish()"
                    variant="primary"
                    size="sm"
                    icon="eye-slash"
                    class="bg-amber-600 text-white hover:bg-amber-700"
                >
                    {{ __('Unpublish') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="addon-action-attach-{{ $this->addonId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="link"
                        class="h-8 w-8 text-green-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Attach Addon') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Restore addon to parent mod') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-sm text-gray-300">
                    Are you sure you want to attach the "{{ $this->addonName }}" addon back to its parent mod?
                </flux:text>
                <flux:text class="text-sm text-gray-300">
                    This will restore the addon's association with its parent mod and make it visible in the mod's addon
                    list.
                </flux:text>

                @if ($this->showModerationReason)
                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('addon-action-attach-{{ $this->addonId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('addon-action-attach-{{ $this->addonId }}').close(); $wire.attach()"
                    variant="primary"
                    size="sm"
                    icon="link"
                    class="bg-green-600 text-white hover:bg-green-700"
                >
                    {{ __('Attach') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="addon-action-detach-{{ $this->addonId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="link-slash"
                        class="h-8 w-8 text-amber-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Detach Addon') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Remove from parent mod') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-sm text-gray-300">
                    Are you sure you want to detach the "{{ $this->addonName }}" addon from its parent mod?
                </flux:text>

                <div class="rounded-lg border border-amber-700 bg-amber-950/30 p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon
                            name="exclamation-triangle"
                            class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600"
                        />
                        <div>
                            <flux:text class="text-sm font-medium text-amber-200">
                                {{ __('Important!') }}
                            </flux:text>
                            <flux:text class="mt-1 text-sm text-amber-300">
                                Detaching this addon will hide it from the parent mod's addon list. The addon will
                                remain accessible via direct link and search, but will no longer appear as associated
                                with the parent mod.
                            </flux:text>
                        </div>
                    </div>
                </div>

                @if ($this->showModerationReason)
                    <flux:textarea
                        wire:model="moderationReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Enter reason for this action...') }}"
                        rows="3"
                    />
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('addon-action-detach-{{ $this->addonId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('addon-action-detach-{{ $this->addonId }}').close(); $wire.detach()"
                    variant="primary"
                    size="sm"
                    icon="link-slash"
                    class="bg-amber-600 text-white hover:bg-amber-700"
                >
                    {{ __('Detach') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
