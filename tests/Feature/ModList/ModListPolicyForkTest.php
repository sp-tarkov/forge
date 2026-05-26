<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

describe('ModListPolicy fork', function (): void {
    it('allows a verified user to fork a public list', function (): void {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($actor->can('fork', $list))->toBeTrue();
    });

    it('denies guests from forking any list', function (): void {
        $list = ModList::factory()->public()->create();

        expect(auth()->guest())->toBeTrue();
        expect(Gate::allows('fork', $list))->toBeFalse();
    });

    it('denies unverified users from forking', function (): void {
        $owner = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($unverified->can('fork', $list))->toBeFalse();
    });

    it('denies forking a private list owned by another user', function (): void {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->private()->create();

        expect($actor->can('fork', $list))->toBeFalse();
    });

    it('allows forking a hidden list when the share token is present in the request', function (): void {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->hidden()->create();

        $this->actingAs($actor);

        expect($actor->can('fork', $list))->toBeFalse();

        $this->app->instance('request', Request::create(
            '/list/'.$list->id.'/'.$list->slug.'/'.$list->share_token,
            'GET',
            ['share_token' => $list->share_token],
        ));

        expect($actor->can('fork', $list))->toBeTrue();
    });

    it('allows the owner to duplicate their own private list', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->private()->create();

        expect($owner->can('fork', $list))->toBeTrue();
    });

    it('allows the owner to duplicate their own Favourites', function (): void {
        $owner = User::factory()->create();
        $favourites = $owner->favouritesList;

        expect($owner->can('fork', $favourites))->toBeTrue();
    });

    it('denies forking a disabled list', function (): void {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->disabled()->create();

        expect($actor->can('fork', $list))->toBeFalse();
    });

    it('denies forking once the actor has reached the per-user list cap', function (): void {
        config()->set('mod-lists.max_lists_per_user', 3);

        $owner = User::factory()->create();
        $actor = User::factory()->create();
        ModList::factory()->for($actor, 'owner')->count(2)->create();

        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($actor->can('fork', $list))->toBeFalse();
    });

    it('denies forking when the source has more items than the per-list cap', function (): void {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        Mod::factory()->count(3)->create()->each(function (Mod $mod, int $index) use ($list): void {
            ModListItem::factory()->create([
                'mod_list_id' => $list->id,
                'listable_type' => Mod::class,
                'listable_id' => $mod->id,
                'position' => $index,
            ]);
        });

        config()->set('mod-lists.max_items_per_list', 2);

        expect($actor->can('fork', $list))->toBeFalse();
    });
});
