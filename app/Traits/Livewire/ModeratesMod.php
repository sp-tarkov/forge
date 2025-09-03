<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Mod;

trait ModeratesMod
{
    /**
     * Delete the mod. Will automatically synchronize the listing.
     */
    public function deleteMod(Mod $mod, string $route = ''): void
    {
        $this->authorize('delete', $mod);

        Track::event(TrackingEventType::MOD_DELETE, $mod);

        $mod->delete();

        flash()->success('Mod successfully deleted!');

        // Redirect to the listing if the mod was deleted from the detail page.
        if ($route === 'mod.show') {
            $this->redirectRoute('mods');
        }
    }

    /**
     * Remove the featured flag from the mod. Will automatically synchronize the listing. This should only be used in
     * the context of the homepage featured section; otherwise, use the moderation->unfeature method.
     */
    public function unfeatureMod(Mod $mod): void
    {
        $this->authorize('unfeature', $mod);

        $mod->featured = false;
        $mod->save();

        Track::event(TrackingEventType::MOD_UNFEATURE, $mod);

        flash()->success('Mod successfully unfeatured!');
    }
}
