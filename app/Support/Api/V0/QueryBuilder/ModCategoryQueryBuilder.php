<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Models\ModCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends AbstractQueryBuilder<ModCategory>
 */
class ModCategoryQueryBuilder extends AbstractQueryBuilder
{
    /**
     * Get the allowed filters for this query builder.
     *
     * @return array<string, string>
     */
    public static function getAllowedFilters(): array
    {
        return [
            'id' => 'filterById',
            'slug' => 'filterBySlug',
            'title' => 'filterByTitle',
        ];
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
     * Get the required fields that should always be loaded.
     *
     * @return array<string>
     */
    public static function getRequiredFields(): array
    {
        return [
            'id',
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
            'title',
            'slug',
            'description',
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
            'title',
            'slug',
        ];
    }

    /**
     * Get the base query for the model.
     *
     * @return Builder<ModCategory>
     */
    protected function getBaseQuery(): Builder
    {
        return ModCategory::query();
    }

    /**
     * Get the model class for this query builder.
     *
     * @return class-string<ModCategory>
     */
    protected function getModelClass(): string
    {
        return ModCategory::class;
    }

    /**
     * Filter by category IDs.
     *
     * @param  Builder<ModCategory>  $query
     */
    protected function filterById(Builder $query, ?string $ids): void
    {
        if ($ids === null) {
            return;
        }

        $query->whereIn('mod_categories.id', self::parseCommaSeparatedInput($ids, 'integer'));
    }

    /**
     * Filter by category slugs.
     *
     * @param  Builder<ModCategory>  $query
     */
    protected function filterBySlug(Builder $query, ?string $slugs): void
    {
        if ($slugs === null) {
            return;
        }

        $query->whereIn('mod_categories.slug', self::parseCommaSeparatedInput($slugs, 'string'));
    }

    /**
     * Filter by category title (wildcard search).
     *
     * @param  Builder<ModCategory>  $query
     */
    protected function filterByTitle(Builder $query, ?string $title): void
    {
        if ($title === null) {
            return;
        }

        $query->where('mod_categories.title', 'like', '%'.$title.'%');
    }
}
