<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Models\ModVersion as ModVersionModel;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

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
     * Get the ribbon data with caching.
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

        $publishedAt = Carbon::parse($this->publishedAt);
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
