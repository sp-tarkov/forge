<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Models\ModVersion;

trait ModeratesModVersion
{
    /**
     * Delete the mod version. Will automatically synchronize the listing.
     */
    public function deleteModVersion(ModVersion $version): void
    {
        $this->authorize('delete', $version);

        $version->delete();

        flash()->success('Mod version successfully deleted!');
    }
}
