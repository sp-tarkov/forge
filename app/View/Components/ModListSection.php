<?php

namespace App\View\Components;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured'])
            ->withTotalDownloads()
            ->with([
                'latestVersion',
                'latestVersion.latestSptVersion:id,version,color_class',
                'users:id,name',
                'license:id,name,link',
            ])
            ->where('featured', true)
            ->latest()
            ->limit(6)
            ->get();
    }

    private function fetchLatestMods(): Collection
    {
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'created_at'])
            ->withTotalDownloads()
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
        return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured'])
            ->withTotalDownloads()
            ->with([
                'latestVersion',
                'latestVersion.latestSptVersion:id,version,color_class',
                'users:id,name',
                'license:id,name,link',
            ])
            ->orderByDesc(
                ModVersion::select('updated_at')
                    ->whereColumn('mod_id', 'mods.id')
                    ->orderByDesc('updated_at')
                    ->take(1)
            )
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
                'title' => 'Featured Mods',
                'mods' => $this->modsFeatured,
                'versionScope' => 'latestVersion',
            ],
            [
                'title' => 'Newest Mods',
                'mods' => $this->modsLatest,
                'versionScope' => 'latestVersion',
            ],
            [
                'title' => 'Recently Updated Mods',
                'mods' => $this->modsUpdated,
                'versionScope' => 'lastUpdatedVersion',
            ],
        ];
    }
}
