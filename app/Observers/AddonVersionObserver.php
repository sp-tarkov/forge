<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AddonVersion;
use App\Services\AddonVersionService;

final readonly class AddonVersionObserver
{
    public function __construct(private AddonVersionService $addonVersionService) {}

    /**
     * Handle the AddonVersion "created" event.
     */
    public function created(AddonVersion $addonVersion): void
    {
        $this->addonVersionService->resolve($addonVersion);

        $this->updateRelatedAddon($addonVersion);
    }

    /**
     * Handle the AddonVersion "updated" event.
     */
    public function updated(AddonVersion $addonVersion): void
    {
        // Only re-resolve if the constraint changed
        if ($addonVersion->wasChanged('mod_version_constraint')) {
            $this->addonVersionService->resolve($addonVersion);
        }

        $this->updateRelatedAddon($addonVersion);
    }

    /**
     * Handle the AddonVersion "deleted" event.
     */
    public function deleted(AddonVersion $addonVersion): void
    {
        $this->updateRelatedAddon($addonVersion);
    }

    /**
     * Update properties on the related Addon.
     */
    private function updateRelatedAddon(AddonVersion $addonVersion): void
    {
        $addonVersion->addon?->calculateDownloads();
    }
}
