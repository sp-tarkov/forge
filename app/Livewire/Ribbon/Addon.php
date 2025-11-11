<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Models\Addon as AddonModel;
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
class Addon extends Component
{
    /**
     * The addon ID.
     */
    #[Locked]
    public int $addonId;

    /**
     * Whether the addon is disabled.
     */
    #[Locked]
    public bool $disabled;

    /**
     * The addon's published_at timestamp.
     */
    #[Locked]
    public ?string $publishedAt = null;

    /**
     * Whether the addon is publicly visible.
     */
    #[Locked]
    public bool $publiclyVisible = false;

    /**
     * Refresh the addon data when it's updated.
     */
    #[On('addon-updated.{addonId}')]
    public function refreshAddon(): void
    {
        $addon = AddonModel::query()
            ->with('versions')
            ->find($this->addonId);

        if ($addon) {
            $hasChanges = false;
            $newPublishedAt = $addon->published_at?->toISOString();
            $newPubliclyVisible = $addon->isPubliclyVisible();

            if ($this->disabled !== $addon->disabled) {
                $this->disabled = $addon->disabled;
                $hasChanges = true;
            }

            if ($this->publishedAt !== $newPublishedAt) {
                $this->publishedAt = $newPublishedAt;
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

        $addon = AddonModel::query()->find($this->addonId);

        if (! $addon) {
            return false;
        }

        return $user->isModOrAdmin() || $addon->isAuthorOrOwner($user);
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

        // Check if addon is not publicly visible due to missing or unpublished versions
        // Only show this to privileged users who can see warnings
        if (! $this->publiclyVisible && $this->canSeeWarnings) {
            return ['color' => 'amber', 'label' => __('Unpublished')];
        }

        return null;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.ribbon.addon', [
            'ribbonData' => $this->ribbonData,
        ]);
    }
}
