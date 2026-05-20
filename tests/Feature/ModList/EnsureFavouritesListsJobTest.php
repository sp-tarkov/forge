<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Jobs\EnsureFavouritesListsJob;
use App\Models\ModList;
use App\Models\User;

describe('EnsureFavouritesListsJob', function (): void {
    it('creates a Favourites list for a user that is missing one', function (): void {
        // Bypass the User observer so the user starts without a Favourites list.
        $user = User::withoutEvents(fn (): User => User::factory()->create());

        expect($user->favouritesList()->exists())->toBeFalse();

        (new EnsureFavouritesListsJob)->handle();

        $favourites = $user->favouritesList()->sole();

        expect($favourites->is_default)->toBeTrue();
        expect($favourites->visibility)->toBe(ListVisibility::Private);
        expect($favourites->title)->toBe(config()->string('mod-lists.favourites.title'));
    });

    it('does not create a duplicate for users that already have a Favourites list', function (): void {
        $user = User::factory()->create();

        (new EnsureFavouritesListsJob)->handle();

        expect(ModList::query()->where('owner_id', $user->id)->where('is_default', true)->count())->toBe(1);
    });

    it('uses a suffixed slug when the canonical Favourites slug is already taken', function (): void {
        $title = config()->string('mod-lists.favourites.title');
        $slug = config()->string('mod-lists.favourites.slug');

        $user = User::withoutEvents(fn (): User => User::factory()->create());

        // A non-default list squatting the canonical slug forces the unique-index workaround.
        $squatter = ModList::factory()->for($user, 'owner')->create(['title' => $title]);
        expect($squatter->slug)->toBe($slug);

        (new EnsureFavouritesListsJob)->handle();

        $favourites = $user->favouritesList()->sole();

        expect($favourites->slug)->not->toBe($slug);
        expect($favourites->slug)->toStartWith($slug.'-');
    });
});
