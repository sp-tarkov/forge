<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Exceptions\Api\V0\InvalidQuery;
use App\Models\SptVersion;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Override;

/**
 * @extends AbstractQueryBuilder<SptVersion>
 */
class SptVersionQueryBuilder extends AbstractQueryBuilder
{
    /**
     * Get the base query for the model.
     *
     * @return Builder<SptVersion>
     */
    protected function getBaseQuery(): Builder
    {
        return SptVersion::query()
            ->where('version', '!=', '0.0.0'); // Always exclude the base version.
    }

    /**
     * Get the model class for this query builder.
     *
     * @return class-string<SptVersion>
     */
    protected function getModelClass(): string
    {
        return SptVersion::class;
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
            'spt_version' => 'filterBySptVersion',
            'created_between' => 'filterByCreatedBetween',
            'updated_between' => 'filterByUpdatedBetween',
        ];
    }

    /**
     * Filter by SPT version IDs.
     *
     * @param  Builder<SptVersion>  $query
     */
    protected function filterById(Builder $query, ?string $ids): void
    {
        if ($ids === null) {
            return;
        }

        $query->whereIn('spt_versions.id', self::parseCommaSeparatedInput($ids, 'integer'));
    }

    /**
     * Filter by SPT version.
     *
     * @param  Builder<SptVersion>  $query
     */
    protected function filterBySptVersion(Builder $query, ?string $version): void
    {
        if ($version === null) {
            return;
        }

        $validSptVersions = SptVersion::allValidVersions();
        $compatibleSptVersions = Semver::satisfiedBy($validSptVersions, $version);
        $query->whereIn('spt_versions.version', $compatibleSptVersions);
    }

    /**
     * Filter by creation date range.
     *
     * @param  Builder<SptVersion>  $query
     */
    protected function filterByCreatedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('spt_versions.created_at', [$start, $end]);
    }

    /**
     * Filter by update date range.
     *
     * @param  Builder<SptVersion>  $query
     */
    protected function filterByUpdatedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('spt_versions.updated_at', [$start, $end]);
    }

    /**
     * Get a map of API include names to model relationship names.
     *
     * @return array<string, string|array<string>>
     */
    public static function getAllowedIncludes(): array
    {
        return [];
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
            'version',
            'version_major',
            'version_minor',
            'version_patch',
            'version_labels',
            'mod_count',
            'link',
            'color_class',
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
            'version',
            'mod_count',
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
