<?php

declare(strict_types=1);

namespace App\Livewire\Addon;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Traits\Livewire\ModerationActionMenu;
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
            ->with('owner:id,name')
            ->findOrFail($this->addonId);
    }

    /**
     * Disables the addon.
     */
    public function disable(): void
    {
        $this->authorize('disable', $this->addon);

        // Update the database directly
        Addon::query()->where('id', $this->addonId)->update(['disabled' => true]);

        Track::event(TrackingEventType::ADDON_DISABLE, $this->addon);

        $this->addonDisabled = true;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.', $this->addonId).auth()->id());

        // Dispatch event to update ribbon
        $this->dispatch('addon-updated.'.$this->addonId, disabled: true);

        flash()->success('Addon successfully disabled!');

        $this->menuOpen = false;
    }

    /**
     * Enables the addon.
     */
    public function enable(): void
    {
        $this->authorize('enable', $this->addon);

        Addon::query()->where('id', $this->addonId)->update(['disabled' => false]);

        Track::event(TrackingEventType::ADDON_ENABLE, $this->addon);

        $this->addonDisabled = false;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.', $this->addonId).auth()->id());

        // Dispatch event to update ribbon
        $this->dispatch('addon-updated.'.$this->addonId, disabled: false);

        flash()->success('Addon successfully enabled!');

        $this->menuOpen = false;
    }

    /**
     * Publishes the addon with a specified date.
     */
    public function publish(): void
    {
        $this->authorize('publish', $this->addon);

        $publishedDate = $this->publishedAt ? Date::parse($this->publishedAt) : now();

        Addon::query()->where('id', $this->addonId)->update(['published_at' => $publishedDate]);

        Track::event(TrackingEventType::ADDON_PUBLISH, $this->addon);

        $this->addonPublished = true;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.', $this->addonId).auth()->id());

        // Dispatch event to update ribbon
        $this->dispatch('addon-updated.'.$this->addonId, published: true);

        flash()->success('Addon successfully published!');

        $this->menuOpen = false;
    }

    /**
     * Unpublishes the addon.
     */
    public function unpublish(): void
    {
        $this->authorize('unpublish', $this->addon);

        Addon::query()->where('id', $this->addonId)->update(['published_at' => null]);

        Track::event(TrackingEventType::ADDON_UNPUBLISH, $this->addon);

        $this->addonPublished = false;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.', $this->addonId).auth()->id());

        // Dispatch event to update ribbon
        $this->dispatch('addon-updated.'.$this->addonId, published: false);

        flash()->success('Addon successfully unpublished!');

        $this->menuOpen = false;
    }

    /**
     * Attaches the addon to its parent mod.
     */
    public function attach(): void
    {
        $this->authorize('attach', $this->addon);

        Addon::query()->where('id', $this->addonId)->update([
            'detached_at' => null,
            'detached_by_user_id' => null,
        ]);

        // Update search index to reflect the attachment
        $this->addon->refresh()->searchable();

        Track::event(TrackingEventType::ADDON_ATTACH, $this->addon);

        $this->addonDetached = false;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.', $this->addonId).auth()->id());

        flash()->success('Addon successfully attached!');

        $this->menuOpen = false;
    }

    /**
     * Detaches the addon from its parent mod.
     */
    public function detach(): void
    {
        $this->authorize('detach', $this->addon);

        Addon::query()->where('id', $this->addonId)->update([
            'detached_at' => now(),
            'detached_by_user_id' => auth()->id(),
        ]);

        // Update search index to reflect the detachment
        $this->addon->refresh()->searchable();

        Track::event(TrackingEventType::ADDON_DETACH, $this->addon);

        $this->addonDetached = true;
        $this->clearPermissionCache(sprintf('addon.%d.permissions.', $this->addonId).auth()->id());

        flash()->success('Addon successfully detached!');

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
