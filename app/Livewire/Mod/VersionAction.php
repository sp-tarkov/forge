<?php

declare(strict_types=1);

namespace App\Livewire\Mod;

use App\Models\ModVersion;
use App\Traits\Livewire\ModerationActionMenu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read ModVersion $version
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
     * Initialize the component with optimized data.
     */
    public function mount(int $versionId, int $modId, string $versionNumber, bool $versionDisabled): void
    {
        $this->versionId = $versionId;
        $this->modId = $modId;
        $this->versionNumber = $versionNumber;
        $this->versionDisabled = $versionDisabled;
    }

    /**
     * Get the version model instance.
     */
    #[Computed(persist: true)]
    public function version(): ModVersion
    {
        return ModVersion::query()->select(['id', 'version', 'disabled', 'mod_id'])
            ->with(['mod:id,name'])
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
            sprintf('mod_version.%d.permissions.%s', $this->versionId, $user->id),
            60, // Seconds
            fn (): array => [
                'viewActions' => Gate::allows('viewActions', [$this->version->mod, $this->version->mod]),
                'update' => Gate::allows('update', $this->version),
                'delete' => Gate::allows('delete', $this->version),
                'disable' => Gate::allows('disable', $this->version),
                'enable' => Gate::allows('enable', $this->version),
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

        ModVersion::query()->where('id', $this->versionId)->update(['disabled' => true]);

        $this->versionDisabled = true;
        $this->clearPermissionCache(sprintf('mod_version.%d.permissions.', $this->versionId).auth()->id());

        $this->dispatch('mod-version-updated.'.$this->versionId, disabled: true);

        flash()->success('Mod version successfully disabled!');

        $this->menuOpen = false;
    }

    /**
     * Enables the version.
     */
    public function enable(): void
    {
        $this->authorize('enable', $this->version);

        ModVersion::query()->where('id', $this->versionId)->update(['disabled' => false]);

        $this->versionDisabled = false;
        $this->clearPermissionCache(sprintf('mod_version.%d.permissions.', $this->versionId).auth()->id());

        $this->dispatch('mod-version-updated.'.$this->versionId, disabled: false);

        flash()->success('Mod version successfully enabled!');

        $this->menuOpen = false;
    }

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.mod.version-action');
    }
}
