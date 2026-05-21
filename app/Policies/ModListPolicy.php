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
     * lists are visible only to the owner.
     */
    public function view(?User $user, ModList $modList): bool
    {
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
     * Allowed on all lists including Favourites.
     */
    public function changeVisibility(User $user, ModList $modList): bool
    {
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
     * The default Favourites list is undeletable.
     */
    public function delete(User $user, ModList $modList): bool
    {
        return $this->isOwner($user, $modList) && ! $modList->is_default;
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
