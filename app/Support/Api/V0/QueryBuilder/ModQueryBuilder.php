<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Models\Mod;
use App\Models\SptVersion;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Override;

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
     * @return array<string, string>
     */
    public static function getAllowedFilters(): array
    {
        return [
            'id' => 'filterById',
            'hub_id' => 'filterByHubId',
            'name' => 'filterByName',
            'slug' => 'filterBySlug',
            'teaser' => 'filterByTeaser',
            'source_code_url' => 'filterBySourceCodeLink',
            'featured' => 'filterByFeatured',
            'contains_ads' => 'filterByContainsAds',
            'contains_ai_content' => 'filterByContainsAiContent',
            'created_between' => 'filterByCreatedBetween',
            'updated_between' => 'filterByUpdatedBetween',
            'published_between' => 'filterByPublishedBetween',
            'spt_version' => 'filterBySptVersion',
        ];
    }

    /**
     * Filter by mod IDs.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterById(Builder $query, ?string $ids): void
    {
        if ($ids === null) {
            return;
        }

        $query->whereIn('mods.id', self::parseCommaSeparatedInput($ids, 'integer'));
    }

    /**
     * Filter by hub IDs.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterByHubId(Builder $query, ?string $hubIds): void
    {
        if ($hubIds === null) {
            return;
        }

        $query->whereIn('mods.hub_id', self::parseCommaSeparatedInput($hubIds, 'integer'));
    }

    /**
     * Filter by name.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterByName(Builder $query, ?string $term): void
    {
        if ($term === null) {
            return;
        }

        $query->whereLike('mods.name', sprintf('%%%s%%', $term));
    }

    /**
     * Filter by slug.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterBySlug(Builder $query, ?string $term): void
    {
        if ($term === null) {
            return;
        }

        $query->whereLike('mods.slug', sprintf('%%%s%%', $term));
    }

    /**
     * Filter by teaser.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterByTeaser(Builder $query, ?string $term): void
    {
        if ($term === null) {
            return;
        }

        $query->whereLike('mods.teaser', sprintf('%%%s%%', $term));
    }

    /**
     * Filter by source code link.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterBySourceCodeLink(Builder $query, ?string $term): void
    {
        if ($term === null) {
            return;
        }

        $query->whereLike('mods.source_code_url', sprintf('%%%s%%', $term));
    }

    /**
     * Filter by featured status.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterByFeatured(Builder $query, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $query->where('mods.featured', self::parseBooleanInput($value));
    }

    /**
     * Filter by contains ads status.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterByContainsAds(Builder $query, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $query->where('mods.contains_ads', self::parseBooleanInput($value));
    }

    /**
     * Filter by contains AI content status.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterByContainsAiContent(Builder $query, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $query->where('mods.contains_ai_content', self::parseBooleanInput($value));
    }

    /**
     * Filter by creation date range.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterByCreatedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('mods.created_at', [$start, $end]);
    }

    /**
     * Filter by update date range.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterByUpdatedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('mods.updated_at', [$start, $end]);
    }

    /**
     * Filter by publication date range.
     *
     * @param  Builder<Mod>  $query
     */
    protected function filterByPublishedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('mods.published_at', [$start, $end]);
    }

    /**
     * Filter by SPT version.
     *
     * @param  Builder<Mod>  $query
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
     * Get the allowed relationships that can be included.
     *
     * @return array<string>
     */
    public static function getAllowedIncludes(): array
    {
        return [
            'owner',
            'authors',
            'versions',
            'license',
        ];
    }

    /**
     * Get the required fields that should always be loaded for relationships.
     *
     * @return array<string>
     */
    public static function getRequiredFields(): array
    {
        return [
            'id',
            'owner_id',
            'license_id',
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
            'hub_id',
            'name',
            'slug',
            'teaser',
            'description',
            'thumbnail',
            'downloads',
            'source_code_url',
            'featured',
            'contains_ai_content',
            'contains_ads',
            'published_at',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get the dynamic attributes that can be included in the response. Keys are the attribute names and the values are
     * arrays of required database fields that are used to compute the attribute.
     *
     * @return array<string, array<string>>
     */
    #[Override]
    protected static function getDynamicAttributes(): array
    {
        return [
            'detail_url' => ['slug'],
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
            'name',
            'featured',
            'created_at',
            'updated_at',
            'published_at',
        ];
    }
}
