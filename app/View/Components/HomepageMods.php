<?php

namespace App\View\Components;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component;

class HomepageMods extends Component
{
    public function render(): View
    {
        return view('components.homepage-mods', [
            'featured' => [
                'title' => __('Featured Mods'),
                'mods' => $this->fetchFeaturedMods(),
                'link' => '/mods?featured=only',
            ],
            'latest' => [
                'title' => __('Newest Mods'),
                'mods' => $this->fetchLatestMods(),
                'link' => '/mods',
            ],
            'updated' => [
                'title' => __('Recently Updated Mods'),
                'mods' => $this->fetchUpdatedMods(),
                'link' => '/mods?order=updated',
            ],
        ]);
    }

    /**
     * Fetches the featured mods homepage listing.
     *
     * @return Collection<int, Mod>
     */
    private function fetchFeaturedMods(): Collection
    {
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'downloads', 'updated_at'])
            ->with([
                'latestVersion.latestSptVersion:id,version,color_class',
                'users:id,name',
                'license:id,name,link',
            ])
            ->whereFeatured(true)
            ->inRandomOrder()
            ->limit(6)
            ->get();
    }

    /**
     * Fetches the latest mods homepage listing.
     *
     * @return Collection<int, Mod>
     */
    private function fetchLatestMods(): Collection
    {
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'created_at', 'downloads', 'updated_at'])
            ->with([
                'latestVersion.latestSptVersion:id,version,color_class',
                'users:id,name',
                'license:id,name,link',
            ])
            ->latest()
            ->limit(6)
            ->get();
    }

    /**
     * Fetches the recently updated mods homepage listing.
     *
     * @return Collection<int, Mod>
     */
    private function fetchUpdatedMods(): Collection
    {
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'downloads', 'updated_at'])
            ->with([
                'lastUpdatedVersion.latestSptVersion:id,version,color_class',
                'users:id,name',
                'license:id,name,link',
            ])
            ->joinSub(
                ModVersion::select('mod_id', DB::raw('MAX(updated_at) as latest_updated_at'))->groupBy('mod_id'),
                'latest_versions',
                'mods.id',
                '=',
                'latest_versions.mod_id'
            )
            ->orderByDesc('latest_versions.latest_updated_at')
            ->limit(6)
            ->get();
    }
}
