<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\ModVersion;
use App\Traits\Livewire\ModerationActionMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read ModVersion $version
 */
new class extends Component {
    use ModerationActionMenu;

    /**
     * The version ID.
     */
    #[Locked]
    public int $versionId;

    /**
     * The mod ID.
     */
    #[Locked]
    public int $modId;

    /**
     * Cached version properties for quick access.
     */
    #[Locked]
    public string $versionNumber;

    /**
     * Whether the version is disabled.
     */
    #[Locked]
    public bool $versionDisabled;

    /**
     * Whether the version is published.
     */
    #[Locked]
    public bool $versionPublished;

    /**
     * The publish date for the version.
     */
    public ?string $publishedAt = null;

    /**
     * The reason for moderation actions.
     */
    public string $moderationReason = '';

    /**
     * Initialize the component with optimized data.
     */
    public function mount(int $versionId, int $modId, string $versionNumber, bool $versionDisabled, bool $versionPublished): void
    {
        $this->versionId = $versionId;
        $this->modId = $modId;
        $this->versionNumber = $versionNumber;
        $this->versionDisabled = $versionDisabled;
        $this->versionPublished = $versionPublished;
    }

    /**
     * Get the version model instance.
     */
    #[Computed(persist: true)]
    public function version(): ModVersion
    {
        return ModVersion::query()
            ->select(['id', 'version', 'disabled', 'published_at', 'mod_id'])
            ->with(['mod:id,name,owner_id', 'mod.owner:id', 'mod.additionalAuthors:id'])
            ->findOrFail($this->versionId);
    }

    /**
     * Determine if the moderation reason field should be shown.
     * Only show for mod/admin users who are NOT an owner or additional author.
     */
    #[Computed]
    public function showModerationReason(): bool
    {
        $user = Auth::user();

        return $user && $user->isModOrAdmin() && !$this->version->mod->isAuthorOrOwner($user);
    }

    /**
     * Get cached permissions for the current user.
     *
     * @return array<string, bool>
     */
    #[Computed(persist: true)]
    public function permissions(): array
    {
        $user = auth()->user();
        if (!$user) {
            return [];
        }

        return Cache::remember(
            sprintf('mod_version.%d.permissions.%s', $this->versionId, $user->id),
            60, // Seconds
            fn(): array => [
                'viewActions' => Gate::allows('viewActions', [$this->version->mod, $this->version->mod]),
                'update' => Gate::allows('update', $this->version),
                'delete' => Gate::allows('delete', $this->version),
                'disable' => Gate::allows('disable', $this->version),
                'enable' => Gate::allows('enable', $this->version),
                'publish' => Gate::allows('publish', $this->version),
                'unpublish' => Gate::allows('unpublish', $this->version),
                'isModOrAdmin' => $user->isModOrAdmin(),
            ],
        );
    }

    /**
     * Disables the version.
     */
    public function disable(): void
    {
        $version = $this->version;

        $this->authorize('disable', $version);

        $version->disabled = true;
        $version->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && !$version->mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(TrackingEventType::VERSION_DISABLE, $version, isModerationAction: $isModerationAction, reason: $isModerationAction ? ($this->moderationReason ?: null) : null);

        $this->versionDisabled = true;
        $this->clearPermissionCache(sprintf('mod_version.%d.permissions.%s', $this->versionId, (string) Auth::id()));

        $this->dispatch('mod-version-updated.' . $this->versionId, disabled: true);

        flash()->success('Mod version successfully disabled!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Enables the version.
     */
    public function enable(): void
    {
        $version = $this->version;

        $this->authorize('enable', $version);

        $version->disabled = false;
        $version->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && !$version->mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(TrackingEventType::VERSION_ENABLE, $version, isModerationAction: $isModerationAction, reason: $isModerationAction ? ($this->moderationReason ?: null) : null);

        $this->versionDisabled = false;
        $this->clearPermissionCache(sprintf('mod_version.%d.permissions.%s', $this->versionId, (string) Auth::id()));

        $this->dispatch('mod-version-updated.' . $this->versionId, disabled: false);

        flash()->success('Mod version successfully enabled!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Publishes the version with a specified date.
     */
    public function publish(): void
    {
        $publishedDate = $this->publishedAt ? Date::parse($this->publishedAt) : now();
        $version = $this->version;

        $this->authorize('publish', $version);

        $version->published_at = $publishedDate;
        $version->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && !$version->mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(TrackingEventType::VERSION_PUBLISH, $version, isModerationAction: $isModerationAction, reason: $isModerationAction ? ($this->moderationReason ?: null) : null);

        $this->versionPublished = true;
        $this->clearPermissionCache(sprintf('mod_version.%d.permissions.%s', $this->versionId, (string) Auth::id()));

        $this->dispatch('mod-version-updated.' . $this->versionId, published: true);

        flash()->success('Mod version successfully published!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Unpublishes the version.
     */
    public function unpublish(): void
    {
        $version = $this->version;

        $this->authorize('unpublish', $version);

        $version->published_at = null;
        $version->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && !$version->mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(TrackingEventType::VERSION_UNPUBLISH, $version, isModerationAction: $isModerationAction, reason: $isModerationAction ? ($this->moderationReason ?: null) : null);

        $this->versionPublished = false;
        $this->clearPermissionCache(sprintf('mod_version.%d.permissions.%s', $this->versionId, (string) Auth::id()));

        $this->dispatch('mod-version-updated.' . $this->versionId, published: false);

        flash()->success('Mod version successfully unpublished!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }
};
?>

<div x-data="{
    menuLoaded: $wire.entangle('menuOpen').live
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
                                href="{{ route('mod.version.edit', [$this->modId, $this->versionId]) }}"
                                icon="pencil"
                            >Edit Version</flux:menu.item>
                        @endif
                        @if ($this->versionPublished)
                            @if ($this->permissions['unpublish'] ?? false)
                                <flux:modal.trigger name="mod-version-action-unpublish-{{ $this->versionId }}">
                                    <flux:menu.item icon="eye-slash">Unpublish Version</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @else
                            @if ($this->permissions['publish'] ?? false)
                                <flux:modal.trigger name="mod-version-action-publish-{{ $this->versionId }}">
                                    <flux:menu.item icon="eye">Publish Version</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @endif
                        @if ($this->permissions['delete'] ?? false)
                            <flux:modal.trigger name="mod-version-action-delete-{{ $this->versionId }}">
                                <flux:menu.item
                                    icon="trash"
                                    variant="danger"
                                >Delete Version</flux:menu.item>
                            </flux:modal.trigger>
                        @endif
                    </flux:menu.group>
                @endif
                @if ($this->permissions['isModOrAdmin'] ?? false)
                    <flux:menu.group heading="{{ auth()->user()->role->name }} Actions">
                        @if ($this->permissions['update'] ?? false)
                            <flux:menu.item
                                href="{{ route('mod.version.edit', [$this->modId, $this->versionId]) }}"
                                icon="pencil"
                            >Edit Version</flux:menu.item>
                        @endif
                        @if ($this->versionDisabled)
                            @if ($this->permissions['enable'] ?? false)
                                <flux:modal.trigger name="mod-version-action-enable-{{ $this->versionId }}">
                                    <flux:menu.item icon="eye">Enable Version</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @else
                            @if ($this->permissions['disable'] ?? false)
                                <flux:modal.trigger name="mod-version-action-disable-{{ $this->versionId }}">
                                    <flux:menu.item icon="eye-slash">Disable Version</flux:menu.item>
                                </flux:modal.trigger>
                            @endif
                        @endif
                        @if ($this->permissions['delete'] ?? false)
                            <flux:modal.trigger name="mod-version-action-delete-{{ $this->versionId }}">
                                <flux:menu.item
                                    icon="trash"
                                    variant="danger"
                                >Delete Version</flux:menu.item>
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
        name="mod-version-action-enable-{{ $this->versionId }}"
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
                    Are you sure you want to enable version "{{ $this->versionNumber }}" of the
                    "{{ $this->version->mod->name ?? 'Unknown' }}" mod?
                </flux:text>
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    This will allow visitors to view and download the version.
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
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-version-action-enable-{{ $this->versionId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('mod-version-action-enable-{{ $this->versionId }}').close(); $wire.enable()"
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
        name="mod-version-action-disable-{{ $this->versionId }}"
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
                    Are you sure you want to disable version "{{ $this->versionNumber }}" of the
                    "{{ $this->version->mod->name ?? 'Unknown' }}" mod?
                </flux:text>
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    This will prevent visitors from viewing or downloading the version.
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
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-version-action-disable-{{ $this->versionId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('mod-version-action-disable-{{ $this->versionId }}').close(); $wire.disable()"
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
        name="mod-version-action-delete-{{ $this->versionId }}"
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
                    Are you sure you want to delete version "{{ $this->versionNumber }}" of the
                    "{{ $this->version->mod->name ?? 'Unknown' }}" mod?
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
                                The mod version will be permanently deleted, along with all data tied directly to the
                                version. If this isn't necessary you should simply disable the version instead.
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
                        x-on:click="$wire.moderationReason = ''; $flux.modal('mod-version-action-delete-{{ $this->versionId }}').close()"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        x-on:click="$flux.modal('mod-version-action-delete-{{ $this->versionId }}').close(); $wire.$parent.deleteModVersion({{ $this->versionId }}, $wire.moderationReason)"
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
        name="mod-version-action-publish-{{ $this->versionId }}"
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
                            {{ __('Publish Mod Version') }}
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
                    Select the date and time version "{{ $this->versionNumber }}" of the
                    "{{ $this->version->mod->name ?? 'Unknown' }}" mod will be published.
                </flux:text>

                <flux:field>
                    <flux:label>{{ __('Publish Date') }}</flux:label>
                    <flux:description>
                        {!! __('Select the date and time the version will be published.') !!}
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
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-version-action-publish-{{ $this->versionId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('mod-version-action-publish-{{ $this->versionId }}').close(); $wire.publish()"
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
        name="mod-version-action-unpublish-{{ $this->versionId }}"
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
                            {{ __('Unpublish Mod Version') }}
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
                    Are you sure you want to unpublish version "{{ $this->versionNumber }}" of the
                    "{{ $this->version->mod->name ?? 'Unknown' }}" mod?
                </flux:text>
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    This will remove the published date and prevent visitors from viewing or downloading the version.
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
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-version-action-unpublish-{{ $this->versionId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    x-on:click="$flux.modal('mod-version-action-unpublish-{{ $this->versionId }}').close(); $wire.unpublish()"
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
