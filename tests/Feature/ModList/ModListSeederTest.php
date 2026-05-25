<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\SptVersion;
use App\Models\User;
use Database\Seeders\ModListSeeder;
use Illuminate\Database\Eloquent\Collection;

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
