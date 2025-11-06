<?php

declare(strict_types=1);

namespace App\Livewire\Addon;

use App\Models\AddonVersion;
use App\Traits\Livewire\ModerationActionMenu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read AddonVersion $version
 */
class VersionAction extends Component
{
    use ModerationActionMenu;

    /**
     * The version ID.
     */
    #[Locked]
    public int $versionId;

    /**
     * The addon ID.
     */
    #[Locked]
    public int $addonId;

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
     * Initialize the component with optimized data.
     */
    public function mount(int $versionId, int $addonId, string $versionNumber, bool $versionDisabled, bool $versionPublished): void
    {
        $this->versionId = $versionId;
        $this->addonId = $addonId;
        $this->versionNumber = $versionNumber;
        $this->versionDisabled = $versionDisabled;
        $this->versionPublished = $versionPublished;
    }

    /**
     * Get the version model instance.
     */
    #[Computed(persist: true)]
    public function version(): AddonVersion
    {
        return AddonVersion::query()->select(['id', 'version', 'disabled', 'published_at', 'addon_id'])
            ->with(['addon:id,name,owner_id', 'addon.owner:id', 'addon.authors:id'])
            ->findOrFail($this->versionId);
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
            sprintf('addon_version.%d.permissions.%s', $this->versionId, $user->id),
            60, // Seconds
            fn (): array => [
                'viewActions' => Gate::allows('viewActions', [$this->version->addon, $this->version->addon]),
                'update' => Gate::allows('update', $this->version),
                'delete' => Gate::allows('delete', $this->version),
                'disable' => Gate::allows('disable', $this->version),
                'enable' => Gate::allows('enable', $this->version),
                'publish' => Gate::allows('publish', $this->version),
                'unpublish' => Gate::allows('unpublish', $this->version),
                'isModOrAdmin' => $user->isModOrAdmin(),
            ]
        );
    }

    /**
     * Disables the version.
     */
    public function disable(): void
    {
        $this->authorize('disable', $this->version);

        AddonVersion::query()->where('id', $this->versionId)->update(['disabled' => true]);

        $this->versionDisabled = true;
        $this->clearPermissionCache(sprintf('addon_version.%d.permissions.', $this->versionId).auth()->id());

        $this->dispatch('addon-version-updated.'.$this->versionId, disabled: true);

        flash()->success('Addon version successfully disabled!');

        $this->menuOpen = false;
    }

    /**
     * Enables the version.
     */
    public function enable(): void
    {
        $this->authorize('enable', $this->version);

        AddonVersion::query()->where('id', $this->versionId)->update(['disabled' => false]);

        $this->versionDisabled = false;
        $this->clearPermissionCache(sprintf('addon_version.%d.permissions.', $this->versionId).auth()->id());

        $this->dispatch('addon-version-updated.'.$this->versionId, disabled: false);

        flash()->success('Addon version successfully enabled!');

        $this->menuOpen = false;
    }

    /**
     * Publishes the version with a specified date.
     */
    public function publish(): void
    {
        $this->authorize('publish', $this->version);

        $publishedDate = $this->publishedAt ? Date::parse($this->publishedAt) : now();

        AddonVersion::query()->where('id', $this->versionId)->update(['published_at' => $publishedDate]);

        $this->versionPublished = true;
        $this->clearPermissionCache(sprintf('addon_version.%d.permissions.', $this->versionId).auth()->id());

        $this->dispatch('addon-version-updated.'.$this->versionId, published: true);

        flash()->success('Addon version successfully published!');

        $this->menuOpen = false;
    }

    /**
     * Unpublishes the version.
     */
    public function unpublish(): void
    {
        $this->authorize('unpublish', $this->version);

        AddonVersion::query()->where('id', $this->versionId)->update(['published_at' => null]);

        $this->versionPublished = false;
        $this->clearPermissionCache(sprintf('addon_version.%d.permissions.', $this->versionId).auth()->id());

        $this->dispatch('addon-version-updated.'.$this->versionId, published: false);

        flash()->success('Addon version successfully unpublished!');

        $this->menuOpen = false;
    }

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.addon.version-action');
    }
}
