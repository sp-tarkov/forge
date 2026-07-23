<?php

declare(strict_types=1);

use App\Jobs\UpdateFavouritesJob;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;

it('recalculates the denormalized favourite count for each mod', function (): void {
    $modUnfavourited = Mod::factory()->create(['favourites_count' => 99]);

    $modFavouritedOnce = Mod::factory()->create();
    ModListItem::factory()->for(ModList::factory()->favourites(), 'modList')->create(['listable_id' => $modFavouritedOnce->id]);

    $modFavouritedTwice = Mod::factory()->create();
    ModListItem::factory()->for(ModList::factory()->favourites(), 'modList')->create(['listable_id' => $modFavouritedTwice->id]);
    ModListItem::factory()->for(ModList::factory()->favourites(), 'modList')->create(['listable_id' => $modFavouritedTwice->id]);

    (new UpdateFavouritesJob)->handle();

    expect($modUnfavourited->refresh()->favourites_count)->toBe(0)
        ->and($modFavouritedOnce->refresh()->favourites_count)->toBe(1)
        ->and($modFavouritedTwice->refresh()->favourites_count)->toBe(2);
});

it('ignores non-favourite lists, disabled lists, and tombstoned items', function (): void {
    $mod = Mod::factory()->create();

    ModListItem::factory()->for(ModList::factory()->favourites(), 'modList')->create(['listable_id' => $mod->id]);

    ModListItem::factory()->for(ModList::factory(), 'modList')->create(['listable_id' => $mod->id]);
    ModListItem::factory()->for(ModList::factory()->favourites()->disabled(), 'modList')->create(['listable_id' => $mod->id]);
    ModListItem::factory()->for(ModList::factory()->favourites(), 'modList')->create(['listable_id' => $mod->id, 'tombstoned_at' => now()]);

    (new UpdateFavouritesJob)->handle();

    expect($mod->refresh()->favourites_count)->toBe(1);
});

it('does not touch the mod updated_at timestamp', function (): void {
    $mod = Mod::factory()->create(['updated_at' => now()->subDays(10)]);
    ModListItem::factory()->for(ModList::factory()->favourites(), 'modList')->create(['listable_id' => $mod->id]);

    $originalUpdatedAt = $mod->updated_at;

    (new UpdateFavouritesJob)->handle();

    $mod->refresh();

    expect($mod->favourites_count)->toBe(1)
        ->and($mod->updated_at?->toIso8601String())->toBe($originalUpdatedAt?->toIso8601String());
});
