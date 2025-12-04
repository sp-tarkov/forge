<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Addon;
use App\Models\AddonVersion;
use Illuminate\Support\Facades\Auth;

trait ModeratesAddon
{
    /**
     * Delete the addon. Will automatically synchronize the listing.
     */
    public function deleteAddon(Addon $addon, string $route = '', string $reason = ''): void
    {
        $this->authorize('delete', $addon);

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_DELETE,
            $addon,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($reason ?: null) : null
        );

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
    public function deleteAddonVersion(AddonVersion $version, string $reason = ''): void
    {
        $this->authorize('delete', $version);

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $version->addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_VERSION_DELETE,
            $version,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($reason ?: null) : null
        );

        $version->delete();

        flash()->success('Addon version successfully deleted!');
    }
}
