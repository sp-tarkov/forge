<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Mod;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;

/** @phpstan-ignore trait.unused */
trait ModeratesMod
{
    /**
     * Delete the mod. Will automatically synchronize the listing.
     */
    public function deleteMod(Mod $mod, string $route = '', string $reason = ''): void
    {
        $this->authorize('delete', $mod);

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::MOD_DELETE,
            $mod,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($reason ?: null) : null
        );

        $mod->delete();

        Flux::toast(heading: 'Mod Deleted', text: 'The mod has been successfully deleted.', variant: 'success');

        // Redirect to the listing if the mod was deleted from the detail page.
        if ($route === 'mod.show') {
            $this->redirectRoute('mods', navigate: true);
        }
    }

    /**
     * Remove the featured flag from the mod. Will automatically synchronize the listing. This should only be used in
     * the context of the homepage featured section; otherwise, use the moderation->unfeature method.
     */
    public function unfeatureMod(Mod $mod, string $reason = ''): void
    {
        $this->authorize('unfeature', $mod);

        $mod->featured = false;
        $mod->save();

        Track::eventSync(
            TrackingEventType::MOD_UNFEATURE,
            $mod,
            isModerationAction: true,
            reason: $reason ?: null
        );

        Flux::toast(heading: 'Mod Unfeatured', text: 'The mod has been successfully unfeatured.', variant: 'success');
    }
}
