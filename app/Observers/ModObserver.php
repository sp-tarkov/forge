<?php

declare(strict_types=1);

namespace App\Observers;

use App\Contracts\DependencyResolver;
use App\Models\Mod;
use App\Models\ModListItem;
use App\Models\SptVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final readonly class ModObserver
{
    public function __construct(private DependencyResolver $dependencyVersionService) {}

    /**
     * Handle the Mod "saved" event.
     */
    public function saved(Mod $mod): void
    {
        foreach ($mod->versions as $modVersion) {
            $this->dependencyVersionService->resolve($modVersion);
        }

        $this->updateRelatedSptVersions($mod);
    }

    /**
     * Handle the Mod "deleted" event.
     */
    public function deleted(Mod $mod): void
    {
        $this->updateRelatedSptVersions($mod);
    }

    /**
     * Handle the Mod "deleting" event.
     */
    public function deleting(Mod $mod): void
    {
        // Remove the mod's thumbnail image from storage if it exists.
        if ($mod->thumbnail) {
            $disk = config()->string('filesystems.asset_upload', 'public');
            if (Storage::disk($disk)->exists($mod->thumbnail)) {
                Storage::disk($disk)->delete($mod->thumbnail);
            }
        }

        // Polymorphic relations are not cascaded by the DB; remove list references here.
        ModListItem::query()
            ->where('listable_type', Mod::class)
            ->where('listable_id', $mod->id)
            ->delete();
    }

    /**
     * Update properties on related SptVersions.
     */
    private function updateRelatedSptVersions(Mod $mod): void
    {
        /** @var Collection<int, SptVersion> $sptVersions */
        $sptVersions = $mod->versions->flatMap->sptVersions->unique();

        foreach ($sptVersions as $sptVersion) {
            $sptVersion->updateModCount();
        }
    }
}
