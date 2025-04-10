<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Exceptions\Api\V0\InvalidQuery;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Override;

/**
 * @extends AbstractQueryBuilder<ModVersion>
 */
class ModVersionQueryBuilder extends AbstractQueryBuilder
{
    /**
     * Create a new ModVersionQueryBuilder instance.
     */
    public function __construct(
        /**
         * The ID of the mod to filter versions for.
         */
        protected readonly int $modId
    ) {
        parent::__construct();
    }

    /**
     * Get the base query for the model.
     *
     * @return Builder<ModVersion>
     */
    protected function getBaseQuery(): Builder
    {
        $query = ModVersion::query()
            ->select('mod_versions.*')
            ->whereModId($this->modId)
            ->where('mod_versions.disabled', false);

        // Apply the SPT version condition if the filter is not being used
        if (! $this->hasFilter('spt_version')) {
            $this->applySptVersionCondition($query);
        }

        return $query;
    }

    /**
     * Apply the SPT version condition to the query.
     *
     * @param  Builder<ModVersion>  $query
     * @param  array<string>|null  $compatibleVersions  Optional list of compatible versions to filter by
     */
    protected function applySptVersionCondition(Builder $query, ?array $compatibleVersions = null): void
    {
        $query->whereExists(function ($query) use ($compatibleVersions): void {
            $query->select(DB::raw(1))
                ->from('mod_version_spt_version')
                ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                ->whereColumn('mod_version_spt_version.mod_version_id', 'mod_versions.id')
                ->whereNotNull('spt_versions.version')
                ->where('spt_versions.version', '!=', '0.0.0');

            // Get all mod versions compatible with specific SPT versions.
            if ($compatibleVersions !== null) {
                $query->whereIn('spt_versions.version', $compatibleVersions);
            }
        });
    }

    /**
     * Check if a specific filter is being used in the current request.
     */
    protected function hasFilter(string $filterName): bool
    {
        return request()->has('filter.'.$filterName);
    }

    /**
     * Get the model class for this query builder.
     *
     * @return class-string<ModVersion>
     */
    protected function getModelClass(): string
    {
        return ModVersion::class;
    }

    /**
     * Get the allowed filters for this query builder.
     *
     * @return array<string, callable>
     */
    protected function getAllowedFilters(): array
    {
        return [
            'id' => function (Builder $query, string $ids): void {
                $query->whereIn('mod_versions.id', self::parseCommaSeparatedInput($ids, 'integer'));
            },
            'hub_id' => function (Builder $query, string $hubIds): void {
                $query->whereIn('mod_versions.hub_id', self::parseCommaSeparatedInput($hubIds, 'integer'));
            },
            'version' => function (Builder $query, string $semverConstraint): void {
                $allVersionNumbers = ModVersion::versionNumbers($this->modId);
                $compatibleVersions = Semver::satisfiedBy($allVersionNumbers, $semverConstraint);
                $query->whereIn('mod_versions.version', $compatibleVersions);
            },
            'description' => function (Builder $query, string $term): void {
                $query->whereLike('mod_versions.description', sprintf('%%%s%%', $term));
            },
            'link' => function (Builder $query, string $term): void {
                $query->whereLike('mod_versions.link', sprintf('%%%s%%', $term));
            },
            'virus_total_link' => function (Builder $query, string $term): void {
                $query->whereLike('mod_versions.virus_total_link', sprintf('%%%s%%', $term));
            },
            'published_between' => function (Builder $query, string $range): void {
                [$start, $end] = explode(',', $range);
                $query->whereBetween('mod_versions.published_at', [$start, $end]);
            },
            'created_between' => function (Builder $query, string $range): void {
                [$start, $end] = explode(',', $range);
                $query->whereBetween('mod_versions.created_at', [$start, $end]);
            },
            'updated_between' => function (Builder $query, string $range): void {
                [$start, $end] = explode(',', $range);
                $query->whereBetween('mod_versions.updated_at', [$start, $end]);
            },
            'spt_version' => function (Builder $query, string $version): void {
                $validSptVersions = SptVersion::allValidVersions();
                $compatibleSptVersions = Semver::satisfiedBy($validSptVersions, $version);
                $this->applySptVersionCondition($query, $compatibleSptVersions);
            },
        ];
    }

    /**
     * Get a map of API include names to model relationship names.
     *
     * @return array<string, string|array<string>>
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'dependencies' => [
                'resolvedDependencies',
                'resolvedDependencies.mod',
            ],
        ];
    }

    /**
     * Get the allowed fields for this query builder.
     *
     * @return array<string>
     */
    protected function getAllowedFields(): array
    {
        return [
            'id',
            'hub_id',
            'version',
            'description',
            'link',
            'spt_version_constraint',
            'virus_total_link',
            'downloads',
            'published_at',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get the allowed sorts for this query builder.
     *
     * @return array<string>
     */
    protected function getAllowedSorts(): array
    {
        return [
            'id',
            'hub_id',
            'version',
            'downloads',
            'published_at',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Apply the sorts to the query.
     *
     * @throws InvalidQuery
     */
    #[Override]
    protected function applySorts(): void
    {
        if (! empty($this->sorts)) {
            $this->sorts = array_filter($this->sorts, fn ($sort): bool => ! empty($sort));
            if (empty($this->sorts)) {
                return; // All sorts were empty and filtered out, return early.
            }

            $allowedSorts = $this->getAllowedSorts();
            $invalidSorts = [];

            foreach ($this->sorts as $sort) {
                $cleanName = Str::startsWith($sort, '-') ? Str::substr($sort, 1) : $sort;
                if (! in_array($cleanName, $allowedSorts, true)) {
                    $invalidSorts[] = $sort;
                }
            }

            if (! empty($invalidSorts)) {
                $invalidSort = implode(', ', $invalidSorts);
                $validSorts = implode(', ', $allowedSorts);
                throw new InvalidQuery(
                    sprintf('Invalid sort parameter(s): %s. Valid sorts are: %s', $invalidSort, $validSorts)
                );
            }

            foreach ($this->sorts as $sort) {
                $isReverse = Str::startsWith($sort, '-');
                $cleanName = $isReverse ? Str::substr($sort, 1) : $sort;
                $direction = $isReverse ? 'desc' : 'asc';

                if ($cleanName === 'version') {
                    // For version sorting, we need to sort by all semantic version components
                    $this->builder->orderBy('version_major', $direction)
                        ->orderBy('version_minor', $direction)
                        ->orderBy('version_patch', $direction)
                        ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
                        ->orderBy('version_labels', $direction);
                } else {
                    $this->builder->orderBy($cleanName, $direction);
                }
            }
        }
    }
}
