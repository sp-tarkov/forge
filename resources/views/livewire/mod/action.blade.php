<div x-data="{
    menuLoaded: @entangle('menuOpen').live
}">
    <flux:dropdown
        position="bottom"
        align="end"
        class="absolute top-1.5 right-1.5"
    >
        <flux:button
            icon="cog-8-tooth"
            x-on:click="if (!menuLoaded) { $wire.loadMenu(); }"
        />
        <flux:menu>
            @if ($this->menuOpen)
                @if ($this->permissions['viewActions'] ?? false)
                    <flux:menu.group heading="Author Actions">
                        @if ($this->permissions['update'] ?? false)
                            <flux:menu.item
                                href="{{ route('mod.edit', $this->modId) }}"
                                icon:trailing="pencil-square"
                            >Edit Mod</flux:menu.item>
                        @endif
                        @if ($this->modPublished)
                            @if ($this->permissions['unpublish'] ?? false)
                                <flux:modal.trigger name="mod-action-unpublish-{{ $this->modId }}">
                                    <flux:menu.item icon:trailing="eye-slash">Unpublish Mod</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @else
                            @if ($this->permissions['publish'] ?? false)
                                <flux:modal.trigger name="mod-action-publish-{{ $this->modId }}">
                                    <flux:menu.item icon:trailing="eye">Publish Mod</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @endif
                        @if ($this->permissions['delete'] ?? false)
                            <flux:modal.trigger name="mod-action-delete-{{ $this->modId }}">
                                <flux:menu.item
                                    icon:trailing="trash"
                                    variant="danger"
                                >Delete Mod</flux:menu.item>
                            </flux:modal.trigger>
                        @endif
                    </flux:menu.group>
                @endif
                @if ($this->permissions['isModOrAdmin'] ?? false)
                    <flux:menu.group heading="{{ auth()->user()->role->name }} Actions">
                        @if ($this->permissions['update'] ?? false)
                            <flux:menu.item
                                href="{{ route('mod.edit', $this->modId) }}"
                                icon:trailing="pencil-square"
                            >Edit Mod</flux:menu.item>
                        @endif
                        @if ($this->modFeatured)
                            @if ($this->permissions['unfeature'] ?? false)
                                <flux:modal.trigger name="mod-action-unfeature-{{ $this->modId }}">
                                    <flux:menu.item icon:trailing="arrow-trending-down">Remove Featured</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @else
                            @if ($this->permissions['feature'] ?? false)
                                <flux:modal.trigger name="mod-action-feature-{{ $this->modId }}">
                                    <flux:menu.item icon:trailing="sparkles">Feature Mod</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @endif
                        @if ($this->modDisabled)
                            @if ($this->permissions['enable'] ?? false)
                                <flux:modal.trigger name="mod-action-enable-{{ $this->modId }}">
                                    <flux:menu.item icon:trailing="eye">Enable Mod</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @else
                            @if ($this->permissions['disable'] ?? false)
                                <flux:modal.trigger name="mod-action-disable-{{ $this->modId }}">
                                    <flux:menu.item icon:trailing="eye-slash">Disable Mod</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @endif
                        @if ($this->permissions['delete'] ?? false)
                            <flux:modal.trigger name="mod-action-delete-{{ $this->modId }}">
                                <flux:menu.item
                                    icon:trailing="trash"
                                    variant="danger"
                                >Delete Mod</flux:menu.item>
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
    <flux:modal
        name="mod-action-unfeature-{{ $this->modId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="arrow-trending-down"
                        class="w-8 h-8 text-amber-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Unfeature Mod') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Remove from featured section') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    Are you sure you want to unfeature the "{{ $this->modName }}" mod?
                </flux:text>
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    This will remove the mod from the homepage featured section and remove the "featured" ribbon on its
                    listing card.
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$flux.modal('mod-action-unfeature-{{ $this->modId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                {{-- In the homepage featured section, the parent must handle the action so the listing can be updated --}}
                <flux:button
                    x-on:click="$flux.modal('mod-action-unfeature-{{ $this->modId }}').close(); {{ $homepageFeatured ? '$wire.$parent.unfeatureMod(' . $this->modId . ')' : '$wire.unfeature()' }}"
                    variant="primary"
                    size="sm"
                    icon="arrow-trending-down"
                    class="bg-amber-600 hover:bg-amber-700 text-white"
                >
                    {{ __('Remove Featured') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="mod-action-feature-{{ $this->modId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="sparkles"
                        class="w-8 h-8 text-blue-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Feature Mod') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Highlight this mod') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    Are you sure you want to feature the "{{ $this->modName }}" mod?
                </flux:text>
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    This will add the mod to the homepage featured section and add the "featured" ribbon on its listing
                    card.
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$flux.modal('mod-action-feature-{{ $this->modId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('mod-action-feature-{{ $this->modId }}').close(); $wire.feature()"
                    variant="primary"
                    size="sm"
                    icon="sparkles"
                    class="bg-blue-600 hover:bg-blue-700 text-white"
                >
                    {{ __('Feature') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="mod-action-disable-{{ $this->modId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="eye-slash"
                        class="w-8 h-8 text-amber-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Disable Mod') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Hide from visitors') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    Are you sure you want to disable the "{{ $this->modName }}" mod?
                </flux:text>
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    This will prevent visitors on the site from seeing the mod.
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$flux.modal('mod-action-disable-{{ $this->modId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('mod-action-disable-{{ $this->modId }}').close(); $wire.disable()"
                    variant="primary"
                    size="sm"
                    icon="eye-slash"
                    class="bg-amber-600 hover:bg-amber-700 text-white"
                >
                    {{ __('Disable') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="mod-action-enable-{{ $this->modId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="eye"
                        class="w-8 h-8 text-green-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Enable Mod') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Make visible to visitors') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    Are you sure you want to enable the "{{ $this->modName }}" mod?
                </flux:text>
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    This will allow visitors on the site to see the mod.
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$flux.modal('mod-action-enable-{{ $this->modId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('mod-action-enable-{{ $this->modId }}').close(); $wire.enable()"
                    variant="primary"
                    size="sm"
                    icon="eye"
                    class="bg-green-600 hover:bg-green-700 text-white"
                >
                    {{ __('Enable') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="mod-action-delete-{{ $this->modId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="trash"
                        class="w-8 h-8 text-red-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Delete Mod') }}
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
                    Are you sure you want to delete the "{{ $this->modName }}" mod?
                </flux:text>

                <div
                    class="bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-700 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon
                            name="exclamation-triangle"
                            class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0"
                        />
                        <div>
                            <flux:text class="text-amber-900 dark:text-amber-200 text-sm font-medium">
                                {{ __('Attention!') }}
                            </flux:text>
                            <flux:text class="text-amber-800 dark:text-amber-300 text-sm mt-1">
                                The mod will be permanently deleted, along with all mod versions, mod comments, and any
                                other data tied directly to the mod. If this isn't necessary, you should simply disable
                                the mod instead.
                            </flux:text>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center text-xs text-red-600 dark:text-red-400">
                    <flux:icon
                        name="shield-exclamation"
                        class="w-4 h-4 mr-2 flex-shrink-0"
                    />
                    <span class="leading-tight">
                        {{ __('This action cannot be undone') }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button
                        x-on:click="$flux.modal('mod-action-delete-{{ $this->modId }}').close()"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        x-on:click="$flux.modal('mod-action-delete-{{ $this->modId }}').close(); $wire.$parent.deleteMod({{ $this->modId }}, '{{ $routeName }}')"
                        variant="primary"
                        size="sm"
                        icon="trash"
                        class="bg-red-600 hover:bg-red-700 text-white"
                    >
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="mod-action-publish-{{ $this->modId }}"
        class="md:w-[500px] lg:w-[600px]"
        x-data="{
            now() {
                // Format: YYYY-MM-DDTHH:MM
                const pad = n => n.toString().padStart(2, '0');
                const d = new Date();
                return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            }
        }"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="eye"
                        class="w-8 h-8 text-green-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Publish Mod') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Make visible to visitors') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    Select the date and time the mod "{{ $this->modName }}" will be published. If the mod is not
                    published, it will not be discoverable by other users.
                </flux:text>

                <flux:field>
                    <flux:label>{{ __('Publish Date') }}</flux:label>
                    <flux:description>
                        {!! __('Select the date and time the mod will be published.') !!}
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
                                        class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                                    >edit your profile</a> to set a specific timezone.
                                </flux:callout.text>
                            </flux:callout>
                        @else
                            {{ __('Your timezone is set to :timezone.', ['timezone' => auth()->user()?->timezone]) }}
                        @endif
                    </flux:description>
                    <div class="flex gap-2 items-center">
                        <flux:input
                            type="datetime-local"
                            wire:model.defer="publishedAt"
                        />
                        @if (auth()->user()?->timezone !== null)
                            <flux:button
                                size="sm"
                                variant="outline"
                                @click="$wire.set('publishedAt', now())"
                            >Now</flux:button>
                        @endif
                    </div>
                </flux:field>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$flux.modal('mod-action-publish-{{ $this->modId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('mod-action-publish-{{ $this->modId }}').close(); $wire.publish()"
                    variant="primary"
                    size="sm"
                    icon="eye"
                    class="bg-green-600 hover:bg-green-700 text-white"
                >
                    {{ __('Publish') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="mod-action-unpublish-{{ $this->modId }}"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="eye-slash"
                        class="w-8 h-8 text-amber-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Unpublish Mod') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Hide from visitors') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    Are you sure you want to unpublish the "{{ $this->modName }}" mod?
                </flux:text>
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    This will remove the published date and prevent visitors on the site from discovering the mod.
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$flux.modal('mod-action-unpublish-{{ $this->modId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('mod-action-unpublish-{{ $this->modId }}').close(); $wire.unpublish()"
                    variant="primary"
                    size="sm"
                    icon="eye-slash"
                    class="bg-amber-600 hover:bg-amber-700 text-white"
                >
                    {{ __('Unpublish') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
