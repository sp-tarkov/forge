<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Exceptions\Api\V0\InvalidQuery;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
     * Get the allowed filters for this query builder. Keys being the filter names and values being the names of the
     * methods that apply the filter to the builder.
     *
     * @return array<string, string>
     */
    public static function getAllowedFilters(): array
    {
        return [
            'id' => 'filterById',
            'hub_id' => 'filterByHubId',
            'version' => 'filterByVersion',
            'description' => 'filterByDescription',
            'link' => 'filterByLink',
            'published_between' => 'filterByPublishedBetween',
            'created_between' => 'filterByCreatedBetween',
            'updated_between' => 'filterByUpdatedBetween',
            'spt_version' => 'filterBySptVersion',
            'fika_compatibility' => 'filterByFikaCompatibility',
        ];
    }

    /**
     * Get a map of API include names to model relationship names.
     *
     * @return array<string, string|array<string>>
     */
    public static function getAllowedIncludes(): array
    {
        return [
            'dependencies' => [
                'dependenciesResolved',
                'dependenciesResolved.mod',
            ],
            'virus_total_links' => 'virusTotalLinks',
        ];
    }

    /**
     * Get the required fields that should always be loaded for relationships. These fields are not subject to field
     * white-listing and will be automatically included when needed.
     *
     * @return array<string>
     */
    public static function getRequiredFields(): array
    {
        return [
            'id',
            'mod_id',
            'version',
        ];
    }

    /**
     * Get the allowed fields for this query builder.
     *
     * @return array<string>
     */
    public static function getAllowedFields(): array
    {
        return [
            'id',
            'hub_id',
            'version',
            'description',
            'link',
            'content_length',
            'spt_version_constraint',
            'downloads',
            'fika_compatibility',
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
    public static function getAllowedSorts(): array
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
     * Get the base query for the model.
     *
     * @return Builder<ModVersion>
     */
    protected function getBaseQuery(): Builder
    {
        $hasVisibleVersions = ModVersion::query()
            ->whereModId($this->modId)
            ->where('mod_versions.disabled', false)
            ->whereNotNull('mod_versions.published_at')
            ->where('mod_versions.published_at', '<=', now())
            ->whereHas('latestSptVersion')
            ->exists();

        if (! $hasVisibleVersions) {
            throw new ModelNotFoundException()->setModel(ModVersion::class);
        }

        $query = ModVersion::query()
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
        $query->whereExists(function (\Illuminate\Database\Query\Builder $query) use ($compatibleVersions): void {
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
     * Get the model class for this query builder.
     *
     * @return class-string<ModVersion>
     */
    protected function getModelClass(): string
    {
        return ModVersion::class;
    }

    /**
     * Filter by mod version IDs.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterById(Builder $query, ?string $ids): void
    {
        if ($ids === null) {
            return;
        }

        $query->whereIn('mod_versions.id', self::parseCommaSeparatedInput($ids, 'integer'));
    }

    /**
     * Filter by hub IDs.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterByHubId(Builder $query, ?string $hubIds): void
    {
        if ($hubIds === null) {
            return;
        }

        $query->whereIn('mod_versions.hub_id', self::parseCommaSeparatedInput($hubIds, 'integer'));
    }

    /**
     * Filter by version.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterByVersion(Builder $query, ?string $semverConstraint): void
    {
        if ($semverConstraint === null) {
            return;
        }

        $allVersionNumbers = ModVersion::versionNumbers($this->modId);
        $compatibleVersions = Semver::satisfiedBy($allVersionNumbers, $semverConstraint);
        $query->whereIn('mod_versions.version', $compatibleVersions);
    }

    /**
     * Filter by description.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterByDescription(Builder $query, ?string $term): void
    {
        if ($term === null) {
            return;
        }

        $query->whereLike('mod_versions.description', sprintf('%%%s%%', $term));
    }

    /**
     * Filter by link.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterByLink(Builder $query, ?string $term): void
    {
        if ($term === null) {
            return;
        }

        $query->whereLike('mod_versions.link', sprintf('%%%s%%', $term));
    }

    /**
     * Filter by publication date range.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterByPublishedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('mod_versions.published_at', [$start, $end]);
    }

    /**
     * Filter by creation date range.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterByCreatedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('mod_versions.created_at', [$start, $end]);
    }

    /**
     * Filter by update date range.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterByUpdatedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('mod_versions.updated_at', [$start, $end]);
    }

    /**
     * Filter by SPT version.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterBySptVersion(Builder $query, ?string $version): void
    {
        if ($version === null) {
            return;
        }

        $validSptVersions = SptVersion::allValidVersions();
        $compatibleSptVersions = Semver::satisfiedBy($validSptVersions, $version);

        $this->applySptVersionCondition($query, $compatibleSptVersions);
    }

    /**
     * Filter by Fika compatibility status.
     *
     * @param  Builder<ModVersion>  $query
     */
    protected function filterByFikaCompatibility(Builder $query, ?string $compatibility): void
    {
        if ($compatibility === null) {
            return;
        }

        $compatibilityValues = self::parseCommaSeparatedInput($compatibility, 'string');
        $query->whereIn('mod_versions.fika_compatibility', $compatibilityValues);
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
            $this->sorts = array_filter($this->sorts, fn (?string $sort): bool => ! empty($sort));
            if (empty($this->sorts)) {
                return; // All sorts were empty and filtered out, return early.
            }

            $allowedSorts = static::getAllowedSorts();
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
