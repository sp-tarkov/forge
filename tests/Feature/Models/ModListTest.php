<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Exceptions\ModListEntryDisabledException;
use App\Jobs\TombstoneModInListsJob;
use App\Models\Addon;
use App\Models\Comment;
use App\Models\Dependency;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ModListService;
use Database\Seeders\ModListSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

describe('AuthorOptOut', function (): void {
    describe('Mod listsEnabled accessor', function (): void {
        it('reflects the inverse of lists_disabled', function (): void {
            $mod = Mod::factory()->create(['lists_disabled' => false]);
            expect($mod->lists_enabled)->toBeTrue();

            $mod->lists_disabled = true;
            $mod->save();

            expect($mod->fresh()->lists_enabled)->toBeFalse();
        });
    });

    describe('ModListService addMod opt-out guard', function (): void {
        it('throws when adding an opted-out mod to a user-created list', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $mod = Mod::factory()->create(['lists_disabled' => true]);

            expect(fn () => resolve(ModListService::class)->addMod($list, $mod))
                ->toThrow(ModListEntryDisabledException::class);

            expect($list->fresh()->itemCount())->toBe(0);
        });

        it('allows adding an opted-out mod to the favourites list', function (): void {
            $user = User::factory()->create();
            $favourites = resolve(ModListService::class)->ensureFavouritesFor($user);
            $mod = Mod::factory()->create(['lists_disabled' => true]);

            resolve(ModListService::class)->addMod($favourites, $mod);

            expect($favourites->fresh()->itemCount())->toBe(1);
            expect($favourites->containsMod($mod->id))->toBeTrue();
        });

        it('filters opted-out mods from explicit dependency cascade', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $primary = Mod::factory()->create(['lists_disabled' => false]);
            $allowedDep = Mod::factory()->create(['lists_disabled' => false]);
            $blockedDep = Mod::factory()->create(['lists_disabled' => true]);

            resolve(ModListService::class)->addMod($list, $primary, collect([$allowedDep, $blockedDep]));

            expect($list->fresh()->itemCount())->toBe(2);
            expect($list->containsMod($primary->id))->toBeTrue();
            expect($list->containsMod($allowedDep->id))->toBeTrue();
            expect($list->containsMod($blockedDep->id))->toBeFalse();
        });
    });

    describe('ModListService addAddon opt-out inheritance', function (): void {
        it('throws when the parent mod has opted out of lists', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $mod = Mod::factory()->create(['lists_disabled' => true]);
            $addon = Addon::factory()->create(['mod_id' => $mod->id]);

            expect(fn () => resolve(ModListService::class)->addAddon($list, $addon, includeParentMod: true))
                ->toThrow(ModListEntryDisabledException::class);

            expect($list->fresh()->itemCount())->toBe(0);
        });

        it('throws even when includeParentMod is false', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $mod = Mod::factory()->create(['lists_disabled' => true]);
            $addon = Addon::factory()->create(['mod_id' => $mod->id]);

            expect(fn () => resolve(ModListService::class)->addAddon($list, $addon))
                ->toThrow(ModListEntryDisabledException::class);
        });
    });

    describe('ModListService addMods bulk filter', function (): void {
        it('silently skips opted-out mods in bulk adds', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $allowed = Mod::factory()->create(['lists_disabled' => false]);
            $blocked = Mod::factory()->create(['lists_disabled' => true]);

            $added = resolve(ModListService::class)->addMods($list, collect([$allowed, $blocked]));

            expect($added)->toBe(1);
            expect($list->fresh()->itemCount())->toBe(1);
            expect($list->containsMod($allowed->id))->toBeTrue();
            expect($list->containsMod($blocked->id))->toBeFalse();
        });
    });

    describe('ModListService toggleFavourite bypass', function (): void {
        it('allows toggling an opted-out mod into favourites', function (): void {
            $user = User::factory()->create();
            $favourites = resolve(ModListService::class)->ensureFavouritesFor($user);
            $mod = Mod::factory()->create(['lists_disabled' => true]);

            $added = resolve(ModListService::class)->toggleFavourite($favourites, $mod);

            expect($added)->toBeTrue();
            expect($favourites->containsMod($mod->id))->toBeTrue();

            $removed = resolve(ModListService::class)->toggleFavourite($favourites, $mod);
            expect($removed)->toBeFalse();
        });
    });

    describe('ModListService dependency cascade walk reports skipped', function (): void {
        it('suggestedDependenciesResult splits included from skipped opt-outs', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();

            [$modA, $verA] = makeListOptOutMod('A');
            [$modB] = makeListOptOutMod('B', listsDisabled: true);
            [$modC] = makeListOptOutMod('C');
            linkListOptOutDep($verA, $modB);
            linkListOptOutDep($verA, $modC);

            $result = resolve(ModListService::class)->suggestedDependenciesResult($list, $modA);

            expect($result->included->pluck('id')->all())->toBe([$modC->id]);
            expect($result->skipped->pluck('id')->all())->toBe([$modB->id]);
        });

        it('missingDependenciesResultForList reports both included and skipped', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();

            [$modA, $verA] = makeListOptOutMod('A');
            [$modB] = makeListOptOutMod('B', listsDisabled: true);
            [$modC] = makeListOptOutMod('C');
            linkListOptOutDep($verA, $modB);
            linkListOptOutDep($verA, $modC);

            resolve(ModListService::class)->addMod($list, $modA);

            $result = resolve(ModListService::class)->missingDependenciesResultForList($list->fresh());

            expect($result->included->pluck('id')->all())->toBe([$modC->id]);
            expect($result->skipped->pluck('id')->all())->toBe([$modB->id]);
        });

        it('favourites lists do not skip opted-out dependencies', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $user = User::factory()->create();
            $favourites = resolve(ModListService::class)->ensureFavouritesFor($user);

            [$modA, $verA] = makeListOptOutMod('A');
            [$modB] = makeListOptOutMod('B', listsDisabled: true);
            linkListOptOutDep($verA, $modB);

            $result = resolve(ModListService::class)->suggestedDependenciesResult($favourites, $modA);

            expect($result->included->pluck('id')->all())->toBe([$modB->id]);
            expect($result->skipped)->toBeEmpty();
        });
    });

    describe('ModObserver dispatches the tombstone job', function (): void {
        it('dispatches when lists_disabled flips false to true', function (): void {
            Queue::fake();

            $mod = Mod::factory()->create(['lists_disabled' => false]);
            $mod->lists_disabled = true;
            $mod->save();

            Queue::assertPushed(TombstoneModInListsJob::class, fn (TombstoneModInListsJob $job): bool => $job->modId === $mod->id);
        });

        it('does not dispatch when lists_disabled flips true to false', function (): void {
            Queue::fake();

            $mod = Mod::factory()->create(['lists_disabled' => true]);
            $mod->lists_disabled = false;
            $mod->save();

            Queue::assertNotPushed(TombstoneModInListsJob::class);
        });

        it('does not dispatch on unrelated saves', function (): void {
            $mod = Mod::factory()->create(['lists_disabled' => false]);

            Queue::fake();

            $mod->teaser = 'Updated teaser';
            $mod->save();

            Queue::assertNotPushed(TombstoneModInListsJob::class);
        });
    });

    describe('TombstoneModInListsJob', function (): void {
        it('marks the mod and its addons as tombstones on non-favourite lists', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $mod = Mod::factory()->create(['name' => 'The Mod', 'lists_disabled' => false]);
            $addon = Addon::factory()->create(['mod_id' => $mod->id, 'name' => 'The Addon']);

            $svc = resolve(ModListService::class);
            $svc->addMod($list, $mod);
            $svc->addAddon($list, $addon);

            // Flip to opted out, run job
            $mod->lists_disabled = true;
            $mod->saveQuietly();
            dispatch_sync(new TombstoneModInListsJob($mod->id));

            $modItem = ModListItem::query()->where('listable_type', Mod::class)->where('listable_id', $mod->id)->first();
            $addonItem = ModListItem::query()->where('listable_type', Addon::class)->where('listable_id', $addon->id)->first();

            expect($modItem->isTombstone())->toBeTrue();
            expect($modItem->tombstoned_name)->toBe('The Mod');
            expect($addonItem->isTombstone())->toBeTrue();
            expect($addonItem->tombstoned_name)->toBe('The Addon');
        });

        it('leaves favourites entries untouched', function (): void {
            $user = User::factory()->create();
            $favourites = resolve(ModListService::class)->ensureFavouritesFor($user);
            $mod = Mod::factory()->create(['lists_disabled' => false]);

            resolve(ModListService::class)->addMod($favourites, $mod);

            $mod->lists_disabled = true;
            $mod->saveQuietly();
            dispatch_sync(new TombstoneModInListsJob($mod->id));

            $favItem = ModListItem::query()
                ->where('mod_list_id', $favourites->id)
                ->where('listable_type', Mod::class)
                ->where('listable_id', $mod->id)
                ->first();

            expect($favItem->isTombstone())->toBeFalse();
            expect($favItem->tombstoned_at)->toBeNull();
        });

        it('bails when the mod has been un-opted-out before the job runs', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $mod = Mod::factory()->create(['lists_disabled' => false]);

            resolve(ModListService::class)->addMod($list, $mod);

            // Author flipped the flag back off before the queued job got to run.
            dispatch_sync(new TombstoneModInListsJob($mod->id));

            $modItem = ModListItem::query()->where('listable_id', $mod->id)->first();
            expect($modItem->isTombstone())->toBeFalse();
        });
    });

    describe('Tombstones are functionally absent', function (): void {
        it('itemCount excludes tombstones for the capacity check', function (): void {
            config()->set('mod-lists.max_items_per_list', 2);

            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $tombstonedMod = Mod::factory()->create(['lists_disabled' => false]);
            $anotherMod = Mod::factory()->create(['lists_disabled' => false]);

            resolve(ModListService::class)->addMod($list, $tombstonedMod);

            // Tombstone the first mod
            $tombstonedMod->lists_disabled = true;
            $tombstonedMod->saveQuietly();
            dispatch_sync(new TombstoneModInListsJob($tombstonedMod->id));

            // The tombstone must not consume a capacity slot.
            expect($list->fresh()->itemCount())->toBe(0);

            // Adding two more active mods should now succeed under cap=2.
            resolve(ModListService::class)->addMod($list, $anotherMod);
            $thirdMod = Mod::factory()->create(['lists_disabled' => false]);
            resolve(ModListService::class)->addMod($list, $thirdMod);

            expect($list->fresh()->itemCount())->toBe(2);
        });

        it('containsMod returns false for a tombstoned mod', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $mod = Mod::factory()->create(['lists_disabled' => false]);

            resolve(ModListService::class)->addMod($list, $mod);

            $mod->lists_disabled = true;
            $mod->saveQuietly();
            dispatch_sync(new TombstoneModInListsJob($mod->id));

            expect($list->fresh()->containsMod($mod->id))->toBeFalse();
        });

        it('forkList omits tombstones from the new list', function (): void {
            $owner = User::factory()->create();
            $forker = User::factory()->create();
            $list = ModList::factory()->for($owner, 'owner')->public()->create();
            $modActive = Mod::factory()->create(['lists_disabled' => false]);
            $modToTombstone = Mod::factory()->create(['lists_disabled' => false]);

            $svc = resolve(ModListService::class);
            $svc->addMod($list, $modActive);
            $svc->addMod($list, $modToTombstone);

            // Tombstone one
            $modToTombstone->lists_disabled = true;
            $modToTombstone->saveQuietly();
            dispatch_sync(new TombstoneModInListsJob($modToTombstone->id));

            $fork = $svc->forkList($forker, $list->fresh(), 'My Fork');

            expect($fork->itemCount())->toBe(1);
            expect($fork->containsMod($modActive->id))->toBeTrue();
            expect($fork->containsMod($modToTombstone->id))->toBeFalse();
        });

        it('curator can remove a tombstone via the normal remove flow', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $mod = Mod::factory()->create(['lists_disabled' => false]);

            resolve(ModListService::class)->addMod($list, $mod);

            $mod->lists_disabled = true;
            $mod->saveQuietly();
            dispatch_sync(new TombstoneModInListsJob($mod->id));

            $tombstone = ModListItem::query()
                ->where('mod_list_id', $list->id)
                ->where('listable_id', $mod->id)
                ->first();

            resolve(ModListService::class)->removeItem($list, $tombstone);

            expect(ModListItem::query()->where('mod_list_id', $list->id)->count())->toBe(0);
        });

        it('re-enabling lists_disabled does not revive tombstones', function (): void {
            $user = User::factory()->create();
            $list = ModList::factory()->for($user, 'owner')->public()->create();
            $mod = Mod::factory()->create(['lists_disabled' => false]);

            resolve(ModListService::class)->addMod($list, $mod);

            $mod->lists_disabled = true;
            $mod->saveQuietly();
            dispatch_sync(new TombstoneModInListsJob($mod->id));

            // Author re-enables; tombstones should NOT be auto-restored.
            $mod->lists_disabled = false;
            $mod->save();

            $item = ModListItem::query()->where('listable_id', $mod->id)->first();
            expect($item->isTombstone())->toBeTrue();
        });
    });

    describe('Tombstone name visibility on the list show page', function (): void {
        it('hides the captured mod name from guests', function (): void {
            [$list, $mod] = seedTombstoneOnList();

            $this->get(route('list.show', ['listId' => $list->id, 'slug' => $list->slug]))
                ->assertOk()
                ->assertDontSee($mod->name)
                ->assertSee('Removed item');
        });

        it('hides the captured mod name from a regular signed-in user', function (): void {
            [$list, $mod] = seedTombstoneOnList();
            $regular = User::factory()->create();

            Livewire::actingAs($regular)
                ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
                ->assertDontSee($mod->name)
                ->assertSee('Removed item');
        });

        it('hides the captured mod name from the list owner if they are neither the mod author nor staff', function (): void {
            [$list, $mod, $owner] = seedTombstoneOnList();

            Livewire::actingAs($owner)
                ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
                ->assertDontSee($mod->name)
                ->assertSee('Removed item');
        });

        it('shows the captured mod name to the mod owner', function (): void {
            [$list, $mod] = seedTombstoneOnList();

            Livewire::actingAs($mod->owner)
                ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
                ->assertSee($mod->name);
        });

        it('shows the captured mod name to additional authors of the mod', function (): void {
            [$list, $mod] = seedTombstoneOnList();
            $coAuthor = User::factory()->create();
            $mod->additionalAuthors()->attach($coAuthor);

            Livewire::actingAs($coAuthor)
                ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
                ->assertSee($mod->name);
        });

        it('shows the captured mod name to staff and moderators', function (): void {
            [$list, $mod] = seedTombstoneOnList();

            $staffRole = UserRole::factory()->staff()->create();
            $staff = User::factory()->for($staffRole, 'role')->create();

            Livewire::actingAs($staff)
                ->test('pages::list.show', ['listId' => $list->id, 'slug' => $list->slug])
                ->assertSee($mod->name);
        });
    });
});

describe('Comments', function (): void {
    describe('ModList::canReceiveComments', function (): void {
        it('returns false for Favourites', function (): void {
            $user = User::factory()->create();
            $favourites = $user->favouritesList;

            expect($favourites->canReceiveComments())->toBeFalse();
        });

        it('returns false for private lists', function (): void {
            $list = ModList::factory()->private()->create();

            expect($list->canReceiveComments())->toBeFalse();
        });

        it('returns true for public lists by default', function (): void {
            $list = ModList::factory()->public()->create();

            expect($list->canReceiveComments())->toBeTrue();
        });

        it('returns true for hidden lists by default', function (): void {
            $list = ModList::factory()->hidden()->create();

            expect($list->canReceiveComments())->toBeTrue();
        });

        it('returns false when the author disables comments on a public list', function (): void {
            $list = ModList::factory()->public()->create(['comments_disabled' => true]);

            expect($list->canReceiveComments())->toBeFalse();
        });
    });

    describe('ModListForm normalizes comments_disabled', function (): void {
        it('forces comments_disabled to true when the list is Favourites', function (): void {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $favourites = $user->favouritesList;

            $this->actingAs($user);

            Livewire::test('pages::list.edit', ['listId' => $favourites->id])
                ->set('form.visibility', ListVisibility::Public->value)
                ->set('form.comments_disabled', false)
                ->call('save');

            expect($favourites->fresh()->comments_disabled)->toBeTrue();
        });

        it('forces comments_disabled to true when the list is private', function (): void {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $list = ModList::factory()->for($user, 'owner')->public()->create();

            $this->actingAs($user);

            Livewire::test('pages::list.edit', ['listId' => $list->id])
                ->set('form.visibility', ListVisibility::Private->value)
                ->set('form.comments_disabled', false)
                ->call('save');

            expect($list->fresh()->comments_disabled)->toBeTrue();
        });

        it('respects the author toggle on public lists', function (): void {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $list = ModList::factory()->for($user, 'owner')->public()->create();

            $this->actingAs($user);

            Livewire::test('pages::list.edit', ['listId' => $list->id])
                ->set('form.visibility', ListVisibility::Public->value)
                ->set('form.comments_disabled', true)
                ->call('save');

            expect($list->fresh()->comments_disabled)->toBeTrue();
        });
    });

    describe('CommentPolicy list-owner moderation', function (): void {
        it('lets the list owner soft-delete comments on their own list', function (): void {
            $owner = User::factory()->create(['email_verified_at' => now()]);
            $list = ModList::factory()->for($owner, 'owner')->public()->create();
            $commenter = User::factory()->create();
            $comment = Comment::factory()
                ->for($commenter, 'user')
                ->create([
                    'commentable_type' => ModList::class,
                    'commentable_id' => $list->id,
                ]);

            expect($owner->can('modOwnerSoftDelete', $comment))->toBeTrue();
        });

        it('lets the list owner pin comments on their own list', function (): void {
            $owner = User::factory()->create(['email_verified_at' => now()]);
            $list = ModList::factory()->for($owner, 'owner')->public()->create();
            $commenter = User::factory()->create();
            $comment = Comment::factory()
                ->for($commenter, 'user')
                ->create([
                    'commentable_type' => ModList::class,
                    'commentable_id' => $list->id,
                ]);

            expect($owner->can('pin', $comment))->toBeTrue();
        });

        it('forbids a non-owner from moderating the list comments', function (): void {
            $owner = User::factory()->create(['email_verified_at' => now()]);
            $outsider = User::factory()->create(['email_verified_at' => now()]);
            $list = ModList::factory()->for($owner, 'owner')->public()->create();
            $commenter = User::factory()->create();
            $comment = Comment::factory()
                ->for($commenter, 'user')
                ->create([
                    'commentable_type' => ModList::class,
                    'commentable_id' => $list->id,
                ]);

            expect($outsider->can('modOwnerSoftDelete', $comment))->toBeFalse();
            expect($outsider->can('pin', $comment))->toBeFalse();
        });
    });
});

describe('Seeder', function (): void {
    describe('ModListSeeder Favourites lists', function (): void {
        it('creates a Favourites list for every user when seeding', function (): void {
            // Seeders run with model events disabled, so the User observer never
            // creates the Favourites list - the seeder must create it explicitly.
            /** @var Collection<int, User> $users */
            $users = User::withoutEvents(fn (): Collection => User::factory()->count(3)->create());

            expect(ModList::query()->where('is_default', true)->count())->toBe(0);

            $this->seed(ModListSeeder::class);

            foreach ($users as $user) {
                $favourites = ModList::query()
                    ->where('owner_id', $user->id)
                    ->where('is_default', true)
                    ->sole();

                expect($favourites->visibility)->toBe(ListVisibility::Private);
                expect($favourites->title)->toBe(config()->string('mod-lists.favourites.title'));
            }
        });

        it('does not create a duplicate Favourites list for users that already have one', function (): void {
            $user = User::factory()->create();

            expect(ModList::query()->where('owner_id', $user->id)->where('is_default', true)->count())->toBe(1);

            $this->seed(ModListSeeder::class);

            expect(ModList::query()->where('owner_id', $user->id)->where('is_default', true)->count())->toBe(1);
        });
    });

    describe('ModListSeeder forks', function (): void {
        it('seeds at least some forked lists when sources exist', function (): void {
            SptVersion::factory()->count(2)->create();
            User::factory()->count(20)->create();
            Mod::factory()->count(20)->create();

            $this->seed(ModListSeeder::class);

            $hasAnyForks = ModList::query()->whereNotNull('forked_from_list_id')->exists();

            expect($hasAnyForks)->toBeTrue('seeder must produce at least some forked lists to exercise provenance UI');
        });

        it('copies source items into each forked list', function (): void {
            SptVersion::factory()->count(2)->create();
            User::factory()->count(20)->create();
            Mod::factory()->count(20)->create();

            $this->seed(ModListSeeder::class);

            /** @var ModList|null $fork */
            $fork = ModList::query()
                ->whereNotNull('forked_from_list_id')
                ->whereHas('items')
                ->with(['items', 'forkedFromList.items'])
                ->first();

            expect($fork)->not->toBeNull('expected at least one fork with items');

            $forkItems = $fork->items->map(fn (ModListItem $item): string => $item->listable_type.':'.$item->listable_id.':'.$item->position)->sort()->values()->all();
            $sourceItems = $fork->forkedFromList->items->map(fn (ModListItem $item): string => $item->listable_type.':'.$item->listable_id.':'.$item->position)->sort()->values()->all();

            expect($forkItems)->toBe($sourceItems);
        });
    });

    describe('ModListSeeder test account demo lists', function (): void {
        it('seeds a curated set of lists for the test@example.com account when present', function (): void {
            SptVersion::factory()->count(2)->create();
            Mod::factory()->count(10)->create();
            Addon::factory()->count(8)->create();

            // The seeder discovers the test account by email; create one to opt into the demo set.
            $testAccount = User::factory()->create(['email' => 'test@example.com']);

            $this->seed(ModListSeeder::class);

            $testLists = ModList::query()
                ->where('owner_id', $testAccount->id)
                ->where('is_default', false)
                ->pluck('title')
                ->all();

            // Every demo state should be present so the dev account has one-click access.
            $expectedTitles = [
                'Demo: Public List with Notes',
                'Demo: Hidden List (Share Token)',
                'Demo: Private List',
                'Demo: Comments Disabled',
                'Demo: Moderator Disabled',
                'Demo: SPT Targeted (3.x)',
                'Demo: Empty List',
                'Demo: List with Tombstones',
                'Demo: Fork Source',
                'Demo: Forked From Source',
            ];

            foreach ($expectedTitles as $expectedTitle) {
                expect($testLists)->toContain($expectedTitle);
            }
        });

        it('includes both mod and addon tombstones on the tombstone demo list', function (): void {
            SptVersion::factory()->count(2)->create();
            Mod::factory()->count(10)->create();
            Addon::factory()->count(8)->create();

            User::factory()->create(['email' => 'test@example.com']);

            $this->seed(ModListSeeder::class);

            $tombstoneList = ModList::query()
                ->where('title', 'Demo: List with Tombstones')
                ->with('items')
                ->sole();

            $tombstoneRows = $tombstoneList->items->filter(fn (ModListItem $item): bool => $item->isTombstone());

            expect($tombstoneRows->count())->toBeGreaterThanOrEqual(1, 'tombstone demo list must include at least one tombstoned mod row');

            foreach ($tombstoneRows as $row) {
                expect($row->tombstoned_name)->not->toBeNull();
                expect($row->tombstoned_at)->not->toBeNull();
            }
        });

        it('marks the underlying mod as lists_disabled when seeding a tombstone', function (): void {
            SptVersion::factory()->count(2)->create();
            Mod::factory()->count(10)->create();
            Addon::factory()->count(8)->create();

            User::factory()->create(['email' => 'test@example.com']);

            $this->seed(ModListSeeder::class);

            $tombstoneList = ModList::query()
                ->where('title', 'Demo: List with Tombstones')
                ->with('items')
                ->sole();

            $tombstoneMod = $tombstoneList->items
                ->first(fn (ModListItem $item): bool => $item->isTombstone() && $item->listable_type === Mod::class);

            expect($tombstoneMod)->not->toBeNull();

            $mod = Mod::query()->find($tombstoneMod->listable_id);

            expect($mod->lists_disabled)->toBeTrue('tombstoned mod should have lists_disabled flipped so the toggle reads as enabled in the UI');
        });

        it('populates the test account favourites list with items', function (): void {
            SptVersion::factory()->count(2)->create();
            Mod::factory()->count(10)->create();

            $testAccount = User::factory()->create(['email' => 'test@example.com']);

            $this->seed(ModListSeeder::class);

            $favourites = ModList::query()
                ->where('owner_id', $testAccount->id)
                ->where('is_default', true)
                ->sole();

            expect($favourites->items()->count())->toBeGreaterThan(0);
        });
    });

    describe('ModListSeeder item rows', function (): void {
        it('never seeds an addon without its parent mod also on the same list', function (): void {
            // Regression: orphan-addon list items (addon on the list, parent mod not on the list) bypass the SPT-version
            // resolver on the show page. The real add-to-list flow refuses to create them, so the seeder must not produce
            // them either.
            SptVersion::factory()->count(3)->create();
            User::factory()->count(5)->create();
            Mod::factory()->count(10)->create();
            Addon::factory()->count(20)->create();

            $this->seed(ModListSeeder::class);

            /** @var Illuminate\Support\Collection<int, ModListItem> $addonItems */
            $addonItems = ModListItem::query()
                ->where('listable_type', Addon::class)
                ->with('listable')
                ->get();

            expect($addonItems)->not->toBeEmpty('seeder must produce at least some addon items to exercise this invariant');

            foreach ($addonItems as $addonItem) {
                $parentModId = $addonItem->listable instanceof Addon ? $addonItem->listable->mod_id : null;
                if ($parentModId === null) {
                    continue;
                }

                $hasParent = ModListItem::query()
                    ->where('mod_list_id', $addonItem->mod_list_id)
                    ->where('listable_type', Mod::class)
                    ->where('listable_id', $parentModId)
                    ->exists();

                expect($hasParent)->toBeTrue(
                    sprintf('Addon item %d (addon %d) is on list %d but its parent mod %d is not.', $addonItem->id, $addonItem->listable_id, $addonItem->mod_list_id, $parentModId),
                );
            }
        });
    });
});

describe('Discovery', function (): void {
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

        it('marks a disabled public list as not searchable', function (): void {
            $list = ModList::factory()->public()->disabled()->create();

            expect($list->shouldBeSearchable())->toBeFalse();
        });
    });

    describe('disabled lists are hidden from public scopes', function (): void {
        it('excludes a disabled list from the discoverable scope', function (): void {
            $visible = ModList::factory()->public()->create();
            $disabled = ModList::factory()->public()->disabled()->create();

            $ids = ModList::query()->discoverable()->pluck('id');

            expect($ids)->toContain($visible->id);
            expect($ids)->not->toContain($disabled->id);
        });

        it('excludes a disabled list from the public scope', function (): void {
            $visible = ModList::factory()->public()->create();
            $disabled = ModList::factory()->public()->disabled()->create();

            $ids = ModList::query()->public()->pluck('id');

            expect($ids)->toContain($visible->id);
            expect($ids)->not->toContain($disabled->id);
        });
    });
});

describe('Thumbnail', function (): void {
    beforeEach(function (): void {
        Storage::fake(config('filesystems.asset_upload', 'public'));
    });

    it('stores the uploaded file, path, and hash on save', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('thumbnail', UploadedFile::fake()->image('cover.jpg', 512, 512))
            ->call('save');

        $list->refresh();

        expect($list->thumbnail)->not->toBeNull();
        expect($list->thumbnail_hash)->not->toBeNull();

        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->assertExists($list->thumbnail);
    });

    it('replaces an existing thumbnail and deletes the old file', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create([
            'thumbnail' => 'mod-lists/old-thumb.png',
            'thumbnail_hash' => 'abc',
        ]);
        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->put('mod-lists/old-thumb.png', 'old');

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('thumbnail', UploadedFile::fake()->image('new.jpg', 512, 512))
            ->call('save');

        $list->refresh();

        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->assertMissing('mod-lists/old-thumb.png');
        expect($list->thumbnail)->not->toBe('mod-lists/old-thumb.png');
    });

    it('deletes the stored thumbnail on demand', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create([
            'thumbnail' => 'mod-lists/ditch.png',
            'thumbnail_hash' => 'xyz',
        ]);
        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->put('mod-lists/ditch.png', 'bytes');

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->call('deleteExistingThumbnail');

        $list->refresh();

        expect($list->thumbnail)->toBeNull();
        expect($list->thumbnail_hash)->toBeNull();
        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->assertMissing('mod-lists/ditch.png');
    });

    it('deletes the thumbnail file when the list is deleted', function (): void {
        $list = ModList::factory()->public()->create([
            'thumbnail' => 'mod-lists/cleanup.png',
            'thumbnail_hash' => 'h',
        ]);
        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->put('mod-lists/cleanup.png', 'bytes');

        $list->delete();

        Storage::disk(config('filesystems.asset_upload', 'public'))
            ->assertMissing('mod-lists/cleanup.png');
    });

    it('ignores the thumbnail field when editing Favourites', function (): void {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $favourites = $user->favouritesList;

        $this->actingAs($user);

        Livewire::test('pages::list.edit', ['listId' => $favourites->id])
            ->set('thumbnail', UploadedFile::fake()->image('f.jpg', 512, 512))
            ->call('save');

        $favourites->refresh();
        expect($favourites->thumbnail)->toBeNull();
    });

    it('rejects disallowed image mime types', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($owner);

        // GIF is a real image but not in the allowed mime list (jpg, png, webp).
        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('thumbnail', UploadedFile::fake()->image('anim.gif', 512, 512))
            ->call('save')
            ->assertHasErrors('thumbnail');
    });

    it('rejects oversize uploads', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($owner);

        Livewire::test('pages::list.edit', ['listId' => $list->id])
            ->set('thumbnail', UploadedFile::fake()->image('huge.jpg', 512, 512)->size(3000))
            ->call('save')
            ->assertHasErrors('thumbnail');
    });

    it('forbids non-owners from reaching the edit page', function (): void {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $outsider = User::factory()->create(['email_verified_at' => now()]);
        $list = ModList::factory()->for($owner, 'owner')->public()->create();

        $this->actingAs($outsider);

        $this->get(route('list.edit', ['listId' => $list->id]))
            ->assertForbidden();
    });
});

/**
 * Build a public mod list that contains one tombstoned mod, owned by a fresh regular user who is neither the mod's
 * author nor staff. Returns the list, the underlying mod, and the list owner.
 *
 * @return array{0: ModList, 1: Mod, 2: User}
 */
function seedTombstoneOnList(): array
{
    $listOwner = User::factory()->create();
    $modOwner = User::factory()->create();
    $list = ModList::factory()->for($listOwner, 'owner')->public()->create();
    $mod = Mod::factory()->for($modOwner, 'owner')->create(['lists_disabled' => false]);

    resolve(ModListService::class)->addMod($list, $mod);

    $mod->lists_disabled = true;
    $mod->saveQuietly();
    dispatch_sync(new TombstoneModInListsJob($mod->id));

    return [$list->fresh(), $mod->fresh(), $listOwner];
}

/**
 * @return array{0: Mod, 1: ModVersion}
 */
function makeListOptOutMod(string $name, bool $listsDisabled = false): array
{
    $mod = Mod::factory()->create(['name' => $name, 'lists_disabled' => $listsDisabled]);
    $version = ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0',
        'spt_version_constraint' => '3.8.0',
    ]);

    return [$mod, $version];
}

function linkListOptOutDep(ModVersion $from, Mod $to): void
{
    Dependency::factory()->recycle([$from, $to])->create(['constraint' => '*']);
}
