<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;

final class UserObserver
{
    /**
     * Handle the User "created" event.
     *
     * Every new user gets an auto-created, immutable default Favourites list.
     */
    public function created(User $user): void
    {
        $title = config()->string('mod-lists.favourites.title', 'Favourites');
        $slug = config()->string('mod-lists.favourites.slug', 'favourites');

        ModList::query()->firstOrCreate(
            [
                'owner_id' => $user->id,
                'is_default' => true,
            ],
            [
                'title' => $title,
                'slug' => $slug,
                'visibility' => ListVisibility::Private,
            ]
        );
    }
}
