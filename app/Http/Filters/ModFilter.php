<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Models\Mod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ModFilter
{
    /**
     * The query builder instance for the mod model.
     */
    protected Builder $builder;

    /**
     * Create a new ModFilter instance.
     */
    public function __construct(/**
     * The filters to apply.
     */
        protected array $filters)
    {
        $this->builder = $this->baseQuery();
    }

    /**
     * The base query for the mod listing.
     */
    private function baseQuery(): Builder
    {
        return Mod::query()
            ->select('mods.*')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('mod_versions')
                    ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
                    ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                    ->whereColumn('mod_versions.mod_id', 'mods.id')
                    ->where('spt_versions.version', '!=', '0.0.0');
            })
            ->with([
                'users:id,name',
                'latestVersion',
                'latestVersion.latestSptVersion',
            ]);
    }

    /**
     * Filter the results by the given search term.
     */
    private function query(string $term): Builder
    {
        return $this->builder->whereLike('mods.name', sprintf('%%%s%%', $term));
    }

    /**
     * Apply the filters to the query.
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
     */
    private function order(string $type): Builder
    {
        return match ($type) {
            'updated' => $this->builder->orderByDesc('mods.updated_at'),
            'downloaded' => $this->builder->orderByDesc('mods.downloads'),
            default => $this->builder->orderByDesc('mods.created_at'),
        };
    }

    /**
     * Filter the results by the featured status.
     */
    private function featured(string $option): Builder
    {
        return match ($option) {
            'exclude' => $this->builder->where('mods.featured', false),
            'only' => $this->builder->where('mods.featured', true),
            default => $this->builder,
        };
    }

    /**
     * Filter the results to specific SPT versions.
     */
    private function sptVersions(array $versions): Builder
    {
        return $this->builder->whereExists(function ($query) use ($versions): void {
            $query->select(DB::raw(1))
                ->from('mod_versions')
                ->join('mod_version_spt_version', 'mod_versions.id', '=', 'mod_version_spt_version.mod_version_id')
                ->join('spt_versions', 'mod_version_spt_version.spt_version_id', '=', 'spt_versions.id')
                ->whereColumn('mod_versions.mod_id', 'mods.id')
                ->whereIn('spt_versions.version', $versions)
                ->where('spt_versions.version', '!=', '0.0.0');
        });
    }
}
