<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Support\Api\V0\PublicViewpoint;
use App\Support\HomepageSectionCache;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component
{
    use ModeratesMod;

    /**
     * The number of mods or comments displayed in each homepage section.
     */
    private const int SECTION_SIZE = 6;

    /**
     * The number of ids cached per ordered section; the extras backfill items that become hidden during the cache TTL.
     */
    private const int SECTION_ID_BUFFER = 12;

    /**
     * Render the component.
     *
     * @return array<string, Collection<int, Mod>|Collection<int, Comment>>
     */
    public function with(): array
    {
        if ($this->viewDisabled()) {
            return [
                'featured' => $this->featured(),
                'newest' => $this->newest(),
                'updated' => $this->updated(),
                'recentComments' => $this->recentComments(),
            ];
        }

        return $this->publicSections();
    }

    /**
     * If the current user can view disabled mods.
     */
    protected function viewDisabled(): bool
    {
        return auth()->user()?->isModOrAdmin() ?? false;
    }

    /**
     * The four homepage sections for regular visitors, hydrated from the cached guest-viewpoint id sets.
     *
     * @return array<string, Collection<int, Mod>|Collection<int, Comment>>
     */
    protected function publicSections(): array
    {
        $featuredPool = $this->rememberSectionIds(
            HomepageSectionCache::FEATURED,
            fn (): SupportCollection => $this->featuredQuery(false)->pluck('mods.id'),
        );
        $newestIds = $this->rememberSectionIds(
            HomepageSectionCache::NEWEST,
            fn (): SupportCollection => $this->newestQuery(false)->limit(self::SECTION_ID_BUFFER)->pluck('mods.id'),
        );
        $updatedIds = $this->rememberSectionIds(
            HomepageSectionCache::UPDATED,
            fn (): SupportCollection => $this->updatedQuery(false)->limit(self::SECTION_ID_BUFFER)->pluck('mods.id'),
        );
        $commentIds = $this->rememberSectionIds(
            HomepageSectionCache::COMMENTS,
            fn (): SupportCollection => $this->recentCommentsQuery(null)->limit(self::SECTION_ID_BUFFER)->pluck('comments.id'),
        );

        $featuredIds = array_values(collect($featuredPool)->shuffle()->take(self::SECTION_SIZE)->all());

        $mods = $this->hydrateMods([...$featuredIds, ...$newestIds, ...$updatedIds]);

        return [
            'featured' => $this->pickMods($mods, $featuredIds),
            'newest' => $this->pickMods($mods, $newestIds),
            'updated' => $this->pickMods($mods, $updatedIds),
            'recentComments' => $this->hydrateComments($commentIds),
        ];
    }

    /**
     * Read a section's cached id list, computing it from the given query under the public viewpoint on a miss.
     *
     * @param  callable(): SupportCollection<array-key, mixed>  $query
     * @return list<int>
     */
    protected function rememberSectionIds(string $section, callable $query): array
    {
        return HomepageSectionCache::remember(
            $section,
            fn (): array => PublicViewpoint::run(fn (): array => $this->toIdList($query())),
        );
    }

    /**
     * Convert a plucked id column to a list of integers.
     *
     * @param  SupportCollection<array-key, mixed>  $ids
     * @return list<int>
     */
    protected function toIdList(SupportCollection $ids): array
    {
        $list = [];

        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $list[] = (int) $id;
            }
        }

        return $list;
    }

    /**
     * Load the given mods with the relationships the mod cards render, keyed by id.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Mod>
     */
    protected function hydrateMods(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection();
        }

        return Mod::query()
            ->whereIn('mods.id', array_values(array_unique($ids)))
            ->where('mods.disabled', false)
            ->with([
                'latestVersion',
                'latestVersion.latestSptVersion',
                'latestUpdatedVersion',
                'latestUpdatedVersion.latestSptVersion',
                'owner:id,name',
                'additionalAuthors:id,name',
                'license:id,name,link',
            ])
            ->get()
            ->keyBy('id');
    }

    /**
     * Build a section collection from the hydrated mods in the given id order, skipping ids that no longer resolve.
     *
     * @param  Collection<int, Mod>  $mods
     * @param  list<int>  $ids
     * @return Collection<int, Mod>
     */
    protected function pickMods(Collection $mods, array $ids): Collection
    {
        $picked = new Collection();

        foreach ($ids as $id) {
            $mod = $mods->get($id);

            if ($mod !== null) {
                $picked->push($mod);
            }
        }

        return $picked->take(self::SECTION_SIZE);
    }

    /**
     * Load the given comments with the relationships the activity feed renders, re-applying the visibility rules.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Comment>
     */
    protected function hydrateComments(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection();
        }

        return $this->recentCommentsQuery(null)
            ->whereIn('comments.id', $ids)
            ->limit(self::SECTION_SIZE)
            ->get();
    }

    /**
     * Featured mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    protected function featured(): Collection
    {
        return $this->featuredQuery(true)
            ->with(['latestVersion', 'latestVersion.latestSptVersion', 'owner:id,name', 'additionalAuthors:id,name', 'license:id,name,link'])
            ->withVisibilityFlags()
            ->inRandomOrder()
            ->limit(self::SECTION_SIZE)
            ->get();
    }

    /**
     * Base query for featured mods visible on the homepage.
     *
     * @return Builder<Mod>
     */
    protected function featuredQuery(bool $viewDisabled): Builder
    {
        return Mod::query()
            ->whereFeatured(true)
            ->whereHas('versions', function (Builder $query) use ($viewDisabled): void {
                $query->where('disabled', false);
                if (! $viewDisabled) {
                    $query->whereNotNull('published_at');
                }
            })
            ->unless($viewDisabled, fn (Builder $q): Builder => $q->where('mods.disabled', false));
    }

    /**
     * Newest mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    protected function newest(): Collection
    {
        return $this->newestQuery(true)
            ->with(['latestVersion', 'latestVersion.latestSptVersion', 'owner:id,name', 'additionalAuthors:id,name', 'license:id,name,link'])
            ->withVisibilityFlags()
            ->limit(self::SECTION_SIZE)
            ->get();
    }

    /**
     * Base query for the newest mods visible on the homepage, ordered by creation date.
     *
     * @return Builder<Mod>
     */
    protected function newestQuery(bool $viewDisabled): Builder
    {
        return Mod::query()
            ->whereHas('versions', function (Builder $query) use ($viewDisabled): void {
                $query->where('disabled', false);
                if (! $viewDisabled) {
                    $query->whereNotNull('published_at');
                }
            })
            ->unless($viewDisabled, fn (Builder $q): Builder => $q->where('mods.disabled', false))
            ->latest();
    }

    /**
     * Latest updated mods for the homepage listing.
     *
     * @return Collection<int, Mod>
     */
    protected function updated(): Collection
    {
        return $this->updatedQuery(true)
            ->with(['latestUpdatedVersion', 'latestUpdatedVersion.latestSptVersion', 'owner:id,name', 'additionalAuthors:id,name', 'license:id,name,link'])
            ->withVisibilityFlags()
            ->limit(self::SECTION_SIZE)
            ->get();
    }

    /**
     * Base query for the most recently updated mods visible on the homepage, ordered by latest version creation date.
     *
     * @return Builder<Mod>
     */
    protected function updatedQuery(bool $viewDisabled): Builder
    {
        return Mod::query()
            ->select('mods.*')
            ->join('mod_versions as latest_version', function (JoinClause $join) use ($viewDisabled): void {
                $join
                    ->on('latest_version.mod_id', '=', 'mods.id')
                    ->whereNotNull('latest_version.published_at')
                    ->where('latest_version.disabled', false)
                    ->whereExists(function (Illuminate\Database\Query\Builder $query) use ($viewDisabled): void {
                        $query->select(DB::raw(1))->from('mod_version_spt_version')->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')->whereColumn('mod_version_spt_version.mod_version_id', 'latest_version.id')->unless($viewDisabled, fn (Illuminate\Database\Query\Builder $q) => $q->where('spt_versions.version', '!=', '0.0.0'));
                    })
                    ->where('latest_version.created_at', '=', function (Illuminate\Database\Query\Builder $query): void {
                        $query->select(DB::raw('MAX(mv2.created_at)'))->from('mod_versions as mv2')->whereColumn('mv2.mod_id', 'mods.id')->whereNotNull('mv2.published_at')->where('mv2.disabled', false);
                    });
            })
            ->unless($viewDisabled, fn (Builder $q): Builder => $q->where('mods.disabled', false))
            ->latest('latest_version.created_at');
    }

    /**
     * Recent comments on mods for the homepage activity feed.
     *
     * @return Collection<int, Comment>
     */
    protected function recentComments(): Collection
    {
        return $this->recentCommentsQuery(auth()->user())
            ->limit(self::SECTION_SIZE)
            ->get();
    }

    /**
     * Base query for recent clean comments on publicly visible mods, newest first.
     *
     * @return Builder<Comment>
     */
    protected function recentCommentsQuery(?User $user): Builder
    {
        return Comment::query()
            ->clean()
            ->visibleToUser($user)
            ->whereNull('deleted_at')
            ->whereHasMorph('commentable', [Mod::class], function (Builder $query): void {
                $query->withoutGlobalScopes()
                    ->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->with([
                'user:id,name,user_role_id,profile_photo_path',
                'user.role:id,name,color_class,icon',
                'commentable',
            ])
            ->latest();
    }
};
