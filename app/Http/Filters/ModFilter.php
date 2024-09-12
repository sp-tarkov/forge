<?php

namespace App\Http\Filters;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Builder;
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
     * The filter that should be applied to the query.
     *
     * @var array<string, mixed>
     */
    protected array $filters;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(array $filters)
    {
        $this->builder = $this->baseQuery();
        $this->filters = $filters;
    }

    /**
     * The base query for the mod listing.
     *
     * @return Builder<Mod>
     */
    private function baseQuery(): Builder
    {
        return Mod::select([
            'mods.id',
            'mods.name',
            'mods.slug',
            'mods.teaser',
            'mods.thumbnail',
            'mods.featured',
            'mods.downloads',
            'mods.created_at',
        ])->with([
            'users:id,name',
            'latestVersion' => function ($query) {
                $query->with('latestSptVersion:id,version,color_class');
            },
        ]);
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
    private function order(string $type): Builder
    {
        // We order the "recently updated" mods by the ModVersion's updated_at value.
        if ($type === 'updated') {
            return $this->builder
                ->joinSub(
                    ModVersion::select('mod_id', DB::raw('MAX(updated_at) as latest_updated_at'))->groupBy('mod_id'),
                    'latest_versions',
                    'mods.id',
                    '=',
                    'latest_versions.mod_id'
                )
                ->orderByDesc('latest_versions.latest_updated_at');
        }

        // By default, we simply order by the column on the mods table/query.
        $column = match ($type) {
            'downloaded' => 'downloads',
            default => 'created_at',
        };

        return $this->builder->orderByDesc($column);
    }

    /**
     * Filter the results by the given search term.
     *
     * @return Builder<Mod>
     */
    private function query(string $term): Builder
    {
        return $this->builder->whereLike('name', "%$term%");
    }

    /**
     * Filter the results by the featured status.
     *
     * @return Builder<Mod>
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
     * Filter the results to specific SPT versions.
     *
     * @param  array<int, string>  $versions
     * @return Builder<Mod>
     */
    private function sptVersions(array $versions): Builder
    {
        // Parse the versions into major, minor, and patch arrays
        $parsedVersions = array_map(fn ($version) => [
            'major' => (int) explode('.', $version)[0],
            'minor' => (int) (explode('.', $version)[1] ?? 0),
            'patch' => (int) (explode('.', $version)[2] ?? 0),
        ], $versions);

        [$majorVersions, $minorVersions, $patchVersions] = array_map('array_unique', [
            array_column($parsedVersions, 'major'),
            array_column($parsedVersions, 'minor'),
            array_column($parsedVersions, 'patch'),
        ]);

        return $this->builder
            ->join('mod_versions as mv', 'mods.id', '=', 'mv.mod_id')
            ->join('mod_version_spt_version as mvsv', 'mv.id', '=', 'mvsv.mod_version_id')
            ->join('spt_versions as sv', 'mvsv.spt_version_id', '=', 'sv.id')
            ->whereIn('sv.version_major', $majorVersions)
            ->whereIn('sv.version_minor', $minorVersions)
            ->whereIn('sv.version_patch', $patchVersions)
            ->where('sv.version', '!=', '0.0.0')
            ->groupBy('mods.id')
            ->distinct();
    }
}
