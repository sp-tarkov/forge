<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AddonVersion;
use App\Services\AddonVersionService;

class AddonVersionObserver
{
    public function __construct(protected AddonVersionService $addonVersionService) {}

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
    protected function updateRelatedAddon(AddonVersion $addonVersion): void
    {
        if ($addonVersion->addon()->exists()) {
            $addon = $addonVersion->addon;
            $addon->calculateDownloads();
        }
    }
}
