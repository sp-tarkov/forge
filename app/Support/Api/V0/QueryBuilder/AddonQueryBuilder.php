<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Models\Addon;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * @extends AbstractQueryBuilder<Addon>
 */
class AddonQueryBuilder extends AbstractQueryBuilder
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
            'name' => 'filterByName',
            'slug' => 'filterBySlug',
            'teaser' => 'filterByTeaser',
            'mod_id' => 'filterByModId',
            'contains_ads' => 'filterByContainsAds',
            'contains_ai_content' => 'filterByContainsAiContent',
            'is_detached' => 'filterByIsDetached',
            'created_between' => 'filterByCreatedBetween',
            'updated_between' => 'filterByUpdatedBetween',
            'published_between' => 'filterByPublishedBetween',
        ];
    }

    /**
     * Get the allowed relationships that can be included.
     *
     * @return array<string, string>
     */
    public static function getAllowedIncludes(): array
    {
        return [
            'owner' => 'owner',
            'authors' => 'authors',
            'versions' => 'versions',
            'latest_version' => 'latestVersion',
            'license' => 'license',
            'mod' => 'mod',
            'source_code_links' => 'sourceCodeLinks',
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
            'mod_id',
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
            'name',
            'slug',
            'teaser',
            'description',
            'thumbnail',
            'downloads',
            'contains_ai_content',
            'contains_ads',
            'mod_id',
            'detached_at',
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
            'name',
            'downloads',
            'created_at',
            'updated_at',
            'published_at',
        ];
    }

    /**
     * Get the dynamic attributes that can be included in the response.
     *
     * @return array<string, array<string>>
     */
    #[Override]
    protected static function getDynamicAttributes(): array
    {
        return [
            'detail_url' => ['slug'],
            'is_detached' => ['detached_at'],
        ];
    }

    /**
     * Get the base query for the model.
     *
     * @return Builder<Addon>
     */
    protected function getBaseQuery(): Builder
    {
        $showDisabled = auth()->user()?->isModOrAdmin() ?? false;

        return Addon::query()
            ->select('addons.*')
            ->unless($showDisabled, fn (Builder $query) => $query->where('addons.disabled', false))
            ->unless($showDisabled, fn (Builder $query) => $query->whereNotNull('addons.published_at'))
            ->unless($showDisabled, fn (Builder $query) => $query->where('addons.published_at', '<=', now()))
            ->latest('addons.created_at');
    }

    /**
     * Get the model class for this query builder.
     *
     * @return class-string<Addon>
     */
    protected function getModelClass(): string
    {
        return Addon::class;
    }

    /**
     * Filter by addon IDs.
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterById(Builder $query, ?string $ids): void
    {
        if (! $ids) {
            return;
        }

        $query->whereIn('addons.id', explode(',', $ids));
    }

    /**
     * Filter by mod IDs.
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterByModId(Builder $query, ?string $ids): void
    {
        if (! $ids) {
            return;
        }

        $query->whereIn('addons.mod_id', explode(',', $ids));
    }

    /**
     * Filter by name (fuzzy).
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterByName(Builder $query, ?string $name): void
    {
        if (! $name) {
            return;
        }

        $query->where('addons.name', 'like', sprintf('%%%s%%', $name));
    }

    /**
     * Filter by slug (fuzzy).
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterBySlug(Builder $query, ?string $slug): void
    {
        if (! $slug) {
            return;
        }

        $query->where('addons.slug', 'like', sprintf('%%%s%%', $slug));
    }

    /**
     * Filter by teaser (fuzzy).
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterByTeaser(Builder $query, ?string $teaser): void
    {
        if (! $teaser) {
            return;
        }

        $query->where('addons.teaser', 'like', sprintf('%%%s%%', $teaser));
    }

    /**
     * Filter by contains_ads.
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterByContainsAds(Builder $query, ?string $containsAds): void
    {
        if ($containsAds === null) {
            return;
        }

        $query->where('addons.contains_ads', filter_var($containsAds, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Filter by contains_ai_content.
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterByContainsAiContent(Builder $query, ?string $containsAiContent): void
    {
        if ($containsAiContent === null) {
            return;
        }

        $query->where('addons.contains_ai_content', filter_var($containsAiContent, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Filter by detached status.
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterByIsDetached(Builder $query, ?string $isDetached): void
    {
        if ($isDetached === null) {
            return;
        }

        $detached = filter_var($isDetached, FILTER_VALIDATE_BOOLEAN);

        if ($detached) {
            $query->whereNotNull('addons.detached_at');
        } else {
            $query->whereNull('addons.detached_at');
        }
    }

    /**
     * Filter by creation date range.
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterByCreatedBetween(Builder $query, ?string $range): void
    {
        if (! $range) {
            return;
        }

        $dates = explode(',', $range);
        if (count($dates) !== 2) {
            return;
        }

        $query->whereBetween('addons.created_at', $dates);
    }

    /**
     * Filter by update date range.
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterByUpdatedBetween(Builder $query, ?string $range): void
    {
        if (! $range) {
            return;
        }

        $dates = explode(',', $range);
        if (count($dates) !== 2) {
            return;
        }

        $query->whereBetween('addons.updated_at', $dates);
    }

    /**
     * Filter by publication date range.
     *
     * @param  Builder<Addon>  $query
     */
    protected function filterByPublishedBetween(Builder $query, ?string $range): void
    {
        if (! $range) {
            return;
        }

        $dates = explode(',', $range);
        if (count($dates) !== 2) {
            return;
        }

        $query->whereBetween('addons.published_at', $dates);
    }
}
