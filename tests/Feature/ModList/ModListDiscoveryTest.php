<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;

describe('discoverable scope', function (): void {
    it('excludes Favourites from discovery even when public', function (): void {
        $user = User::factory()->create();

        $favourites = $user->favouritesList;
        $favourites->visibility = ListVisibility::Public;
        $favourites->save();

        $curatedPublic = ModList::factory()->for($user, 'owner')->public()->create();
        ModList::factory()->for($user, 'owner')->private()->create();

        $ids = ModList::query()->discoverable()->pluck('id');

        expect($ids)->toContain($curatedPublic->id);
        expect($ids)->not->toContain($favourites->id);
    });

    it('public scope still includes public Favourites (for profile tab visibility)', function (): void {
        $user = User::factory()->create();

        $favourites = $user->favouritesList;
        $favourites->visibility = ListVisibility::Public;
        $favourites->save();

        $ids = ModList::query()->public()->pluck('id');

        expect($ids)->toContain($favourites->id);
    });
});

describe('Scout searchability', function (): void {
    it('marks Favourites as not searchable even when public', function (): void {
        $user = User::factory()->create();
        $favourites = $user->favouritesList;
        $favourites->visibility = ListVisibility::Public;
        $favourites->save();

        expect($favourites->shouldBeSearchable())->toBeFalse();
    });

    it('marks public non-default lists as searchable', function (): void {
        $list = ModList::factory()->public()->create();

        expect($list->shouldBeSearchable())->toBeTrue();
    });

    it('marks private lists as not searchable', function (): void {
        $list = ModList::factory()->private()->create();

        expect($list->shouldBeSearchable())->toBeFalse();
    });
});
