<?php

namespace App\View\Components;

use App\Models\Mod;
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
        return Mod::with('versionLatestSptVersion.sptVersion')->whereFeatured(true)->take(6)->get();
    }

    private function fetchLatestMods(): Collection
    {
        return Mod::with('versionLatestSptVersion.sptVersion')->latest()->take(6)->get();
    }

    private function fetchUpdatedMods(): Collection
    {
        return Mod::with('versionLastUpdated.sptVersion')->take(6)->get();
    }

    public function getSections(): array
    {
        return [
            [
                'title' => 'Featured Mods',
                'mods' => $this->modsFeatured,
                'versionScope' => 'versionLatestSptVersion',
            ],
            [
                'title' => 'Latest Mods',
                'mods' => $this->modsLatest,
                'versionScope' => 'versionLatestSptVersion',
            ],
            [
                'title' => 'Recently Updated Mods',
                'mods' => $this->modsUpdated,
                'versionScope' => 'versionLastUpdated',
            ],
        ];
    }

    public function render(): View
    {
        return view('components.mod-list-section', [
            'sections' => $this->getSections(),
        ]);
    }
}
