<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

describe('view', function (): void {
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

describe('disable/enable', function (): void {
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

describe('delete', function (): void {
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

describe('capacity', function (): void {
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

describe('rename/delete immutability of Favourites', function (): void {
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

describe('report', function (): void {
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

    it('disallows reporting a private list the reporter cannot view', function (): void {
        $reporter = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->private()->create();

        expect($reporter->can('report', $list))->toBeFalse();
    });

    it('disallows reporting a hidden list without the share token', function (): void {
        $reporter = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->hidden()->create();

        expect($reporter->can('report', $list))->toBeFalse();
    });

    it('allows reporting a public list owned by someone else', function (): void {
        $reporter = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->public()->create();

        expect($reporter->can('report', $list))->toBeTrue();
    });
});

describe('regenerateShareToken', function (): void {
    it('allows the owner of a hidden list', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->hidden()->create();

        expect($owner->can('regenerateShareToken', $list))->toBeTrue();
    });

    it('disallows the owner of a non-hidden list', function (): void {
        $owner = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($owner->can('regenerateShareToken', $list))->toBeFalse();
    });

    it('disallows non-owners of a hidden list', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->hidden()->create();

        expect($other->can('regenerateShareToken', $list))->toBeFalse();
    });
});

describe('updateItemNote', function (): void {
    it('allows the owner and disallows everyone else', function (): void {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        expect($owner->can('updateItemNote', $list))->toBeTrue();
        expect($other->can('updateItemNote', $list))->toBeFalse();
    });
});

describe('forking', function (): void {
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
