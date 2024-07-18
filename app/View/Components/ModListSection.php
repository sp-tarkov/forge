<?php

namespace App\View\Components;

use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
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
        return Cache::remember('homepage-featured-mods', now()->addMinutes(5), function () {
            return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured'])
                ->withTotalDownloads()
                ->with(['latestSptVersion', 'users:id,name'])
                ->where('featured', true)
                ->latest()
                ->limit(6)
                ->get();
        });
    }

    private function fetchLatestMods(): Collection
    {
        return Cache::remember('homepage-latest-mods', now()->addMinutes(5), function () {
            return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'created_at'])
                ->withTotalDownloads()
                ->with(['latestSptVersion', 'users:id,name'])
                ->latest()
                ->limit(6)
                ->get();
        });
    }

    private function fetchUpdatedMods(): Collection
    {
        return Cache::remember('homepage-updated-mods', now()->addMinutes(5), function () {
            return Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured'])
                ->withTotalDownloads()
                ->with(['lastUpdatedVersion', 'users:id,name'])
                ->orderByDesc(
                    ModVersion::select('updated_at')
                        ->whereColumn('mod_id', 'mods.id')
                        ->orderByDesc('updated_at')
                        ->take(1)
                )
                ->limit(6)
                ->get();
        });
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
                'versionScope' => 'latestSptVersion',
            ],
            [
                'title' => 'Newest Mods',
                'mods' => $this->modsLatest,
                'versionScope' => 'latestSptVersion',
            ],
            [
                'title' => 'Recently Updated Mods',
                'mods' => $this->modsUpdated,
                'versionScope' => 'lastUpdatedVersion',
            ],
        ];
    }
}
