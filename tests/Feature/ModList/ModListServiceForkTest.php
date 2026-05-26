<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Exceptions\ModListCapacityExceededException;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\SptVersion;
use App\Models\User;
use App\Services\ModListService;
use Illuminate\Support\Facades\Storage;

describe('ModListService forkList', function (): void {
    it('creates a new list owned by the actor with the given title', function (): void {
        $sourceOwner = User::factory()->create();
        $actor = User::factory()->create();
        $source = ModList::factory()->for($sourceOwner, 'owner')->public()->create();

        $fork = resolve(ModListService::class)->forkList($actor, $source, 'My Forked List');

        expect($fork->owner_id)->toBe($actor->id);
        expect($fork->title)->toBe('My Forked List');
        expect($fork->forked_from_list_id)->toBe($source->id);
    });

    it('starts the new list as Private with comments disabled regardless of source visibility', function (): void {
        $source = ModList::factory()->public()->create(['comments_disabled' => false]);
        $actor = User::factory()->create();

        $fork = resolve(ModListService::class)->forkList($actor, $source, 'Fork');

        expect($fork->visibility)->toBe(ListVisibility::Private);
        expect($fork->comments_disabled)->toBeTrue();
        expect($fork->share_token)->toBeNull();
    });

    it('copies description and spt target from the source', function (): void {
        $spt = SptVersion::factory()->create(['version' => '3.11.0']);
        $source = ModList::factory()->public()->create([
            'description' => 'A curated list.',
            'spt_version_id' => $spt->id,
        ]);
        $actor = User::factory()->create();

        $fork = resolve(ModListService::class)->forkList($actor, $source, 'Fork');

        expect($fork->description)->toBe('A curated list.');
        expect($fork->spt_version_id)->toBe($spt->id);
    });

    it('copies items preserving listable, position, and note', function (): void {
        $source = ModList::factory()->public()->create();
        $modA = Mod::factory()->create();
        $modB = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $modA->id]);

        ModListItem::factory()->create([
            'mod_list_id' => $source->id,
            'listable_type' => Mod::class,
            'listable_id' => $modA->id,
            'note' => 'Pinned',
            'position' => 1,
        ]);
        ModListItem::factory()->create([
            'mod_list_id' => $source->id,
            'listable_type' => Addon::class,
            'listable_id' => $addon->id,
            'note' => null,
            'position' => 2,
        ]);
        ModListItem::factory()->create([
            'mod_list_id' => $source->id,
            'listable_type' => Mod::class,
            'listable_id' => $modB->id,
            'note' => 'Recommended',
            'position' => 3,
        ]);

        $actor = User::factory()->create();
        $fork = resolve(ModListService::class)->forkList($actor, $source, 'Fork');

        $items = $fork->items()->get();
        expect($items->count())->toBe(3);

        $modAItem = $items->firstWhere(fn (ModListItem $item): bool => $item->listable_type === Mod::class && $item->listable_id === $modA->id);
        expect($modAItem?->note)->toBe('Pinned');
        expect($modAItem?->position)->toBe(1);

        $addonItem = $items->firstWhere(fn (ModListItem $item): bool => $item->listable_type === Addon::class && $item->listable_id === $addon->id);
        expect($addonItem?->note)->toBeNull();
        expect($addonItem?->position)->toBe(2);

        $modBItem = $items->firstWhere(fn (ModListItem $item): bool => $item->listable_type === Mod::class && $item->listable_id === $modB->id);
        expect($modBItem?->note)->toBe('Recommended');
        expect($modBItem?->position)->toBe(3);
    });

    it('tracks the immediate parent so forks of forks chain', function (): void {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $listA = ModList::factory()->for($userA, 'owner')->public()->create();
        $listB = resolve(ModListService::class)->forkList($userB, $listA, 'B fork');
        $listC = resolve(ModListService::class)->forkList($userC, $listB, 'C fork');

        expect($listB->forked_from_list_id)->toBe($listA->id);
        expect($listC->forked_from_list_id)->toBe($listB->id);
    });

    it('produces a non-default list when forking the actors own Favourites', function (): void {
        $user = User::factory()->create();
        $favourites = $user->favouritesList;

        Mod::factory()->count(2)->create()->each(function (Mod $mod) use ($favourites): void {
            ModListItem::factory()->create([
                'mod_list_id' => $favourites->id,
                'listable_type' => Mod::class,
                'listable_id' => $mod->id,
            ]);
        });

        $fork = resolve(ModListService::class)->forkList($user, $favourites, 'My Favs Snapshot');

        expect($fork->is_default)->toBeFalse();
        expect($fork->visibility)->toBe(ListVisibility::Private);
        expect($fork->itemCount())->toBe(2);
    });

    it('throws when the source has more items than the configured per-list cap', function (): void {
        $source = ModList::factory()->public()->create();
        Mod::factory()->count(3)->create()->each(function (Mod $mod, int $index) use ($source): void {
            ModListItem::factory()->create([
                'mod_list_id' => $source->id,
                'listable_type' => Mod::class,
                'listable_id' => $mod->id,
                'position' => $index,
            ]);
        });

        config()->set('mod-lists.max_items_per_list', 2);

        $actor = User::factory()->create();

        expect(fn () => resolve(ModListService::class)->forkList($actor, $source, 'Fork'))
            ->toThrow(ModListCapacityExceededException::class);

        expect(ModList::query()->where('forked_from_list_id', $source->id)->count())->toBe(0);
    });

    it('copies the thumbnail file to a new path', function (): void {
        $disk = config()->string('filesystems.asset_upload', 'public');
        Storage::fake($disk);
        Storage::disk($disk)->put('list-thumbnails/source.jpg', 'binary');

        $source = ModList::factory()->public()->create([
            'thumbnail' => 'list-thumbnails/source.jpg',
            'thumbnail_hash' => 'abc123',
        ]);
        $actor = User::factory()->create();

        $fork = resolve(ModListService::class)->forkList($actor, $source, 'Fork');

        expect($fork->thumbnail)->not->toBe('list-thumbnails/source.jpg');
        expect($fork->thumbnail)->not->toBeNull();
        expect(Storage::disk($disk)->exists((string) $fork->thumbnail))->toBeTrue();
        expect(Storage::disk($disk)->exists('list-thumbnails/source.jpg'))->toBeTrue();
        expect($fork->thumbnail_hash)->toBe('abc123');
    });

    it('falls back to no thumbnail when the source file is missing on disk', function (): void {
        $disk = config()->string('filesystems.asset_upload', 'public');
        Storage::fake($disk);

        $source = ModList::factory()->public()->create([
            'thumbnail' => 'list-thumbnails/missing.jpg',
            'thumbnail_hash' => 'abc123',
        ]);
        $actor = User::factory()->create();

        $fork = resolve(ModListService::class)->forkList($actor, $source, 'Fork');

        expect($fork->thumbnail)->toBeNull();
    });
});
