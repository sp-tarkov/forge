<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Models\Mod;
use App\Models\SptVersion;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @extends AbstractQueryBuilder<Mod>
 */
class ModQueryBuilder extends AbstractQueryBuilder
{
    /**
     * Get the base query for the model.
     *
     * @return Builder<Mod>
     */
    protected function getBaseQuery(): Builder
    {
        $query = Mod::query()
            ->select('mods.*')
            ->where('mods.disabled', false);

        // Apply the SPT version condition if the filter is not being used
        if (! $this->hasFilter('spt_version')) {
            $this->applySptVersionCondition($query);
        }

        return $query;
    }

    /**
     * Apply the SPT version condition to the query.
     *
     * @param  Builder<Mod>  $query
     * @param  array<string>|null  $compatibleVersions  Optional list of compatible versions to filter by
     */
    protected function applySptVersionCondition(Builder $query, ?array $compatibleVersions = null): void
    {
        $query->whereExists(function ($query) use ($compatibleVersions): void {

            // By default, get all mods that have at least one version that is compatible with the SPT version
            $query->select(DB::raw(1))
                ->from('mod_versions')
                ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
                ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                ->whereColumn('mod_versions.mod_id', 'mods.id')
                ->whereNotNull('spt_versions.version')
                ->where('spt_versions.version', '!=', '0.0.0')
                ->where('mod_versions.disabled', false);

            // Get all mods with versions compatible with specific SPT versions.
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
     * @return class-string<Mod>
     */
    protected function getModelClass(): string
    {
        return Mod::class;
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
                $query->whereIn('mods.id', self::parseCommaSeparatedInput($ids, 'integer'));
            },
            'hub_id' => function (Builder $query, string $hubIds): void {
                $query->whereIn('mods.hub_id', self::parseCommaSeparatedInput($hubIds, 'integer'));
            },
            'name' => function (Builder $query, string $term): void {
                $query->whereLike('mods.name', sprintf('%%%s%%', $term));
            },
            'slug' => function (Builder $query, string $term): void {
                $query->whereLike('mods.slug', sprintf('%%%s%%', $term));
            },
            'teaser' => function (Builder $query, string $term): void {
                $query->whereLike('mods.teaser', sprintf('%%%s%%', $term));
            },
            'source_code_link' => function (Builder $query, string $term): void {
                $query->whereLike('mods.source_code_link', sprintf('%%%s%%', $term));
            },
            'featured' => function (Builder $query, string $value): void {
                $query->where('mods.featured', self::parseBooleanInput($value));
            },
            'contains_ads' => function (Builder $query, string $value): void {
                $query->where('mods.contains_ads', self::parseBooleanInput($value));
            },
            'contains_ai_content' => function (Builder $query, string $value): void {
                $query->where('mods.contains_ai_content', self::parseBooleanInput($value));
            },
            'created_between' => function (Builder $query, string $range): void {
                [$start, $end] = explode(',', $range);
                $query->whereBetween('mods.created_at', [$start, $end]);
            },
            'updated_between' => function (Builder $query, string $range): void {
                [$start, $end] = explode(',', $range);
                $query->whereBetween('mods.updated_at', [$start, $end]);
            },
            'published_between' => function (Builder $query, string $range): void {
                [$start, $end] = explode(',', $range);
                $query->whereBetween('mods.published_at', [$start, $end]);
            },
            'spt_version' => function (Builder $query, string $version): void {
                $validSptVersions = SptVersion::allValidVersions();
                $compatibleSptVersions = Semver::satisfiedBy($validSptVersions, $version);
                $this->applySptVersionCondition($query, $compatibleSptVersions);
            },
        ];
    }

    /**
     * Get the map of API include names to model relationship names.
     *
     * @return array<string, string>
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'owner' => 'owner',
            'authors' => 'authors',
            'versions' => 'versions',
            'license' => 'license',
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
            'name',
            'slug',
            'teaser',
            'source_code_link',
            'featured',
            'contains_ads',
            'contains_ai_content',
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
            'name',
            'slug',
            'featured',
            'contains_ads',
            'contains_ai_content',
            'created_at',
            'updated_at',
            'published_at',
        ];
    }
}
