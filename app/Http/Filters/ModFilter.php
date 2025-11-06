<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Enums\FikaCompatibilityStatus;
use App\Models\Mod;
use App\Models\SptVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ModFilter
{
    /**
     * The query builder instance for the mod model.
     *
     * @var Builder<Mod>
     */
    protected Builder $builder;

    /**
     * Create a new ModFilter instance.
     */
    public function __construct(
        /**
         * The filters to apply to the query.
         *
         * @var array<string, mixed>
         */
        protected array $filters
    ) {
        $this->builder = $this->baseQuery();
    }

    /**
     * Apply the filters to the query.
     *
     * @return Builder<Mod>
     */
    public function apply(): Builder
    {
        if (! $this->hasVersionFilter()) {
            // Only call if we're not applying a specific version filter
            $this->addBaseExistsClause();
        }

        foreach ($this->filters as $method => $value) {
            if (method_exists($this, $method) && ! empty($value)) {
                $this->$method($value);
            }
        }

        return $this->builder;
    }

    /**
     * The base query for the mod listing.
     *
     * @return Builder<Mod>
     */
    private function baseQuery(): Builder
    {
        $showDisabled = auth()->user()?->isModOrAdmin() ?? false;

        return Mod::query()
            ->select('mods.*')
            ->unless($showDisabled, fn (Builder $query) => $query->where('mods.disabled', false));
    }

    /**
     * Check if the filters include a version filter.
     */
    private function hasVersionFilter(): bool
    {
        $versions = $this->filters['sptVersions'] ?? null;

        return ! empty($versions) && $versions !== 'all';
    }

    /**
     * Filter the results by the given search term.
     *
     * @return Builder<Mod>
     */
    private function query(mixed $term): Builder
    {
        if (! is_string($term)) {
            return $this->builder;
        }

        return $this->builder->whereLike('mods.name', sprintf('%%%s%%', $term));
    }

    /**
     * Add the base exists clause. Used when mod versions are being filtered.
     */
    private function addBaseExistsClause(): void
    {
        $showDisabled = auth()->user()?->isModOrAdmin() ?? false;

        $this->builder->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
            $query->select(DB::raw(1))
                ->from('mod_versions')
                ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
                ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                ->whereColumn('mod_versions.mod_id', 'mods.id')
                ->unless($showDisabled, fn (QueryBuilder $query) => $query->where('spt_versions.version', '!=', '0.0.0'))
                ->unless($showDisabled, fn (QueryBuilder $query) => $query->where('mod_versions.disabled', false))
                ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'))
                ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('spt_versions.publish_date')
                    ->where('spt_versions.publish_date', '<=', now()));
        });
    }

    /**
     * Order the query by the given type.
     *
     * @return Builder<Mod>
     */
    private function order(mixed $type): Builder
    {
        if (! is_string($type)) {
            return $this->builder;
        }

        return match ($type) {
            'updated' => $this->orderByLatestVersionCreatedAt(),
            'downloaded' => $this->builder->orderByDesc('mods.downloads'),
            default => $this->builder->latest('mods.created_at'),
        };
    }

    /**
     * Order mods by their latest version's created_at date.
     *
     * @return Builder<Mod>
     */
    private function orderByLatestVersionCreatedAt(): Builder
    {
        $showDisabled = auth()->user()?->isModOrAdmin() ?? false;

        return $this->builder
            ->leftJoin('mod_versions as latest_versions', function (JoinClause $join) use ($showDisabled): void {
                $join->on('latest_versions.mod_id', '=', 'mods.id')
                    ->whereNotNull('latest_versions.published_at')
                    ->where('latest_versions.disabled', false)
                    ->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
                        $query->select(DB::raw(1))
                            ->from('mod_version_spt_version')
                            ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                            ->whereColumn('mod_version_spt_version.mod_version_id', 'latest_versions.id')
                            ->unless($showDisabled, fn (QueryBuilder $q) => $q->where('spt_versions.version', '!=', '0.0.0'))
                            ->unless($showDisabled, fn (QueryBuilder $q) => $q->whereNotNull('spt_versions.publish_date')
                                ->where('spt_versions.publish_date', '<=', now()));
                    })
                    ->where('latest_versions.created_at', '=', function (QueryBuilder $query): void {
                        $query->select(DB::raw('MAX(mv2.created_at)'))
                            ->from('mod_versions as mv2')
                            ->whereColumn('mv2.mod_id', 'mods.id')
                            ->whereNotNull('mv2.published_at')
                            ->where('mv2.disabled', false);
                    });
            })
            ->latest('latest_versions.created_at');
    }

    /**
     * Filter the results by the featured status.
     *
     * @return Builder<Mod>
     */
    private function featured(mixed $option): Builder
    {
        if (! is_string($option)) {
            return $this->builder;
        }

        return match ($option) {
            'exclude' => $this->builder->where('mods.featured', false),
            'only' => $this->builder->where('mods.featured', true),
            default => $this->builder,
        };
    }

    /**
     * Filter the results by category slug.
     *
     * @return Builder<Mod>
     */
    private function category(mixed $categorySlug): Builder
    {
        if (! is_string($categorySlug) || empty($categorySlug)) {
            return $this->builder;
        }

        return $this->builder->whereHas('category', function (Builder $query) use ($categorySlug): void {
            $query->where('slug', $categorySlug);
        });
    }

    /**
     * Filter the results by Fika compatibility status.
     * When true, only show Fika compatible mods.
     *
     * @return Builder<Mod>
     */
    private function fikaCompatibility(mixed $option): Builder
    {
        // Only filter when explicitly set to true (checkbox checked)
        if ($option !== true) {
            return $this->builder;
        }

        $showDisabled = auth()->user()?->isModOrAdmin() ?? false;

        return $this->builder->whereHas('versions', function (Builder $query) use ($showDisabled): void {
            $query->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->unless($showDisabled, fn (Builder $q): Builder => $q->where('disabled', false))
                ->where('fika_compatibility_status', FikaCompatibilityStatus::Compatible->value);
        });
    }

    /**
     * Filter the results to specific SPT versions.
     *
     * @param  string|array<int, string>  $versions
     * @return Builder<Mod>
     */
    private function sptVersions(mixed $versions): Builder
    {
        // If versions is "all" or empty, no specific version filtering needed
        if ($versions === 'all' || empty($versions)) {
            return $this->builder;
        }

        // If versions are "legacy", convert it to an array
        if ($versions === 'legacy') {
            $versions = ['legacy'];
        }

        // If versions is not an array at this point, don't apply filtering
        if (! is_array($versions)) {
            return $this->builder;
        }

        $showDisabled = auth()->user()?->isModOrAdmin() ?? false;
        $hasLegacyVersion = in_array('legacy', $versions);
        $normalVersions = array_filter($versions, fn (string $version): bool => $version !== 'legacy');

        // Both normal versions and legacy
        if (! empty($normalVersions) && $hasLegacyVersion) {
            return $this->builder->whereExists(function (QueryBuilder $subQuery) use ($normalVersions, $showDisabled): void {
                $subQuery->select(DB::raw(1))
                    ->from('mod_versions')
                    ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
                    ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                    ->whereColumn('mod_versions.mod_id', 'mods.id')
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->where('mod_versions.disabled', false))
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'))
                    ->where(function (QueryBuilder $query) use ($normalVersions, $showDisabled): void {
                        // Include normal versions
                        $query->whereIn('spt_versions.version', $normalVersions)
                            ->where('spt_versions.version', '!=', '0.0.0')
                            ->unless($showDisabled, fn (QueryBuilder $q) => $q->whereNotNull('spt_versions.publish_date')
                                ->where('spt_versions.publish_date', '<=', now()));

                        // Include legacy versions
                        $activeSptVersions = $this->getActiveSptVersions($showDisabled);
                        $query->orWhere(function (QueryBuilder $q) use ($activeSptVersions, $showDisabled): void {
                            $q->whereNotIn('spt_versions.version', $activeSptVersions);
                            if (! $showDisabled) {
                                $q->where('spt_versions.version', '!=', '0.0.0')
                                    ->whereNotNull('spt_versions.publish_date')
                                    ->where('spt_versions.publish_date', '<=', now());
                            }
                        });
                    });
            });
        }

        // Only legacy versions
        if (empty($normalVersions) && $hasLegacyVersion) {
            return $this->builder->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
                $this->legacyVersions($query, $showDisabled);
            });
        }

        // Only normal versions
        if (! empty($normalVersions) && ! $hasLegacyVersion) {
            return $this->builder->whereExists(function (QueryBuilder $query) use ($normalVersions, $showDisabled): void {
                $query->select(DB::raw(1))
                    ->from('mod_versions')
                    ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
                    ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                    ->whereColumn('mod_versions.mod_id', 'mods.id')
                    ->whereIn('spt_versions.version', $normalVersions)
                    ->where('spt_versions.version', '!=', '0.0.0')
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->where('mod_versions.disabled', false))
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'))
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('spt_versions.publish_date')
                        ->where('spt_versions.publish_date', '<=', now()));
            });
        }

        return $this->builder;
    }

    /**
     * Build the query for normal SPT versions.
     *
     * @param  array<int, string>  $normalVersions
     */
    private function normalVersions(QueryBuilder $query, array $normalVersions, bool $showDisabled): void
    {
        $query->select(DB::raw(1))
            ->from('mod_versions')
            ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
            ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
            ->whereColumn('mod_versions.mod_id', 'mods.id')
            ->whereIn('spt_versions.version', $normalVersions)
            ->where('spt_versions.version', '!=', '0.0.0')
            ->unless($showDisabled, fn (QueryBuilder $query) => $query->where('mod_versions.disabled', false))
            ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'));
    }

    /**
     * Build the query for legacy versions (versions not in the current active list).
     */
    private function legacyVersions(QueryBuilder $query, bool $showDisabled): void
    {
        // Get the active SPT versions that are shown in the filter
        $activeSptVersions = $this->getActiveSptVersions($showDisabled);

        $query->select(DB::raw(1))
            ->from('mod_versions')
            ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
            ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
            ->whereColumn('mod_versions.mod_id', 'mods.id')
            ->whereNotIn('spt_versions.version', $activeSptVersions)
            ->when(
                $showDisabled,
                // Admin can see 0.0.0 versions in legacy filter
                fn (QueryBuilder $query): QueryBuilder => $query,
                // Regular users cannot see 0.0.0 versions and unpublished SPT versions
                fn (QueryBuilder $query) => $query->where('spt_versions.version', '!=', '0.0.0')
                    ->whereNotNull('spt_versions.publish_date')
                    ->where('spt_versions.publish_date', '<=', now())
            )
            ->unless($showDisabled, fn (QueryBuilder $query) => $query->where('mod_versions.disabled', false))
            ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'));
    }

    /**
     * Get the active SPT versions with role-based caching.
     *
     * @return array<int, string>
     */
    private function getActiveSptVersions(bool $isAdmin): array
    {
        $cacheKey = $isAdmin ? 'spt-versions:active:admin' : 'spt-versions:active:user';

        return Cache::flexible(
            $cacheKey,
            [5 * 60, 10 * 60], // 5 minutes stale, 10 minutes expire
            fn (): array => SptVersion::getVersionsForLastThreeMinors($isAdmin)->pluck('version')->toArray()
        );
    }
}
