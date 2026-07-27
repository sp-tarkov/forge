<?php

declare(strict_types=1);

use App\Models\Addon as AddonModel;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read array<string, string>|null $ribbonData
 * @property-read bool $canSeeWarnings
 * @property-read AddonModel|null $addon
 */
new class extends Component
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
     * Whether the addon is publicly visible. Null until it has been resolved.
     */
    #[Locked]
    public ?bool $publiclyVisible = null;

    /**
     * Refresh the addon data when it's updated.
     */
    #[On('addon-updated.{addonId}')]
    public function refreshAddon(): void
    {
        $addon = AddonModel::query()->find($this->addonId);

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
     * The addon this ribbon describes.
     */
    #[Computed]
    public function addon(): ?AddonModel
    {
        return AddonModel::query()->find($this->addonId);
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

        if ($user->isModOrAdmin()) {
            return true;
        }

        $addon = $this->addon;

        if (! $addon) {
            return false;
        }

        return $addon->isAuthorOrOwner($user);
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

        // Shows the unpublished warning to privileged users when the addon is not publicly visible
        if ($this->canSeeWarnings && ! $this->resolvePubliclyVisible()) {
            return ['color' => 'amber', 'label' => __('Unpublished')];
        }

        return null;
    }

    /**
     * Resolve whether the addon is publicly visible, storing the result on the component after the first lookup.
     */
    protected function resolvePubliclyVisible(): bool
    {
        return $this->publiclyVisible ??= (bool) $this->addon?->isPubliclyVisible();
    }
};
