<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Models\Mod;
use App\Traits\Livewire\ModerationActionMenu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
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
    public function mount(int $modId, string $modName, bool $modFeatured, bool $modDisabled, bool $homepageFeatured = false): void
    {
        $this->modId = $modId;
        $this->modName = $modName;
        $this->modFeatured = $modFeatured;
        $this->modDisabled = $modDisabled;
        $this->homepageFeatured = $homepageFeatured;
        $this->routeName = request()->route()?->getName() ?? '';
    }

    /**
     * Get the mod model instance.
     */
    #[Computed(persist: true)]
    public function mod(): Mod
    {
        return Mod::query()->select(['id', 'name', 'featured', 'disabled', 'owner_id'])
            ->with('owner:id,name')
            ->findOrFail($this->modId);
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
        if (! $user) {
            return [];
        }

        return Cache::remember(
            sprintf('mod.%d.permissions.%s', $this->modId, $user->id),
            60, // Seconds
            fn (): array => [
                'viewActions' => Gate::allows('viewActions', $this->mod),
                'update' => Gate::allows('update', $this->mod),
                'delete' => Gate::allows('delete', $this->mod),
                'feature' => Gate::allows('feature', $this->mod),
                'unfeature' => Gate::allows('unfeature', $this->mod),
                'disable' => Gate::allows('disable', $this->mod),
                'enable' => Gate::allows('enable', $this->mod),
                'isModOrAdmin' => $user->isModOrAdmin(),
            ]
        );
    }

    /**
     * Features the mod.
     */
    public function feature(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $this->authorize('feature', $this->mod);

        Mod::query()->where('id', $this->modId)->update(['featured' => true]);

        $this->modFeatured = true;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.', $this->modId).auth()->id());

        $this->dispatch('mod-updated.'.$this->modId, featured: true);

        flash()->success('Mod successfully featured!');
    }

    /**
     * Unfeatures the mod.
     */
    public function unfeature(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $this->authorize('unfeature', $this->mod);

        // Update the database directly
        Mod::query()->where('id', $this->modId)->update(['featured' => false]);

        $this->modFeatured = false;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.', $this->modId).auth()->id());

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.'.$this->modId, featured: false);

        flash()->success('Mod successfully unfeatured!');
    }

    /**
     * Disables the mod.
     */
    public function disable(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $this->authorize('disable', $this->mod);

        // Update the database directly
        Mod::query()->where('id', $this->modId)->update(['disabled' => true]);

        $this->modDisabled = true;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.', $this->modId).auth()->id());

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.'.$this->modId, disabled: true);

        flash()->success('Mod successfully disabled!');
    }

    /**
     * Enables the mod.
     */
    public function enable(): void
    {
        $this->closeModal();
        $this->menuOpen = false;

        $this->authorize('enable', $this->mod);

        Mod::query()->where('id', $this->modId)->update(['disabled' => false]);

        $this->modDisabled = false;
        $this->clearPermissionCache(sprintf('mod.%d.permissions.', $this->modId).auth()->id());

        // Dispatch event to update ribbon
        $this->dispatch('mod-updated.'.$this->modId, disabled: false);

        flash()->success('Mod successfully enabled!');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.mod.action');
    }
}
