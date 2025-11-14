<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Mod;
use App\Traits\Livewire\ModerationActionMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Mod $mod
 */
class Action extends Component
{
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
        return Mod::query()->withoutGlobalScopes()->select(['id', 'name', 'slug', 'featured', 'disabled', 'published_at', 'owner_id', 'contains_ai_content'])
            ->with('owner:id,name')
            ->findOrFail($this->modId);
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

        Track::event(TrackingEventType::MOD_FEATURE, $mod);

        $this->modFeatured = true;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        $this->dispatch('mod-updated.'.$this->modId, featured: true);

        flash()->success('Mod successfully featured!');

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

        Track::event(TrackingEventType::MOD_UNFEATURE, $mod);

        $this->modFeatured = false;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.'.$this->modId, featured: false);

        flash()->success('Mod successfully unfeatured!');

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

        Track::event(TrackingEventType::MOD_DISABLE, $mod);

        $this->modDisabled = true;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.'.$this->modId, disabled: true);

        flash()->success('Mod successfully disabled!');

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

        Track::event(TrackingEventType::MOD_ENABLE, $mod);

        $this->modDisabled = false;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.'.$this->modId, disabled: false);

        flash()->success('Mod successfully enabled!');

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

        Track::event(TrackingEventType::MOD_PUBLISH, $mod);

        $this->modPublished = true;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.'.$this->modId, published: true);

        flash()->success('Mod successfully published!');

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

        Track::event(TrackingEventType::MOD_UNPUBLISH, $mod);

        $this->modPublished = false;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.%s', $this->modId, (string) Auth::id()));

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.'.$this->modId, published: false);

        flash()->success('Mod successfully unpublished!');

        $this->menuOpen = false;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.mod.action');
    }
}
