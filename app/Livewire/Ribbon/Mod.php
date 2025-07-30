<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Models\Mod as ModModel;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Mod extends Component
{
    /**
     * The mod ID.
     */
    #[Locked]
    public int $modId;

    /**
     * Whether the mod is disabled.
     */
    #[Locked]
    public bool $disabled;

    /**
     * The mod's published_at timestamp.
     */
    #[Locked]
    public ?string $publishedAt;

    /**
     * Whether the mod is featured.
     */
    #[Locked]
    public bool $featured;

    /**
     * Whether the ribbon is on the home page.
     */
    #[Locked]
    public bool $homepageFeatured = false;

    /**
     * Refresh the mod data when it's updated.
     */
    #[On('mod-updated.{modId}')]
    public function refreshMod(): void
    {
        $mod = ModModel::select('disabled', 'published_at', 'featured')->find($this->modId);
        if ($mod) {
            $hasChanges = false;
            $newPublishedAt = $mod->published_at?->toISOString();

            if ($this->disabled !== $mod->disabled) {
                $this->disabled = $mod->disabled;
                $hasChanges = true;
            }
            if ($this->publishedAt !== $newPublishedAt) {
                $this->publishedAt = $newPublishedAt;
                $hasChanges = true;
            }
            if ($this->featured !== $mod->featured) {
                $this->featured = $mod->featured;
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

        if ($this->featured && ! $this->homepageFeatured) {
            return ['color' => 'sky', 'label' => __('Featured!')];
        }

        return null;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.ribbon.mod', [
            'ribbonData' => $this->ribbonData,
        ]);
    }
}
