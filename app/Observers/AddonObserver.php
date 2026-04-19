<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Addon;
use App\Models\ModListItem;
use App\Services\AddonVersionService;
use Illuminate\Support\Facades\Storage;

final readonly class AddonObserver
{
    public function __construct(private AddonVersionService $addonVersionService) {}

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
            $disk = config()->string('filesystems.asset_upload', 'public');
            if (Storage::disk($disk)->exists($addon->thumbnail)) {
                Storage::disk($disk)->delete($addon->thumbnail);
            }
        }

        // Polymorphic relations are not cascaded by the DB; remove list references here.
        ModListItem::query()
            ->where('listable_type', Addon::class)
            ->where('listable_id', $addon->id)
            ->delete();
    }
}
