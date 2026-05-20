<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\ModList;
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
