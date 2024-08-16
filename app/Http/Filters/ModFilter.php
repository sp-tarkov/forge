<?php

namespace App\Http\Filters;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Builder;

class ModFilter
{
    /**
     * The query builder instance for the mod model.
     */
    protected Builder $builder;

    /**
     * The filter that should be applied to the query.
     */
    protected array $filters;

    public function __construct(array $filters)
    {
        $this->builder = $this->baseQuery();
        $this->filters = $filters;
    }

    /**
     * The base query for the mod listing.
     */
    private function baseQuery(): Builder
    {
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'created_at'])
            ->withTotalDownloads()
            ->with(['latestVersion', 'latestVersion.sptVersion', 'users:id,name'])
            ->whereHas('latestVersion');
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
        // We order the "recently updated" mods by the ModVersion's updated_at value.
        if ($type === 'updated') {
            return $this->builder->orderByDesc(
                ModVersion::select('updated_at')
                    ->whereColumn('mod_id', 'mods.id')
                    ->orderByDesc('updated_at')
                    ->take(1)
            );
        }

        // By default, we simply order by the column on the mods table/query.
        $column = match ($type) {
            'downloaded' => 'total_downloads',
            default => 'created_at',
        };

        return $this->builder->orderByDesc($column);
    }

    /**
     * Filter the results by the given search term.
     */
    private function query(string $term): Builder
    {
        return $this->builder->whereLike('name', "%$term%");
    }

    /**
     * Filter the results by the featured status.
     */
    private function featured(string $option): Builder
    {
        return match ($option) {
            'exclude' => $this->builder->where('featured', false),
            'only' => $this->builder->where('featured', true),
            default => $this->builder,
        };
    }

    /**
     * Filter the results to a specific SPT version.
     */
    private function sptVersion(array $versions): Builder
    {
        return $this->builder->withWhereHas('latestVersion.sptVersion', function ($query) use ($versions) {
            $query->whereIn('version', $versions);
            $query->orderByDesc('version');
        });
    }
}
