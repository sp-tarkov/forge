<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;

describe('Favourites observer', function (): void {
    it('creates a private default Favourites list for new users', function (): void {
        $user = User::factory()->create();

        $favourites = $user->favouritesList;

        expect($favourites)->not->toBeNull();
        expect($favourites->is_default)->toBeTrue();
        expect($favourites->visibility)->toBe(ListVisibility::Private);
        expect($favourites->title)->toBe(config('mod-lists.favourites.title'));
    });

    it('only creates one Favourites list per user', function (): void {
        $user = User::factory()->create();

        expect(ModList::query()->where('owner_id', $user->id)->where('is_default', true)->count())->toBe(1);
    });
});

describe('ModList slug + share token lifecycle', function (): void {
    it('auto-generates a unique slug per owner on save', function (): void {
        $user = User::factory()->create();

        $first = ModList::factory()->for($user, 'owner')->create(['title' => 'My Mods']);
        $second = ModList::factory()->for($user, 'owner')->create(['title' => 'My Mods']);

        expect($first->slug)->not->toBe($second->slug);
    });

    it('generates a share token only for hidden visibility', function (): void {
        $user = User::factory()->create();

        $public = ModList::factory()->for($user, 'owner')->public()->create();
        $hidden = ModList::factory()->for($user, 'owner')->hidden()->create();
        $private = ModList::factory()->for($user, 'owner')->private()->create();

        expect($public->share_token)->toBeNull();
        expect($hidden->share_token)->not->toBeNull();
        expect($private->share_token)->toBeNull();
    });

    it('clears the share token when flipping away from hidden', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->hidden()->create();

        expect($list->share_token)->not->toBeNull();

        $list->visibility = ListVisibility::Private;
        $list->save();

        expect($list->fresh()->share_token)->toBeNull();
    });

    it('assigns a share token when flipping to hidden', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->for($user, 'owner')->public()->create();

        $list->visibility = ListVisibility::Hidden;
        $list->save();

        expect($list->fresh()->share_token)->not->toBeNull();
    });
});
