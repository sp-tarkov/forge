<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Models\Mod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * @extends AbstractQueryBuilder<Mod>
 */
class ModDependencyTreeQueryBuilder extends AbstractQueryBuilder
{
    /**
     * The mod version IDs to resolve dependencies for.
     *
     * @var array<int>
     */
    protected array $modVersionIds = [];

    /**
     * Get the allowed filters for this query builder. Keys being the filter names and values being the names of the
     * methods that apply the filter to the builder.
     *
     * @return array<string, string>
     */
    public static function getAllowedFilters(): array
    {
        return [];
    }

    /**
     * Get the allowed relationships that can be included.
     *
     * @return array<string, string>
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
            'guid',
            'name',
            'slug',
        ];
    }

    /**
     * Get the allowed fields for this query builder.
     *
     * @return array<string>
     */
    public static function getAllowedFields(): array
    {
        return [];
    }

    /**
     * Get the allowed sorts for this query builder.
     *
     * @return array<string>
     */
    public static function getAllowedSorts(): array
    {
        return [];
    }

    /**
     * Set the mod version IDs to resolve dependencies for.
     *
     * @param  array<int>  $modVersionIds
     */
    public function withModVersionIds(array $modVersionIds): self
    {
        $this->modVersionIds = $modVersionIds;

        return $this;
    }

    /**
     * Get the dynamic attributes that can be included in the response. The keys are the attribute names, and the values
     * are arrays of required database fields.
     *
     * @return array<string, array<string>>
     */
    #[Override]
    protected static function getDynamicAttributes(): array
    {
        return [];
    }

    /**
     * Get the base query for the model.
     *
     * @return Builder<Mod>
     */
    protected function getBaseQuery(): Builder
    {
        $query = Mod::query()
            ->select('mods.*')
            ->where('mods.disabled', false)
            ->whereNotNull('mods.published_at')
            ->where('mods.published_at', '<=', now());

        // If we have mod version IDs, get their dependencies
        if (! empty($this->modVersionIds)) {
            $query->whereExists(function (\Illuminate\Database\Query\Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('dependencies_resolved')
                    ->join('dependencies', 'dependencies_resolved.dependency_id', '=', 'dependencies.id')
                    ->join('mod_versions', 'dependencies_resolved.resolved_mod_version_id', '=', 'mod_versions.id')
                    ->whereColumn('dependencies.dependent_mod_id', 'mods.id')
                    ->whereIn('dependencies_resolved.dependable_id', $this->modVersionIds)
                    ->whereNotNull('mod_versions.published_at')
                    ->where('mod_versions.published_at', '<=', now())
                    ->where('mod_versions.disabled', false);
            });
        }

        return $query;
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
}
