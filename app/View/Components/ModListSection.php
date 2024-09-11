<?php

namespace App\View\Components;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component;

class ModListSection extends Component
{
    public Collection $modsFeatured;

    public Collection $modsLatest;

    public Collection $modsUpdated;

    public function __construct()
    {
        $this->modsFeatured = $this->fetchFeaturedMods();
        $this->modsLatest = $this->fetchLatestMods();
        $this->modsUpdated = $this->fetchUpdatedMods();
    }

    private function fetchFeaturedMods(): Collection
    {
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'downloads'])
            ->with([
                'latestVersion',
                'latestVersion.latestSptVersion:id,version,color_class',
                'users:id,name',
                'license:id,name,link',
            ])
            ->whereFeatured(true)
            ->inRandomOrder()
            ->limit(6)
            ->get();
    }

    private function fetchLatestMods(): Collection
    {
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'created_at', 'downloads'])
            ->with([
                'latestVersion',
                'latestVersion.latestSptVersion:id,version,color_class',
                'users:id,name',
                'license:id,name,link',
            ])
            ->latest()
            ->limit(6)
            ->get();
    }

    private function fetchUpdatedMods(): Collection
    {
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'downloads'])
            ->with([
                'lastUpdatedVersion',
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

    public function render(): View
    {
        return view('components.mod-list-section', [
            'sections' => $this->getSections(),
        ]);
    }

    public function getSections(): array
    {
        return [
            [
                'title' => __('Featured Mods'),
                'mods' => $this->modsFeatured,
                'versionScope' => 'latestVersion',
                'link' => '/mods?featured=only',
            ],
            [
                'title' => __('Newest Mods'),
                'mods' => $this->modsLatest,
                'versionScope' => 'latestVersion',
                'link' => '/mods',
            ],
            [
                'title' => __('Recently Updated Mods'),
                'mods' => $this->modsUpdated,
                'versionScope' => 'lastUpdatedVersion',
                'link' => '/mods?order=updated',
            ],
        ];
    }
}
