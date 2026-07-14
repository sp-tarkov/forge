<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\VerificationTrigger;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\VerificationResult;
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

        $this->dispatchVerification($addonVersion);
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
        /** @var Addon|null $addon */
        $addon = $addonVersion->addon;
        $addon?->calculateDownloads();
    }

    /**
     * Dispatch a file verification for a newly uploaded version when automatic verification is enabled and the
     * version has a downloadable link and is not disabled.
     */
    private function dispatchVerification(AddonVersion $addonVersion): void
    {
        if (! config()->boolean('verification.auto_enabled')) {
            return;
        }

        if ($addonVersion->link === '' || $addonVersion->disabled) {
            return;
        }

        VerificationResult::dispatchFor($addonVersion, VerificationTrigger::Upload);
    }
}
