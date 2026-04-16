<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Enums\FikaCompatibility;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ModFilter
{
    /**
     * The allowed filter method names that can be called from user input.
     *
     * @var array<int, string>
     */
    private const array ALLOWED_FILTERS = [
        'query',
        'order',
        'featured',
        'category',
        'fikaCompatibility',
        'sptVersions',
    ];

    /**
     * The query builder instance for the mod model.
     *
     * @var Builder<Mod>
     */
    private readonly Builder $builder;

    /**
     * Create a new ModFilter instance.
     */
    public function __construct(
        /**
         * The filters to apply to the query.
         *
         * @var array<string, mixed>
         */
        private array $filters
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
            if (in_array($method, self::ALLOWED_FILTERS, true) && ! empty($value)) {
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
            ->addSelect([
                'latest_version_created_at' => ModVersion::query()
                    ->select('mod_versions.created_at')
                    ->whereColumn('mod_versions.mod_id', 'mods.id')
                    ->whereNotNull('mod_versions.published_at')
                    ->where('mod_versions.disabled', false)
                    ->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
                        $query->select(DB::raw(1))
                            ->from('mod_version_spt_version')
                            ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                            ->whereColumn('mod_version_spt_version.mod_version_id', 'mod_versions.id')
                            ->unless($showDisabled, fn (QueryBuilder $q) => $q->whereNotNull('spt_versions.publish_date')
                                ->where('spt_versions.publish_date', '<=', now()));
                    })
                    ->latest('mod_versions.created_at')
                    ->limit(1),
            ])
            ->latest('latest_version_created_at');
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
        if (! is_string($categorySlug) || ($categorySlug === '' || $categorySlug === '0')) {
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
                ->where('fika_compatibility', FikaCompatibility::Compatible->value);
        });
    }

    /**
     * Filter the results to specific SPT versions.
     *
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
        $normalVersions = array_filter($versions, fn (mixed $version): bool => $version !== 'legacy');

        // Both normal versions and legacy
        if ($normalVersions !== [] && $hasLegacyVersion) {
            return $this->builder->whereExists(function (QueryBuilder $subQuery) use ($normalVersions, $showDisabled): void {
                $subQuery->select(DB::raw(1))
                    ->from('mod_versions')
                    ->whereColumn('mod_versions.mod_id', 'mods.id')
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->where('mod_versions.disabled', false))
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'))
                    ->where(function (QueryBuilder $query) use ($normalVersions, $showDisabled): void {
                        // True legacy versions (empty constraint)
                        $query->where('mod_versions.spt_version_constraint', '');

                        // OR normal versions with specific SPT versions
                        $query->orWhereExists(function (QueryBuilder $sptQuery) use ($normalVersions, $showDisabled): void {
                            $sptQuery->select(DB::raw(1))
                                ->from('mod_version_spt_version')
                                ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                                ->whereColumn('mod_version_spt_version.mod_version_id', 'mod_versions.id')
                                ->whereIn('spt_versions.version', $normalVersions)
                                ->unless($showDisabled, fn (QueryBuilder $q) => $q->whereNotNull('spt_versions.publish_date')
                                    ->where('spt_versions.publish_date', '<=', now()));
                        });

                        // OR older SPT versions (legacy SPT compatibility)
                        $activeSptVersions = $this->getActiveSptVersions($showDisabled);
                        $query->orWhereExists(function (QueryBuilder $oldSptQuery) use ($activeSptVersions, $showDisabled): void {
                            $oldSptQuery->select(DB::raw(1))
                                ->from('mod_version_spt_version')
                                ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                                ->whereColumn('mod_version_spt_version.mod_version_id', 'mod_versions.id')
                                ->whereNotIn('spt_versions.version', $activeSptVersions)
                                ->unless($showDisabled, fn (QueryBuilder $q) => $q->whereNotNull('spt_versions.publish_date')
                                    ->where('spt_versions.publish_date', '<=', now()));
                        });
                    });
            });
        }

        // Only legacy versions
        if ($normalVersions === [] && $hasLegacyVersion) {
            return $this->builder->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
                $this->legacyVersions($query, $showDisabled);
            });
        }

        // Only normal versions
        if ($normalVersions !== [] && ! $hasLegacyVersion) {
            return $this->builder->whereExists(function (QueryBuilder $query) use ($normalVersions, $showDisabled): void {
                $query->select(DB::raw(1))
                    ->from('mod_versions')
                    ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
                    ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                    ->whereColumn('mod_versions.mod_id', 'mods.id')
                    ->whereIn('spt_versions.version', $normalVersions)
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->where('mod_versions.disabled', false))
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'))
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('spt_versions.publish_date')
                        ->where('spt_versions.publish_date', '<=', now()));
            });
        }

        return $this->builder;
    }

    /**
     * Build the query for legacy versions.
     * Includes both true legacy versions (empty spt_version_constraint) and versions with older SPT compatibility.
     */
    private function legacyVersions(QueryBuilder $query, bool $showDisabled): void
    {
        // Get the active SPT versions that are shown in the filter
        $activeSptVersions = $this->getActiveSptVersions($showDisabled);

        $query->select(DB::raw(1))
            ->from('mod_versions')
            ->whereColumn('mod_versions.mod_id', 'mods.id')
            ->unless($showDisabled, fn (QueryBuilder $q) => $q->where('mod_versions.disabled', false))
            ->unless($showDisabled, fn (QueryBuilder $q) => $q->whereNotNull('mod_versions.published_at'))
            ->where(function (QueryBuilder $q) use ($activeSptVersions, $showDisabled): void {
                // True legacy: empty SPT version constraint
                $q->where('mod_versions.spt_version_constraint', '')
                // OR older SPT versions (existing behavior)
                    ->orWhereExists(function (QueryBuilder $sptQuery) use ($activeSptVersions, $showDisabled): void {
                        $sptQuery->select(DB::raw(1))
                            ->from('mod_version_spt_version')
                            ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                            ->whereColumn('mod_version_spt_version.mod_version_id', 'mod_versions.id')
                            ->whereNotIn('spt_versions.version', $activeSptVersions)
                            ->unless($showDisabled, fn (QueryBuilder $q) => $q->whereNotNull('spt_versions.publish_date')
                                ->where('spt_versions.publish_date', '<=', now()));
                    });
            });
    }

    /**
     * Get the active SPT versions with role-based caching.
     *
     * @return array<int, string>
     */
    private function getActiveSptVersions(bool $isAdmin): array
    {
        $cacheKey = $isAdmin ? 'spt-versions:active:admin' : 'spt-versions:active:user';

        /** @var array<int, string> */
        return Cache::flexible(
            $cacheKey,
            [5 * 60, 10 * 60], // 5 minutes stale, 10 minutes expire
            fn (): array => SptVersion::getVersionsForLastThreeMinors($isAdmin)->pluck('version')->all()
        );
    }
}
