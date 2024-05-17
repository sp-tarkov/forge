<?php

namespace App\View\Components;

use App\Models\Mod;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class LatestModListing extends Component
{
    public function render(): View
    {
        return view('components.latest-mod-listing', [
            'latestMods' => Mod::with('versionWithHighestSptVersion.sptVersion')->latest()->take(6)->get(),
        ]);
    }
}
