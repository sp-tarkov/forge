<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Models\Mod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ModFilter
{
    /**
     * The query builder instance for the mod model.
     *
     * @var Builder<Mod>
     */
    protected Builder $builder;

    /**
     * Create a new ModFilter instance.
     */
    public function __construct(
        /**
         * The filters to apply to the query.
         *
         * @var array<string, mixed>
         */
        protected array $filters
    ) {
        $this->builder = $this->baseQuery();
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
            ->unless($showDisabled, fn (Builder $query) => $query->where('mods.disabled', false))
            ->whereExists(function (QueryBuilder $query) use ($showDisabled): void {
                $query->select(DB::raw(1))
                    ->from('mod_versions')
                    ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
                    ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                    ->whereColumn('mod_versions.mod_id', 'mods.id')
                    ->where('spt_versions.version', '!=', '0.0.0')
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->where('mod_versions.disabled', false))
                    ->unless($showDisabled, fn (QueryBuilder $query) => $query->whereNotNull('mod_versions.published_at'));
            });
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
     * Apply the filters to the query.
     *
     * @return Builder<Mod>
     */
    public function apply(): Builder
    {
        foreach ($this->filters as $method => $value) {
            if (method_exists($this, $method) && ! empty($value)) {
                $this->$method($value);
            }
        }

        return $this->builder;
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
            'updated' => $this->builder->orderByDesc('mods.updated_at'),
            'downloaded' => $this->builder->orderByDesc('mods.downloads'),
            default => $this->builder->orderByDesc('mods.created_at'),
        };
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
     * Filter the results to specific SPT versions.
     *
     * @param  string|array<int, string>  $versions
     * @return Builder<Mod>
     */
    private function sptVersions(mixed $versions): Builder
    {
        if (! is_array($versions)) {
            return $this->builder;
        }

        $showDisabled = auth()->user()?->isModOrAdmin() ?? false;

        return $this->builder->whereExists(function (\Illuminate\Database\Query\Builder $query) use ($versions, $showDisabled): void {
            $query->select(DB::raw(1))
                ->from('mod_versions')
                ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
                ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                ->whereColumn('mod_versions.mod_id', 'mods.id')
                ->whereIn('spt_versions.version', $versions)
                ->where('spt_versions.version', '!=', '0.0.0')
                ->unless($showDisabled, fn (\Illuminate\Database\Query\Builder $query) => $query->where('mod_versions.disabled', false))
                ->unless($showDisabled, fn (\Illuminate\Database\Query\Builder $query) => $query->whereNotNull('mod_versions.published_at'));
        });
    }
}
