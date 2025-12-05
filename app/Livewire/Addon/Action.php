<?php

declare(strict_types=1);

namespace App\Livewire\Addon;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Traits\Livewire\ModerationActionMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Addon $addon
 */
class Action extends Component
{
    use ModerationActionMenu;

    /**
     * The addon ID.
     */
    #[Locked]
    public int $addonId;

    /**
     * Cached addon properties for quick access.
     */
    #[Locked]
    public string $addonName;

    /**
     * Whether the addon is disabled.
     */
    #[Locked]
    public bool $addonDisabled;

    /**
     * Whether the addon is published.
     */
    #[Locked]
    public bool $addonPublished;

    /**
     * Whether the addon is detached.
     */
    #[Locked]
    public bool $addonDetached;

    /**
     * The publish date for the addon.
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
     * Initialize the component with optimized data.
     */
    public function mount(int $addonId, string $addonName, bool $addonDisabled = false, bool $addonPublished = false, bool $addonDetached = false): void
    {
        $this->addonId = $addonId;
        $this->addonName = $addonName;
        $this->addonDisabled = $addonDisabled;
        $this->addonPublished = $addonPublished;
        $this->addonDetached = $addonDetached;
        $this->routeName = request()->route()?->getName() ?? '';
    }

    /**
     * Get the addon model instance.
     */
    #[Computed(persist: true)]
    public function addon(): Addon
    {
        return Addon::query()->withoutGlobalScopes()->select(['id', 'name', 'slug', 'disabled', 'published_at', 'owner_id', 'contains_ai_content', 'detached_at', 'mod_id'])
            ->with(['owner:id,name', 'additionalAuthors:id'])
            ->findOrFail($this->addonId);
    }

    /**
     * Determine if the moderation reason field should be shown.
     * Only show for mod/admin users who are NOT an owner or additional author.
     */
    #[Computed]
    public function showModerationReason(): bool
    {
        $user = Auth::user();

        return $user
            && $user->isModOrAdmin()
            && ! $this->addon->isAuthorOrOwner($user);
    }

    /**
     * Disables the addon.
     */
    public function disable(): void
    {
        $addon = $this->addon;

        $this->authorize('disable', $addon);

        $addon->disabled = true;
        $addon->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_DISABLE,
            $addon,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->addonDisabled = true;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.%s', $this->addonId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('addon-updated.'.$this->addonId, disabled: true);

        flash()->success('Addon successfully disabled!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Enables the addon.
     */
    public function enable(): void
    {
        $addon = $this->addon;

        $this->authorize('enable', $addon);

        $addon->disabled = false;
        $addon->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_ENABLE,
            $addon,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->addonDisabled = false;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.%s', $this->addonId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('addon-updated.'.$this->addonId, disabled: false);

        flash()->success('Addon successfully enabled!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Publishes the addon with a specified date.
     */
    public function publish(): void
    {
        $publishedDate = $this->publishedAt ? Date::parse($this->publishedAt) : now();
        $addon = $this->addon;

        $this->authorize('publish', $addon);

        $addon->published_at = $publishedDate;
        $addon->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_PUBLISH,
            $addon,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->addonPublished = true;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.%s', $this->addonId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('addon-updated.'.$this->addonId, published: true);

        flash()->success('Addon successfully published!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Unpublishes the addon.
     */
    public function unpublish(): void
    {
        $addon = $this->addon;

        $this->authorize('unpublish', $addon);

        $addon->published_at = null;
        $addon->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_UNPUBLISH,
            $addon,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->addonPublished = false;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.%s', $this->addonId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('addon-updated.'.$this->addonId, published: false);

        flash()->success('Addon successfully unpublished!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Attaches the addon to its parent mod.
     */
    public function attach(): void
    {
        $addon = $this->addon;

        $this->authorize('attach', $addon);

        $addon->detached_at = null;
        $addon->detached_by_user_id = null;
        $addon->save();

        // Update search index to reflect the attachment
        $addon->refresh()->searchable();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_ATTACH,
            $addon,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->addonDetached = false;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.%s', $this->addonId, (string) Auth::id()));

        flash()->success('Addon successfully attached!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Detaches the addon from its parent mod.
     */
    public function detach(): void
    {
        $addon = $this->addon;

        $this->authorize('detach', $addon);

        $addon->detached_at = now();
        $addon->detached_by_user_id = auth()->id();
        $addon->save();

        // Update search index to reflect the detachment
        $addon->refresh()->searchable();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_DETACH,
            $addon,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->addonDetached = true;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.%s', $this->addonId, (string) auth()->id()));

        flash()->success('Addon successfully detached!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.addon.action');
    }
}
