<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\ModVersion;
use Illuminate\Support\Facades\Auth;

trait ModeratesModVersion
{
    /**
     * Delete the mod version. Will automatically synchronize the listing.
     */
    public function deleteModVersion(ModVersion $version, string $reason = ''): void
    {
        $this->authorize('delete', $version);

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $version->mod->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::VERSION_DELETE,
            $version,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($reason ?: null) : null
        );

        $version->delete();

        flash()->success('Mod version successfully deleted!');
    }
}
