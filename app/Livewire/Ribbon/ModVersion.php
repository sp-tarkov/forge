<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Models\Mod;
use App\Models\ModVersion as ModVersionModel;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read array<string, string>|null $ribbonData
 */
class ModVersion extends Component
{
    /**
     * The mod version ID.
     */
    #[Locked]
    public int $versionId;

    /**
     * Whether the mod version is disabled.
     */
    #[Locked]
    public bool $disabled;

    /**
     * The mod version's published_at timestamp.
     */
    #[Locked]
    public ?string $publishedAt = null;

    /**
     * Refresh the mod version data when it's updated.
     */
    #[On('mod-version-updated.{versionId}')]
    public function refreshVersion(): void
    {
        $version = ModVersionModel::query()->select('disabled', 'published_at')->find($this->versionId);
        if ($version) {
            $hasChanges = false;
            $newPublishedAt = $version->published_at?->toISOString();

            if ($this->disabled !== $version->disabled) {
                $this->disabled = $version->disabled;
                $hasChanges = true;
            }

            if ($this->publishedAt !== $newPublishedAt) {
                $this->publishedAt = $newPublishedAt;
                $hasChanges = true;
            }

            if (! $hasChanges) {
                $this->skipRender();
            }
        } else {
            $this->skipRender();
        }
    }

    /**
     * Check if the current user can see visibility warnings.
     */
    #[Computed]
    public function canSeeWarnings(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $version = ModVersionModel::query()->with('mod')->find($this->versionId);

        if (! $version) {
            return false;
        }

        // Check if mod relationship exists by checking the foreign key
        if (! $version->mod_id) {
            return false;
        }

        /** @var Mod|null $mod */
        $mod = $version->mod;
        if (! $mod) {
            return false;
        }

        return $user->isModOrAdmin() || $mod->isAuthorOrOwner($user);
    }

    /**
     * Get the ribbon data with caching.
     *
     * @return array<string, string>|null
     */
    #[Computed]
    public function ribbonData(): ?array
    {
        if ($this->disabled) {
            return ['color' => 'red', 'label' => __('Disabled')];
        }

        if ($this->publishedAt === null) {
            return ['color' => 'amber', 'label' => __('Unpublished')];
        }

        $publishedAt = Date::parse($this->publishedAt);
        if ($publishedAt->isFuture()) {
            return ['color' => 'emerald', 'label' => __('Scheduled')];
        }

        return null;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.ribbon.mod-version', [
            'ribbonData' => $this->ribbonData,
        ]);
    }
}
