<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class ModListPolicy
{
    /**
     * Determine whether the user can view any mod lists (the public index).
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific list.
     *
     * Public lists are visible to everyone. Hidden lists require either
     * ownership or a matching share_token on the current request. Private
     * lists are visible only to the owner. A list disabled by a moderator is
     * hidden from everyone except its owner and staff, regardless of
     * visibility or share token.
     */
    public function view(?User $user, ModList $modList): bool
    {
        if ($modList->disabled) {
            if ($this->isOwner($user, $modList)) {
                return true;
            }

            return $user?->isModOrAdmin() ?? false;
        }

        return match ($modList->visibility) {
            ListVisibility::Public => true,
            ListVisibility::Hidden => $this->isOwner($user, $modList) || $this->hasValidShareToken($modList),
            ListVisibility::Private => $this->isOwner($user, $modList),
        };
    }

    /**
     * Determine whether the user can create a new list.
     *
     * Users are capped at the configured maximum lists per user (including Favourites).
     */
    public function create(User $user): bool
    {
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        $max = config()->integer('mod-lists.max_lists_per_user', 50);

        return $user->modLists()->count() < $max;
    }

    /**
     * Determine whether the user can update the list's metadata (description, SPT target, etc.).
     */
    public function update(User $user, ModList $modList): bool
    {
        return $this->isOwner($user, $modList);
    }

    /**
     * Determine whether the user can rename the list.
     *
     * Favourites is title-locked; everything else the owner can rename.
     */
    public function rename(User $user, ModList $modList): bool
    {
        return $this->isOwner($user, $modList) && ! $modList->is_default;
    }

    /**
     * Determine whether the user can change the list's visibility.
     *
     * The default Favourites list is locked to Private and its visibility can
     * never be changed by anyone. Every other list the owner controls.
     */
    public function changeVisibility(User $user, ModList $modList): bool
    {
        if ($modList->is_default) {
            return false;
        }

        return $this->isOwner($user, $modList);
    }

    /**
     * Determine whether the user can regenerate the list's share token.
     */
    public function regenerateShareToken(User $user, ModList $modList): bool
    {
        return $this->isOwner($user, $modList) && $modList->visibility === ListVisibility::Hidden;
    }

    /**
     * Determine whether the user can add an item (mod or addon) to the list.
     */
    public function addItem(User $user, ModList $modList): bool
    {
        if (! $this->isOwner($user, $modList)) {
            return false;
        }

        $max = config()->integer('mod-lists.max_items_per_list', 250);

        return $modList->items()->count() < $max;
    }

    /**
     * Determine whether the user can remove an item from the list.
     */
    public function removeItem(User $user, ModList $modList): bool
    {
        return $this->isOwner($user, $modList);
    }

    /**
     * Determine whether the user can edit the per-item note.
     */
    public function updateItemNote(User $user, ModList $modList): bool
    {
        return $this->isOwner($user, $modList);
    }

    /**
     * Determine whether the user can reorder items in the list.
     */
    public function reorder(User $user, ModList $modList): bool
    {
        return $this->isOwner($user, $modList);
    }

    /**
     * Determine whether the user can delete the list.
     *
     * The default Favourites list can never be deleted by anyone, including
     * staff. Any other list can be deleted by its owner or, as a moderation
     * action, by a moderator or administrator.
     */
    public function delete(User $user, ModList $modList): bool
    {
        return ! $modList->is_default
            && ($this->isOwner($user, $modList) || $user->isModOrAdmin());
    }

    /**
     * Determine whether the user can disable the list.
     *
     * Disabling is a staff-only moderation action.
     */
    public function disable(User $user, ModList $modList): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can enable the list.
     *
     * Enabling is a staff-only moderation action.
     */
    public function enable(User $user, ModList $modList): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $user->isModOrAdmin();
    }

    /**
     * Determine whether the user can report a mod list.
     *
     * Authentication and email verification are required.
     */
    public function report(User $user, Model $reportable): bool
    {
        // Must have verified email address
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        // Moderators and administrators cannot create reports.
        if ($user->isModOrAdmin()) {
            return false;
        }

        // Owners cannot report their own list.
        if ($reportable instanceof ModList && $this->isOwner($user, $reportable)) {
            return false;
        }

        // Check if the reportable model has the required method.
        if (! method_exists($reportable, 'hasBeenReportedBy')) {
            return false;
        }

        // User cannot report the same item more than once.
        return ! $reportable->hasBeenReportedBy($user->id);
    }

    private function isOwner(?User $user, ModList $modList): bool
    {
        return $user instanceof User && $user->id === $modList->owner_id;
    }

    private function hasValidShareToken(ModList $modList): bool
    {
        if ($modList->share_token === null) {
            return false;
        }

        $request = request();
        $presented = $request->route('shareToken') ?? $request->query('share_token');

        return is_string($presented) && hash_equals($modList->share_token, $presented);
    }
}
