<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Addon;
use App\Services\AddonVersionService;
use Illuminate\Support\Facades\Storage;

class AddonObserver
{
    public function __construct(protected AddonVersionService $addonVersionService) {}

    /**
     * Handle the Addon "updated" event.
     */
    public function updated(Addon $addon): void
    {
        // If the mod_id changed (addon was detached or attached), re-resolve all addon versions
        if ($addon->wasChanged('mod_id')) {
            foreach ($addon->versions as $version) {
                $this->addonVersionService->resolve($version);
            }
        }
    }

    /**
     * Handle the Addon "deleting" event.
     */
    public function deleting(Addon $addon): void
    {
        // Remove the addon's thumbnail image from storage if it exists.
        if ($addon->thumbnail) {
            $disk = config('filesystems.asset_upload', 'public');
            if (Storage::disk($disk)->exists($addon->thumbnail)) {
                Storage::disk($disk)->delete($addon->thumbnail);
            }
        }
    }
}
