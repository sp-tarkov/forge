<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion as AddonVersionModel;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read array<string, string>|null $ribbonData
 * @property-read bool $canSeeWarnings
 */
new class extends Component {
    /**
     * The addon version ID.
     */
    #[Locked]
    public int $versionId;

    /**
     * Whether the addon version is disabled.
     */
    #[Locked]
    public bool $disabled;

    /**
     * The addon version's published_at timestamp.
     */
    #[Locked]
    public ?string $publishedAt = null;

    /**
     * Refresh the addon version data when it's updated.
     */
    #[On('addon-version-updated.{versionId}')]
    public function refreshVersion(): void
    {
        $version = AddonVersionModel::query()->select('disabled', 'published_at')->find($this->versionId);
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

            if (!$hasChanges) {
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

        if (!$user) {
            return false;
        }

        $version = AddonVersionModel::query()->with('addon')->find($this->versionId);

        if (!$version) {
            return false;
        }

        // Check if addon relationship exists by checking the foreign key
        if (!$version->addon_id) {
            return false;
        }

        /** @var Addon|null $addon */
        $addon = $version->addon;
        if (!$addon) {
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
        // Only show visibility warnings to privileged users
        if (!$this->canSeeWarnings) {
            return null;
        }

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
};
?>

<div>
    @if ($this->ribbonData)
        <x-ribbon
            :color="$this->ribbonData['color']"
            :label="$this->ribbonData['label']"
        />
    @endif
</div>
