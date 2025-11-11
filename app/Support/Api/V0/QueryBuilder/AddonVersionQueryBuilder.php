<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Exceptions\Api\V0\InvalidQuery;
use App\Models\AddonVersion;
use Composer\Semver\Semver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Override;

/**
 * @extends AbstractQueryBuilder<AddonVersion>
 */
class AddonVersionQueryBuilder extends AbstractQueryBuilder
{
    /**
     * Create a new AddonVersionQueryBuilder instance.
     */
    public function __construct(
        /**
         * The ID of the addon to filter versions for.
         */
        protected readonly int $addonId
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
            'version' => 'filterByVersion',
            'description' => 'filterByDescription',
            'link' => 'filterByLink',
            'published_between' => 'filterByPublishedBetween',
            'created_between' => 'filterByCreatedBetween',
            'updated_between' => 'filterByUpdatedBetween',
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
            'addon_id',
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
            'description',
            'link',
            'content_length',
            'mod_version_constraint',
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
    public static function getAllowedSorts(): array
    {
        return [
            'id',
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
     * @return Builder<AddonVersion>
     */
    protected function getBaseQuery(): Builder
    {
        $hasPublishedVersions = AddonVersion::query()
            ->where('addon_versions.addon_id', $this->addonId)
            ->where('addon_versions.disabled', false)
            ->whereNotNull('addon_versions.published_at')
            ->where('addon_versions.published_at', '<=', now())
            ->exists();

        if (! $hasPublishedVersions) {
            throw new ModelNotFoundException()->setModel(AddonVersion::class);
        }

        // Check if parent mod has published versions with SPT versions
        $parentModHasPublishedVersions = AddonVersion::query()
            ->where('addon_versions.addon_id', $this->addonId)
            ->whereHas('addon.mod.versions', function (Builder $modVersionQuery): void {
                $modVersionQuery->where('mod_versions.disabled', false)
                    ->whereNotNull('mod_versions.published_at')
                    ->where('mod_versions.published_at', '<=', now())
                    ->whereHas('latestSptVersion');
            })
            ->exists();

        if (! $parentModHasPublishedVersions) {
            throw new ModelNotFoundException()->setModel(AddonVersion::class);
        }

        return AddonVersion::query()
            ->where('addon_versions.addon_id', $this->addonId)
            ->where('addon_versions.disabled', false)
            ->whereNotNull('addon_versions.published_at');
    }

    /**
     * Get the model class for this query builder.
     *
     * @return class-string<AddonVersion>
     */
    protected function getModelClass(): string
    {
        return AddonVersion::class;
    }

    /**
     * Filter by addon version IDs.
     *
     * @param  Builder<AddonVersion>  $query
     */
    protected function filterById(Builder $query, ?string $ids): void
    {
        if ($ids === null) {
            return;
        }

        $query->whereIn('addon_versions.id', self::parseCommaSeparatedInput($ids, 'integer'));
    }

    /**
     * Filter by version.
     *
     * @param  Builder<AddonVersion>  $query
     */
    protected function filterByVersion(Builder $query, ?string $semverConstraint): void
    {
        if ($semverConstraint === null) {
            return;
        }

        $allVersionNumbers = AddonVersion::query()->where('addon_id', $this->addonId)
            ->pluck('version')
            ->all();
        $compatibleVersions = Semver::satisfiedBy($allVersionNumbers, $semverConstraint);
        $query->whereIn('addon_versions.version', $compatibleVersions);
    }

    /**
     * Filter by description.
     *
     * @param  Builder<AddonVersion>  $query
     */
    protected function filterByDescription(Builder $query, ?string $term): void
    {
        if ($term === null) {
            return;
        }

        $query->whereLike('addon_versions.description', sprintf('%%%s%%', $term));
    }

    /**
     * Filter by link.
     *
     * @param  Builder<AddonVersion>  $query
     */
    protected function filterByLink(Builder $query, ?string $term): void
    {
        if ($term === null) {
            return;
        }

        $query->whereLike('addon_versions.link', sprintf('%%%s%%', $term));
    }

    /**
     * Filter by publication date range.
     *
     * @param  Builder<AddonVersion>  $query
     */
    protected function filterByPublishedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('addon_versions.published_at', [$start, $end]);
    }

    /**
     * Filter by creation date range.
     *
     * @param  Builder<AddonVersion>  $query
     */
    protected function filterByCreatedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('addon_versions.created_at', [$start, $end]);
    }

    /**
     * Filter by update date range.
     *
     * @param  Builder<AddonVersion>  $query
     */
    protected function filterByUpdatedBetween(Builder $query, ?string $range): void
    {
        if ($range === null) {
            return;
        }

        [$start, $end] = explode(',', $range);
        $query->whereBetween('addon_versions.updated_at', [$start, $end]);
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
                        ->orderBy('version_pre_release', $direction);
                } else {
                    $this->builder->orderBy($cleanName, $direction);
                }
            }
        }
    }
}
