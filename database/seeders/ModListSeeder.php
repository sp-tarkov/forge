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

        // Favourites lists are created explicitly because seeders run with model events disabled, bypassing the User observer.
        $this->seedFavouritesLists();

        /** @var Collection<int, int> $modIds */
        $modIds = Mod::query()->where('disabled', false)->pluck('id')->values();

        /** @var Collection<int, int> $addonIds */
        $addonIds = Addon::query()
            ->where('disabled', false)
            ->whereNull('detached_at')
            ->pluck('id')
            ->values();

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

        $this->seedUserLists($userIds, $modIds, $addonIds, $sptVersionIds);
        $this->seedFavouritesItems($userIds, $modIds, $addonIds);
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
     * @param  Collection<int, int>  $sptVersionIds
     */
    private function seedUserLists(
        Collection $userIds,
        Collection $modIds,
        Collection $addonIds,
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

            foreach ($this->buildItemRows($listId, $modIds, $addonIds, $now, max: 25) as $item) {
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
     */
    private function seedFavouritesItems(
        Collection $userIds,
        Collection $modIds,
        Collection $addonIds,
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
            foreach ($this->buildItemRows($favourite->id, $modIds, $addonIds, $now, max: 12) as $item) {
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
     * @param  Collection<int, int>  $modIds
     * @param  Collection<int, int>  $addonIds
     * @return array<int, array<string, mixed>>
     */
    private function buildItemRows(
        int $listId,
        Collection $modIds,
        Collection $addonIds,
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

        if ($modShare > 0) {
            foreach ($modIds->shuffle()->take($modShare) as $modId) {
                $rows[] = [
                    'mod_list_id' => $listId,
                    'listable_type' => Mod::class,
                    'listable_id' => $modId,
                    'note' => $this->maybe(30) ? $this->shortNote() : null,
                    'position' => $position++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($addonShare > 0) {
            foreach ($addonIds->shuffle()->take($addonShare) as $addonId) {
                $rows[] = [
                    'mod_list_id' => $listId,
                    'listable_type' => Addon::class,
                    'listable_id' => $addonId,
                    'note' => $this->maybe(20) ? $this->shortNote() : null,
                    'position' => $position++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
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
