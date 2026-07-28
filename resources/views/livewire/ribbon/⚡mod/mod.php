<?php

declare(strict_types=1);

use App\Models\Mod as ModModel;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read array<string, string>|null $ribbonData
 * @property-read ModModel|null $mod
 */
new class extends Component
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
     * Whether the mod is publicly visible. Null until it has been resolved.
     */
    #[Locked]
    public ?bool $publiclyVisible = null;

    /**
     * Whether the current user can see visibility warnings. Null until it has been resolved.
     */
    #[Locked]
    public ?bool $canSeeWarnings = null;

    /**
     * Refresh the mod data when it's updated.
     */
    #[On('mod-updated.{modId}')]
    public function refreshMod(): void
    {
        $mod = ModModel::query()->find($this->modId);

        if ($mod) {
            $hasChanges = false;
            $this->canSeeWarnings = null;
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
     * The mod this ribbon describes.
     */
    #[Computed]
    public function mod(): ?ModModel
    {
        return ModModel::query()->find($this->modId);
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

        // Shows the unpublished warning to privileged users when the mod is not publicly visible
        if ($this->resolveCanSeeWarnings() && ! $this->resolvePubliclyVisible()) {
            return ['color' => 'amber', 'label' => __('Unpublished')];
        }

        if ($this->featured && ! $this->homepageFeatured) {
            return ['color' => 'sky', 'label' => __('Featured!')];
        }

        return null;
    }

    /**
     * Resolve whether the current user can see visibility warnings, storing the result on the component after the
     * first lookup.
     */
    protected function resolveCanSeeWarnings(): bool
    {
        return $this->canSeeWarnings ??= $this->computeCanSeeWarnings();
    }

    /**
     * Check if the current user can see visibility warnings.
     */
    protected function computeCanSeeWarnings(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isModOrAdmin()) {
            return true;
        }

        $mod = $this->mod;

        if (! $mod) {
            return false;
        }

        return $mod->isAuthorOrOwner($user);
    }

    /**
     * Resolve whether the mod is publicly visible, storing the result on the component after the first lookup.
     */
    protected function resolvePubliclyVisible(): bool
    {
        return $this->publiclyVisible ??= (bool) $this->mod?->isPubliclyVisible();
    }
};
