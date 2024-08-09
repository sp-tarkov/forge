<?php

namespace App\Livewire;

use App\Models\Mod;
use Livewire\Component;
use Livewire\WithPagination;

class ModIndex extends Component
{
    use WithPagination;

    public $sectionFilter = 'new';

    public function render()
    {
        $mods = Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'created_at'])
            ->withTotalDownloads()
            ->with(['latestVersion', 'latestVersion.sptVersion', 'users:id,name'])
            ->whereHas('latestVersion')
            ->latest()
            ->paginate(12);

        return view('livewire.mod-index', ['mods' => $mods]);
    }

    public function changeSection($section): void
    {
        $this->sectionFilter = $section;
    }
}
