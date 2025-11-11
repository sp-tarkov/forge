<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\AddonVersion;

trait ModeratesAddon
{
    /**
     * Delete the addon. Will automatically synchronize the listing.
     */
    public function deleteAddon(Addon $addon, string $route = ''): void
    {
        $this->authorize('delete', $addon);

        Track::event(TrackingEventType::ADDON_DELETE, $addon);

        $addon->delete();

        flash()->success('Addon successfully deleted!');

        // Redirect to the parent mod page if the addon was deleted from the detail page.
        if ($route === 'addon.show' && $addon->mod) {
            $this->redirectRoute('mod.show', [$addon->mod->id, $addon->mod->slug]);
        }
    }

    /**
     * Delete the addon version. Will automatically synchronize the listing.
     */
    public function deleteAddonVersion(AddonVersion $version): void
    {
        $this->authorize('delete', $version);

        Track::event(TrackingEventType::VERSION_DELETE, $version);

        $version->delete();

        flash()->success('Addon version successfully deleted!');
    }
}
