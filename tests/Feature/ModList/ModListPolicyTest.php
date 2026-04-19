<?php

declare(strict_types=1);

use App\Models\ModList;
use App\Models\User;
use Illuminate\Http\Request;

describe('ModListPolicy view', function (): void {
    it('allows anyone to view a public list', function (): void {
        $list = ModList::factory()->public()->create();
        $other = User::factory()->create();

        expect(auth()->guest())->toBeTrue();
        expect($other->can('view', $list))->toBeTrue();
    });

    it('allows only the owner to view a private list', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->private()->create();

        $this->actingAs($other);
        expect($other->can('view', $list))->toBeFalse();

        $this->actingAs($owner);
        expect($owner->can('view', $list))->toBeTrue();
    });

    it('allows hidden lists only with a valid share token', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->hidden()->create();

        $this->actingAs($other);

        // No token: denied
        expect($other->can('view', $list))->toBeFalse();

        // With a valid token via request query: allowed
        $this->app->instance('request', Request::create('/list/'.$list->id.'/'.$list->slug, 'GET', ['share_token' => $list->share_token]));
        expect($other->can('view', $list))->toBeTrue();

        // With a wrong token: denied
        $this->app->instance('request', Request::create('/list/'.$list->id.'/'.$list->slug, 'GET', ['share_token' => 'bogus']));
        expect($other->can('view', $list))->toBeFalse();
    });
});

describe('ModListPolicy capacity', function (): void {
    it('blocks create once the user has reached the cap', function (): void {
        config()->set('mod-lists.max_lists_per_user', 3);

        $user = User::factory()->create(); // starts with Favourites (1)
        ModList::factory()->for($user, 'owner')->count(2)->create(); // 3 total now

        expect($user->can('create', ModList::class))->toBeFalse();
    });

    it('allows create under the cap', function (): void {
        config()->set('mod-lists.max_lists_per_user', 5);

        $user = User::factory()->create();

        expect($user->can('create', ModList::class))->toBeTrue();
    });
});

describe('ModListPolicy rename/delete immutability of Favourites', function (): void {
    it('disallows renaming Favourites', function (): void {
        $user = User::factory()->create();
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        expect($user->can('rename', $favourites))->toBeFalse();
        expect($user->can('delete', $favourites))->toBeFalse();
    });

    it('allows changing Favourites visibility', function (): void {
        $user = User::factory()->create();
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        expect($user->can('changeVisibility', $favourites))->toBeTrue();
    });
});
