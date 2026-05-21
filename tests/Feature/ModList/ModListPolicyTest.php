<?php

declare(strict_types=1);

use App\Models\ModList;
use App\Models\Report;
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

    it('hides a disabled public list from the public', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->disabled()->create();

        $this->actingAs($other);
        expect($other->can('view', $list))->toBeFalse();
    });

    it('allows the owner and staff to view a disabled list', function (): void {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->disabled()->create();

        $this->actingAs($owner);
        expect($owner->can('view', $list))->toBeTrue();

        $this->actingAs($moderator);
        expect($moderator->can('view', $list))->toBeTrue();

        $this->actingAs($admin);
        expect($admin->can('view', $list))->toBeTrue();
    });

    it('denies a disabled hidden list even with a valid share token', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->hidden()->disabled()->create();

        $this->actingAs($other);
        $this->app->instance('request', Request::create('/list/'.$list->id.'/'.$list->slug, 'GET', ['share_token' => $list->share_token]));

        expect($other->can('view', $list))->toBeFalse();
    });
});

describe('ModListPolicy disable/enable', function (): void {
    it('allows moderators and admins to disable and enable a list', function (): void {
        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();
        $list = ModList::factory()->public()->create();

        expect($moderator->can('disable', $list))->toBeTrue();
        expect($moderator->can('enable', $list))->toBeTrue();
        expect($admin->can('disable', $list))->toBeTrue();
        expect($admin->can('enable', $list))->toBeTrue();
    });

    it('denies regular users and the owner from disabling or enabling a list', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($owner->can('disable', $list))->toBeFalse();
        expect($owner->can('enable', $list))->toBeFalse();
        expect($other->can('disable', $list))->toBeFalse();
        expect($other->can('enable', $list))->toBeFalse();
    });

    it('denies unverified moderators from disabling or enabling a list', function (): void {
        $moderator = User::factory()->moderator()->unverified()->create();
        $list = ModList::factory()->public()->create();

        expect($moderator->can('disable', $list))->toBeFalse();
        expect($moderator->can('enable', $list))->toBeFalse();
    });
});

describe('ModListPolicy delete', function (): void {
    it('allows the owner to delete their own non-default list', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($owner->can('delete', $list))->toBeTrue();
    });

    it('denies a regular non-owner from deleting a list', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($other->can('delete', $list))->toBeFalse();
    });

    it('allows moderators and admins to delete a non-default list', function (): void {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();

        $curated = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($moderator->can('delete', $curated))->toBeTrue();
        expect($admin->can('delete', $curated))->toBeTrue();
    });

    it('denies everyone, including staff, from deleting a default Favourites list', function (): void {
        $owner = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();

        $favourites = $owner->favouritesList;

        expect($owner->can('delete', $favourites))->toBeFalse();
        expect($moderator->can('delete', $favourites))->toBeFalse();
        expect($admin->can('delete', $favourites))->toBeFalse();
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

    it('disallows changing Favourites visibility for anyone', function (): void {
        $user = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();
        $favourites = $user->favouritesList;

        expect($user->can('changeVisibility', $favourites))->toBeFalse();
        expect($moderator->can('changeVisibility', $favourites))->toBeFalse();
        expect($admin->can('changeVisibility', $favourites))->toBeFalse();
    });

    it('allows the owner to change a non-default list visibility', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($owner->can('changeVisibility', $list))->toBeTrue();
        expect($other->can('changeVisibility', $list))->toBeFalse();
    });
});

describe('ModListPolicy report', function (): void {
    it('allows a verified user to report a list', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->public()->create();

        expect($user->can('report', $list))->toBeTrue();
    });

    it('disallows unverified users from reporting a list', function (): void {
        $user = User::factory()->unverified()->create();
        $list = ModList::factory()->public()->create();

        expect($user->can('report', $list))->toBeFalse();
    });

    it('disallows moderators and admins from reporting a list', function (): void {
        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();
        $list = ModList::factory()->public()->create();

        expect($moderator->can('report', $list))->toBeFalse();
        expect($admin->can('report', $list))->toBeFalse();
    });

    it('disallows reporting the same list twice', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->public()->create();

        Report::factory()->create([
            'reporter_id' => $user->id,
            'reportable_type' => $list::class,
            'reportable_id' => $list->id,
        ]);

        expect($user->can('report', $list))->toBeFalse();
    });

    it('disallows the owner from reporting their own list', function (): void {
        $user = User::factory()->create();
        $list = ModList::factory()->public()->create(['owner_id' => $user->id]);

        expect($user->can('report', $list))->toBeFalse();
    });
});
