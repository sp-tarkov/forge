<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Models\Mod as ModModel;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read array<string, string>|null $ribbonData
 * @property-read bool $canSeeWarnings
 */
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
    public ?string $publishedAt = null;

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
     * Whether the mod is publicly visible.
     */
    #[Locked]
    public bool $publiclyVisible = false;

    /**
     * Refresh the mod data when it's updated.
     */
    #[On('mod-updated.{modId}')]
    public function refreshMod(): void
    {
        $mod = ModModel::query()
            ->with('versions.latestSptVersion')
            ->find($this->modId);

        if ($mod) {
            $hasChanges = false;
            $newPublishedAt = $mod->published_at?->toISOString();
            $newPubliclyVisible = $mod->isPubliclyVisible();

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

            if ($this->publiclyVisible !== $newPubliclyVisible) {
                $this->publiclyVisible = $newPubliclyVisible;
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

        $mod = ModModel::query()->find($this->modId);

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

        // Check if mod is not publicly visible due to missing or unpublished versions or invalid SPT compatibility
        // Only show this to privileged users who can see warnings
        if (! $this->publiclyVisible && $this->canSeeWarnings) {
            return ['color' => 'amber', 'label' => __('Unpublished')];
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
