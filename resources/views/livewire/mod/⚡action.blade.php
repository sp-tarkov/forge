<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Mod;
use App\Traits\Livewire\ModerationActionMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Mod $mod
 */
new class extends Component {
    use ModerationActionMenu;

    /**
     * The mod ID.
     */
    #[Locked]
    public int $modId;

    /**
     * Cached mod properties for quick access.
     */
    #[Locked]
    public string $modName;

    /**
     * Whether the mod is featured.
     */
    #[Locked]
    public bool $modFeatured;

    /**
     * Whether the mod is disabled.
     */
    #[Locked]
    public bool $modDisabled;

    /**
     * Whether the mod is published.
     */
    #[Locked]
    public bool $modPublished;

    /**
     * The publish date for the mod.
     */
    public ?string $publishedAt = null;

    /**
     * The reason for moderation actions.
     */
    public string $moderationReason = '';

    /**
     * The route name of the current page on initialization of the component.
     */
    #[Locked]
    public string $routeName = '';

    /**
     * Is this mod card is in the featured section of the homepage. Changing the featured state in this context requires
     * processing the action through the homepage component so the listing can be updated.
     */
    #[Locked]
    public bool $homepageFeatured = false;

    /**
     * Initialize the component with optimized data.
     */
    public function mount(int $modId, string $modName, bool $modFeatured, bool $modDisabled, bool $modPublished, bool $homepageFeatured = false): void
    {
        $this->modId = $modId;
        $this->modName = $modName;
        $this->modFeatured = $modFeatured;
        $this->modDisabled = $modDisabled;
        $this->modPublished = $modPublished;
        $this->homepageFeatured = $homepageFeatured;
        $this->routeName = request()->route()?->getName() ?? '';
    }

    /**
     * Get the mod model instance.
     */
    #[Computed(persist: true)]
    public function mod(): Mod
    {
        return Mod::query()
            ->withoutGlobalScopes()
            ->select(['id', 'name', 'slug', 'featured', 'disabled', 'published_at', 'owner_id', 'contains_ai_content'])
            ->with(['owner:id,name', 'additionalAuthors:id'])
            ->findOrFail($this->modId);
    }

    /**
     * Determine if the moderation reason field should be shown.
     * Only show for mod/admin users who are NOT an owner or additional author.
     */
    #[Computed]
    public function showModerationReason(): bool
    {
        $user = Auth::user();

        return $user && $user->isModOrAdmin() && !$this->mod->isAuthorOrOwner($user);
    }

    /**
     * Features the mod.
     */
    public function feature(): void
    {
        $mod = $this->mod;

        $this->authorize('feature', $mod);

        $mod->featured = true;
        $mod->save();

        Track::eventSync(TrackingEventType::MOD_FEATURE, $mod, isModerationAction: true, reason: $this->moderationReason ?: null);

        $this->modFeatured = true;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        $this->dispatch('mod-updated.' . $this->modId, featured: true);

        flash()->success('Mod successfully featured!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Unfeatures the mod.
     */
    public function unfeature(): void
    {
        $mod = $this->mod;

        $this->authorize('unfeature', $mod);

        $mod->featured = false;
        $mod->save();

        Track::eventSync(TrackingEventType::MOD_UNFEATURE, $mod, isModerationAction: true, reason: $this->moderationReason ?: null);

        $this->modFeatured = false;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.' . $this->modId, featured: false);

        flash()->success('Mod successfully unfeatured!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Disables the mod.
     */
    public function disable(): void
    {
        $mod = $this->mod;

        $this->authorize('disable', $mod);

        $mod->disabled = true;
        $mod->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && !$mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(TrackingEventType::MOD_DISABLE, $mod, isModerationAction: $isModerationAction, reason: $isModerationAction ? ($this->moderationReason ?: null) : null);

        $this->modDisabled = true;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.' . $this->modId, disabled: true);

        flash()->success('Mod successfully disabled!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Enables the mod.
     */
    public function enable(): void
    {
        $mod = $this->mod;

        $this->authorize('enable', $mod);

        $mod->disabled = false;
        $mod->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && !$mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(TrackingEventType::MOD_ENABLE, $mod, isModerationAction: $isModerationAction, reason: $isModerationAction ? ($this->moderationReason ?: null) : null);

        $this->modDisabled = false;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.' . $this->modId, disabled: false);

        flash()->success('Mod successfully enabled!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Publishes the mod with a specified date.
     */
    public function publish(): void
    {
        $publishedDate = $this->publishedAt ? Date::parse($this->publishedAt) : now();
        $mod = $this->mod;

        $this->authorize('publish', $mod);

        $mod->published_at = $publishedDate;
        $mod->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && !$mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(TrackingEventType::MOD_PUBLISH, $mod, isModerationAction: $isModerationAction, reason: $isModerationAction ? ($this->moderationReason ?: null) : null);

        $this->modPublished = true;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.' . $this->modId, published: true);

        flash()->success('Mod successfully published!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Unpublishes the mod.
     */
    public function unpublish(): void
    {
        $mod = $this->mod;

        $this->authorize('unpublish', $mod);

        $mod->published_at = null;
        $mod->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && !$mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(TrackingEventType::MOD_UNPUBLISH, $mod, isModerationAction: $isModerationAction, reason: $isModerationAction ? ($this->moderationReason ?: null) : null);

        $this->modPublished = false;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.' . $this->modId, published: false);

        flash()->success('Mod successfully unpublished!');

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
                @cachedCan('viewActions', $this->mod)
                <flux:menu.group heading="Author Actions">
                    @cachedCan('update', $this->mod)
                    <flux:menu.item
                        href="{{ route('mod.edit', $this->modId) }}"
                        icon:trailing="pencil-square"
                    >Edit Mod</flux:menu.item>
                    @endcachedCan
                    @if ($this->modPublished)
                        @cachedCan('unpublish', $this->mod)
                        <flux:modal.trigger name="mod-action-unpublish-{{ $this->modId }}">
                            <flux:menu.item icon:trailing="eye-slash">Unpublish Mod</flux:menu.item>
                        </flux:modal.trigger>
                        @endcachedCan
                    @else
                        @cachedCan('publish', $this->mod)
                        <flux:modal.trigger name="mod-action-publish-{{ $this->modId }}">
                            <flux:menu.item icon:trailing="eye">Publish Mod</flux:menu.item>
                        </flux:modal.trigger>
                    @endif
            @endif
            @cachedCan('delete', $this->mod)
            <flux:modal.trigger name="mod-action-delete-{{ $this->modId }}">
                <flux:menu.item
                    icon:trailing="trash"
                    variant="danger"
                >Delete Mod</flux:menu.item>
            </flux:modal.trigger>
            @endcachedCan
            </flux:menu.group>
            @endcachedCan
            @if (auth()->user()?->isModOrAdmin())
                <flux:menu.group heading="{{ auth()->user()->role->name }} Actions">
                    @cachedCan('update', $this->mod)
                    <flux:menu.item
                        href="{{ route('mod.edit', $this->modId) }}"
                        icon:trailing="pencil-square"
                    >Edit Mod</flux:menu.item>
                    @endcachedCan
                    @if ($this->modFeatured)
                        @cachedCan('unfeature', $this->mod)
                        <flux:modal.trigger name="mod-action-unfeature-{{ $this->modId }}">
                            <flux:menu.item icon:trailing="arrow-trending-down">Remove Featured</flux:menu.item>
                        </flux:modal.trigger>
                        @endcachedCan
                    @else
                        @cachedCan('feature', $this->mod)
                        <flux:modal.trigger name="mod-action-feature-{{ $this->modId }}">
                            <flux:menu.item icon:trailing="sparkles">Feature Mod</flux:menu.item>
                        </flux:modal.trigger>
                        @endcachedCan
                    @endif
                    @if ($this->modDisabled)
                        @cachedCan('enable', $this->mod)
                        <flux:modal.trigger name="mod-action-enable-{{ $this->modId }}">
                            <flux:menu.item icon:trailing="eye">Enable Mod</flux:menu.item>
                        </flux:modal.trigger>
                        @endcachedCan
                    @else
                        @cachedCan('disable', $this->mod)
                        <flux:modal.trigger name="mod-action-disable-{{ $this->modId }}">
                            <flux:menu.item icon:trailing="eye-slash">Disable Mod</flux:menu.item>
                        </flux:modal.trigger>
                    @endif
            @endif
            @cachedCan('delete', $this->mod)
                <flux:modal.trigger name="mod-action-delete-{{ $this->modId }}">
                    <flux:menu.item
                        icon:trailing="trash"
                        variant="danger"
                    >Delete Mod</flux:menu.item>
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
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-action-unfeature-{{ $this->modId }}').close()"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                {{-- In the homepage featured section, the parent must handle the action so the listing can be updated --}}
                <flux:button
                    x-on:click="$flux.modal('mod-action-unfeature-{{ $this->modId }}').close(); {{ $homepageFeatured ? '$wire.$parent.unfeatureMod(' . $this->modId . ', $wire.moderationReason)' : '$wire.unfeature()' }}"
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
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-action-feature-{{ $this->modId }}').close()"
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
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-action-disable-{{ $this->modId }}').close()"
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
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-action-enable-{{ $this->modId }}').close()"
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
                        x-on:click="$wire.moderationReason = ''; $flux.modal('mod-action-delete-{{ $this->modId }}').close()"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        x-on:click="$flux.modal('mod-action-delete-{{ $this->modId }}').close(); $wire.$parent.deleteMod({{ $this->modId }}, '{{ $routeName }}', $wire.moderationReason)"
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
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-action-publish-{{ $this->modId }}').close()"
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
                    x-on:click="$wire.moderationReason = ''; $flux.modal('mod-action-unpublish-{{ $this->modId }}').close()"
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
