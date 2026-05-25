<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ListVisibility;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use App\Models\SptVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

use function Laravel\Prompts\progress;

final class ModListSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Cap on how many users get random non-default lists. Larger numbers slow
     * the seeder linearly with no real benefit for dev/test data.
     */
    private const int USER_SAMPLE_SIZE = 1000;

    /**
     * Cap on how many Favourites lists are populated with items.
     */
    private const int FAVOURITES_SAMPLE_SIZE = 100;

    /**
     * Percent of inserted non-default lists that get spawned forks. Tuned low so forks remain a minority of the seeded
     * list pool but show up often enough to exercise the provenance UI in dev.
     */
    private const int FORK_SOURCE_PERCENT = 15;

    /**
     * Hard cap on how many forks the seeder will create in a single run, so the cost stays bounded on large dev
     * databases.
     */
    private const int FORK_MAX_COUNT = 200;

    private const int LIST_INSERT_CHUNK = 500;

    private const int ITEM_INSERT_CHUNK = 1000;

    private int $maxItemsPerList;

    private int $noteMax;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();

        $userCount = User::query()->count();
        if ($userCount === 0) {
            return;
        }

        // Favourites lists are created explicitly because seeders run with model events disabled, bypassing the User
        // observer.
        $this->seedFavouritesLists();

        /** @var Collection<int, int> $modIds */
        $modIds = Mod::query()->where('disabled', false)->pluck('id')->values();

        /** @var Collection<int, int> $addonModMap addon id => parent mod id */
        $addonModMap = Addon::query()
            ->where('disabled', false)
            ->whereNull('detached_at')
            ->whereNotNull('mod_id')
            ->pluck('mod_id', 'id');

        /** @var Collection<int, int> $addonIds */
        $addonIds = $addonModMap->keys()->values();

        /** @var Collection<int, int> $sptVersionIds */
        $sptVersionIds = SptVersion::query()->pluck('id')->values();

        if ($modIds->isEmpty() && $addonIds->isEmpty()) {
            return;
        }

        /** @var Collection<int, int> $userIds */
        $userIds = User::query()
            ->inRandomOrder()
            ->limit(min(self::USER_SAMPLE_SIZE, $userCount))
            ->pluck('id')
            ->values();

        $this->maxItemsPerList = config()->integer('mod-lists.max_items_per_list', 250);
        $this->noteMax = config()->integer('mod-lists.validation.note_max', 280);

        $this->seedUserLists($userIds, $modIds, $addonIds, $addonModMap, $sptVersionIds);
        $this->seedFavouritesItems($userIds, $modIds, $addonIds, $addonModMap);
        $this->seedForks($userIds);
    }

    /**
     * Bulk-insert the immutable default Favourites list for every user that lacks one.
     *
     * The User observer creates this list on registration, but seeders run with
     * model events disabled, so it must be created explicitly here.
     */
    private function seedFavouritesLists(): void
    {
        $now = Date::now();
        $title = config()->string('mod-lists.favourites.title', 'Favourites');
        $slug = config()->string('mod-lists.favourites.slug', 'favourites');

        /** @var Collection<int, int> $existingOwnerIds */
        $existingOwnerIds = ModList::query()
            ->where('is_default', true)
            ->pluck('owner_id');

        $query = User::query()->orderBy('id');
        if ($existingOwnerIds->isNotEmpty()) {
            $query->whereNotIn('id', $existingOwnerIds);
        }

        /** @var Collection<int, int> $userIds */
        $userIds = $query->pluck('id');

        $rows = [];
        foreach ($userIds as $userId) {
            $rows[] = [
                'owner_id' => $userId,
                'title' => $title,
                'slug' => $slug,
                'description' => null,
                'description_html' => null,
                'visibility' => ListVisibility::Private->value,
                'spt_version_id' => null,
                'share_token' => null,
                'is_default' => true,
                'comments_disabled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, self::LIST_INSERT_CHUNK) as $chunk) {
            ModList::query()->insert($chunk);
        }
    }

    /**
     * Bulk-build and insert random non-default lists, then their items.
     *
     * Bypasses the ModList observer (slug uniqueness query, markdown render)
     * by computing a guaranteed-unique slug up-front and a trivial HTML wrap.
     *
     * @param  Collection<int, int>  $userIds
     * @param  Collection<int, int>  $modIds
     * @param  Collection<int, int>  $addonIds
     * @param  Collection<int, int>  $addonModMap  addon id => parent mod id
     * @param  Collection<int, int>  $sptVersionIds
     */
    private function seedUserLists(
        Collection $userIds,
        Collection $modIds,
        Collection $addonIds,
        Collection $addonModMap,
        Collection $sptVersionIds,
    ): void {
        $now = Date::now();

        $listRows = [];
        foreach ($userIds as $userId) {
            $count = $this->weightedListCount();
            for ($i = 0; $i < $count; $i++) {
                $listRows[] = $this->buildListRow($userId, $sptVersionIds, $now);
            }
        }

        if ($listRows === []) {
            return;
        }

        $listChunks = array_chunk($listRows, self::LIST_INSERT_CHUNK);
        progress(
            label: 'Inserting Mod Lists...',
            steps: $listChunks,
            callback: fn (array $chunk): bool => ModList::query()->insert($chunk),
        );

        // Look up the inserted IDs by (owner_id, slug) so we can attach items.
        $slugs = array_column($listRows, 'slug');

        /** @var array<string, int> $idLookup */
        $idLookup = ModList::query()
            ->whereIn('slug', $slugs)
            ->where('is_default', false)
            ->get(['id', 'owner_id', 'slug'])
            ->mapWithKeys(fn (ModList $list): array => [
                $list->owner_id.':'.$list->slug => $list->id,
            ])
            ->all();

        $itemRows = [];
        foreach ($listRows as $row) {
            $listId = $idLookup[$row['owner_id'].':'.$row['slug']] ?? null;
            if ($listId === null) {
                continue;
            }

            foreach ($this->buildItemRows($listId, $modIds, $addonIds, $addonModMap, $now, max: 25) as $item) {
                $itemRows[] = $item;
            }
        }

        if ($itemRows === []) {
            return;
        }

        $itemChunks = array_chunk($itemRows, self::ITEM_INSERT_CHUNK);
        progress(
            label: 'Inserting Mod List Items...',
            steps: $itemChunks,
            callback: fn (array $chunk): bool => ModListItem::query()->insert($chunk),
        );
    }

    /**
     * Sprinkle items into a small random sample of auto-created Favourites lists.
     *
     * @param  Collection<int, int>  $userIds
     * @param  Collection<int, int>  $modIds
     * @param  Collection<int, int>  $addonIds
     * @param  Collection<int, int>  $addonModMap  addon id => parent mod id
     */
    private function seedFavouritesItems(
        Collection $userIds,
        Collection $modIds,
        Collection $addonIds,
        Collection $addonModMap,
    ): void {
        $sampleOwnerIds = $userIds
            ->shuffle()
            ->take(min($userIds->count(), self::FAVOURITES_SAMPLE_SIZE));

        /** @var Collection<int, ModList> $favourites */
        $favourites = ModList::query()
            ->where('is_default', true)
            ->whereIn('owner_id', $sampleOwnerIds)
            ->get(['id']);

        if ($favourites->isEmpty()) {
            return;
        }

        $now = Date::now();
        $itemRows = [];
        foreach ($favourites as $favourite) {
            foreach ($this->buildItemRows($favourite->id, $modIds, $addonIds, $addonModMap, $now, max: 12) as $item) {
                $itemRows[] = $item;
            }
        }

        if ($itemRows === []) {
            return;
        }

        foreach (array_chunk($itemRows, self::ITEM_INSERT_CHUNK) as $chunk) {
            ModListItem::query()->insert($chunk);
        }
    }

    /**
     * Spawn forks of a random subset of the inserted non-default lists.
     *
     * Each fork copies the source's items verbatim (preserving position and note) and tracks the immediate parent via
     * forked_from_list_id, exercising both the provenance chip and the "Forked N times" badge in dev. Fork ownership is
     * picked at random from the same user pool used for the main list pass, occasionally landing on the source owner to
     * also exercise the "Duplicate" label path.
     *
     * @param  Collection<int, int>  $userIds
     */
    private function seedForks(Collection $userIds): void
    {
        if ($userIds->isEmpty()) {
            return;
        }

        /** @var Collection<int, ModList> $candidates */
        $candidates = ModList::query()
            ->where('is_default', false)
            ->whereNull('forked_from_list_id')
            ->whereExists(fn (Builder $query): Builder => $query
                ->from('mod_list_items')
                ->whereColumn('mod_list_items.mod_list_id', 'mod_lists.id'))
            ->get(['id', 'owner_id', 'title', 'description', 'spt_version_id']);

        if ($candidates->isEmpty()) {
            return;
        }

        $sources = $candidates
            ->shuffle()
            ->take((int) ceil($candidates->count() * self::FORK_SOURCE_PERCENT / 100));

        if ($sources->isEmpty()) {
            return;
        }

        $now = Date::now();

        /** @var array<int, array<int, array{listable_type: string, listable_id: int, note: string|null, position: int}>> $sourceItems */
        $sourceItems = ModListItem::query()
            ->whereIn('mod_list_id', $sources->pluck('id'))
            ->orderBy('position')
            ->orderBy('id')
            ->get(['mod_list_id', 'listable_type', 'listable_id', 'note', 'position'])
            ->groupBy('mod_list_id')
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (ModListItem $item): array => [
                    'listable_type' => $item->listable_type,
                    'listable_id' => $item->listable_id,
                    'note' => $item->note,
                    'position' => $item->position,
                ])
                ->all())
            ->all();

        $listRows = [];
        $sourceForSlug = [];
        $forkCount = 0;

        foreach ($sources as $source) {
            if ($forkCount >= self::FORK_MAX_COUNT) {
                break;
            }

            $items = $sourceItems[$source->id] ?? [];
            if ($items === []) {
                continue;
            }

            if (count($items) > $this->maxItemsPerList) {
                continue;
            }

            $forksForThisSource = $this->weightedForkCount();
            for ($i = 0; $i < $forksForThisSource && $forkCount < self::FORK_MAX_COUNT; $i++) {
                $row = $this->buildForkRow($source, $userIds->random(), $now);
                $listRows[] = $row;
                $sourceForSlug[$row['owner_id'].':'.$row['slug']] = $source->id;
                $forkCount++;
            }
        }

        if ($listRows === []) {
            return;
        }

        $listChunks = array_chunk($listRows, self::LIST_INSERT_CHUNK);
        progress(
            label: 'Inserting Forked Mod Lists...',
            steps: $listChunks,
            callback: fn (array $chunk): bool => ModList::query()->insert($chunk),
        );

        $slugs = array_column($listRows, 'slug');

        /** @var array<string, int> $idLookup */
        $idLookup = ModList::query()
            ->whereIn('slug', $slugs)
            ->whereNotNull('forked_from_list_id')
            ->get(['id', 'owner_id', 'slug'])
            ->mapWithKeys(fn (ModList $list): array => [
                $list->owner_id.':'.$list->slug => $list->id,
            ])
            ->all();

        $itemRows = [];
        foreach ($listRows as $row) {
            $forkId = $idLookup[$row['owner_id'].':'.$row['slug']] ?? null;
            $sourceId = $sourceForSlug[$row['owner_id'].':'.$row['slug']] ?? null;
            if ($forkId === null) {
                continue;
            }

            if ($sourceId === null) {
                continue;
            }

            foreach ($sourceItems[$sourceId] ?? [] as $item) {
                $itemRows[] = [
                    'mod_list_id' => $forkId,
                    'listable_type' => $item['listable_type'],
                    'listable_id' => $item['listable_id'],
                    'note' => $item['note'],
                    'position' => $item['position'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($itemRows === []) {
            return;
        }

        foreach (array_chunk($itemRows, self::ITEM_INSERT_CHUNK) as $chunk) {
            ModListItem::query()->insert($chunk);
        }
    }

    /**
     * Build a single fork ModList row ready for raw insert.
     *
     * Mirrors the runtime ModListService::forkList contract: the fork starts Private with comments disabled, carries
     * forked_from_list_id back to the source, and reuses the source's title (the random slug suffix keeps the
     * (owner_id, slug) uniqueness invariant intact). Visibility is drawn from the same weighted picker as regular lists
     * so the "Forked N times" badge has a meaningful non-zero count in dev for popular sources.
     *
     * @return array{
     *     owner_id: int,
     *     title: string,
     *     slug: string,
     *     description: string|null,
     *     description_html: string|null,
     *     visibility: string,
     *     spt_version_id: int|null,
     *     forked_from_list_id: int,
     *     share_token: string|null,
     *     is_default: bool,
     *     comments_disabled: bool,
     *     created_at: CarbonInterface,
     *     updated_at: CarbonInterface,
     * }
     */
    private function buildForkRow(ModList $source, int $newOwnerId, CarbonInterface $now): array
    {
        $title = $source->title;
        $slug = Str::slug($title).'-'.Str::lower(Str::random(6));
        $visibility = $this->randomVisibility();

        return [
            'owner_id' => $newOwnerId,
            'title' => $title,
            'slug' => $slug,
            'description' => $source->description,
            'description_html' => $source->description !== null && $source->description !== ''
                ? '<p>'.e($source->description).'</p>'
                : null,
            'visibility' => $visibility->value,
            'spt_version_id' => $source->spt_version_id,
            'forked_from_list_id' => $source->id,
            'share_token' => $visibility === ListVisibility::Hidden ? ModList::generateShareToken() : null,
            'is_default' => false,
            'comments_disabled' => $visibility === ListVisibility::Private,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Pick how many forks a single source list spawns. Heavy bias toward 1.
     */
    private function weightedForkCount(): int
    {
        $roll = random_int(1, 100);

        if ($roll <= 70) {
            return 1;
        }

        if ($roll <= 95) {
            return 2;
        }

        return 3;
    }

    /**
     * Build a single mod list row ready for raw insert.
     *
     * @param  Collection<int, int>  $sptVersionIds
     * @return array{
     *     owner_id: int,
     *     title: string,
     *     slug: string,
     *     description: string,
     *     description_html: string,
     *     visibility: string,
     *     spt_version_id: int|null,
     *     share_token: string|null,
     *     is_default: bool,
     *     comments_disabled: bool,
     *     created_at: CarbonInterface,
     *     updated_at: CarbonInterface,
     * }
     */
    private function buildListRow(int $userId, Collection $sptVersionIds, CarbonInterface $now): array
    {
        $title = Str::title(mb_rtrim($this->faker->sentence(random_int(2, 4)), '.'));
        // The 6-char random suffix makes collisions on (owner_id, slug) effectively impossible
        // without needing the observer's per-row uniqueness lookup.
        $slug = Str::slug($title).'-'.Str::lower(Str::random(6));
        $description = $this->faker->paragraph(random_int(1, 3));
        $visibility = $this->randomVisibility();

        return [
            'owner_id' => $userId,
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'description_html' => '<p>'.e($description).'</p>',
            'visibility' => $visibility->value,
            'spt_version_id' => $this->maybe(30) ? $sptVersionIds->random() : null,
            'share_token' => $visibility === ListVisibility::Hidden ? ModList::generateShareToken() : null,
            'is_default' => false,
            'comments_disabled' => $this->maybe(20),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Build raw item rows for a list.
     *
     * Mirrors the real add-to-list flow: every addon's parent mod is added
     * alongside the addon (the modal forces this via ParentModMissingException),
     * so seed data exercises the same group-anchor layout as production lists.
     *
     * @param  Collection<int, int>  $modIds
     * @param  Collection<int, int>  $addonIds
     * @param  Collection<int, int>  $addonModMap  addon id => parent mod id
     * @return array<int, array<string, mixed>>
     */
    private function buildItemRows(
        int $listId,
        Collection $modIds,
        Collection $addonIds,
        Collection $addonModMap,
        CarbonInterface $now,
        int $max = 25,
    ): array {
        $targetItemCount = min(random_int(3, $max), $this->maxItemsPerList);

        // Roughly 70% mods / 30% addons when both pools exist.
        $modShare = $modIds->isNotEmpty() && $addonIds->isNotEmpty()
            ? (int) round($targetItemCount * 0.7)
            : $targetItemCount;
        $addonShare = $targetItemCount - $modShare;

        if ($modIds->isEmpty()) {
            $modShare = 0;
            $addonShare = $targetItemCount;
        } elseif ($addonIds->isEmpty()) {
            $addonShare = 0;
            $modShare = $targetItemCount;
        }

        $rows = [];
        $position = 0;
        $addedModIds = [];

        if ($modShare > 0) {
            foreach ($modIds->shuffle()->take($modShare) as $modId) {
                $rows[] = $this->modRow($listId, $modId, $position++, $now);
                $addedModIds[$modId] = true;
            }
        }

        if ($addonShare > 0) {
            $remaining = $this->maxItemsPerList - count($rows);
            foreach ($addonIds->shuffle()->take($addonShare) as $addonId) {
                $parentModId = $addonModMap->get($addonId);
                if ($parentModId === null) {
                    // The $addonModMap pre-filter excludes addons without a parent mod, so this branch is unreachable
                    // in practice. Skip defensively rather than seed a parent-less addon.
                    continue;
                }

                $needsParent = ! isset($addedModIds[$parentModId]);
                $slotsNeeded = $needsParent ? 2 : 1;

                if ($remaining < $slotsNeeded) {
                    // Not enough capacity for both this addon and its parent (or just the addon itself). Skip this
                    // candidate and try the next one rather than aborting the loop, which keeps the no-orphan
                    // invariant intact without giving up on the remaining addon budget.
                    continue;
                }

                if ($needsParent) {
                    $rows[] = $this->modRow($listId, $parentModId, $position++, $now);
                    $addedModIds[$parentModId] = true;
                    $remaining--;
                }

                $rows[] = [
                    'mod_list_id' => $listId,
                    'listable_type' => Addon::class,
                    'listable_id' => $addonId,
                    'note' => $this->maybe(20) ? $this->shortNote() : null,
                    'position' => $position++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $remaining--;
            }
        }

        return $rows;
    }

    /**
     * Build a single Mod list-item row.
     *
     * @return array<string, mixed>
     */
    private function modRow(int $listId, int $modId, int $position, CarbonInterface $now): array
    {
        return [
            'mod_list_id' => $listId,
            'listable_type' => Mod::class,
            'listable_id' => $modId,
            'note' => $this->maybe(30) ? $this->shortNote() : null,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Pick a list count weighted heavily toward 0-1 so the sample stays small.
     */
    private function weightedListCount(): int
    {
        $roll = random_int(1, 100);

        if ($roll <= 35) {
            return 0;
        }

        if ($roll <= 65) {
            return 1;
        }

        if ($roll <= 85) {
            return 2;
        }

        if ($roll <= 95) {
            return 3;
        }

        return 4;
    }

    /**
     * Pick a visibility weighted towards Public.
     */
    private function randomVisibility(): ListVisibility
    {
        $roll = random_int(1, 100);

        if ($roll <= 75) {
            return ListVisibility::Public;
        }

        if ($roll <= 90) {
            return ListVisibility::Hidden;
        }

        return ListVisibility::Private;
    }

    /**
     * Build a short note that respects the configured maximum length.
     */
    private function shortNote(): string
    {
        $note = $this->faker->sentence(random_int(4, 12));

        return mb_substr($note, 0, $this->noteMax);
    }

    /**
     * Coin flip for the given probability percentage (1-100).
     */
    private function maybe(int $percent): bool
    {
        return random_int(1, 100) <= $percent;
    }
}
