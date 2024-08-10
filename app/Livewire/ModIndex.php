<?php

namespace App\Livewire;

use App\Models\Mod;
use App\Models\SptVersion;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ModIndex extends Component
{
    use WithPagination;

    public string $modSearch = '';

    public string $sectionFilter = 'featured';

    public string $versionFilter = '';

    public function render()
    {
        // 'featured' section is default
        $section = 'featured';

        switch ($this->sectionFilter) {
            case 'new':
                $section = 'created_at';
                break;
            case 'most_downloaded':
                $section = 'total_downloads';
                break;
            case 'recently_updated':
                $section = 'updated_at';
                break;
            case 'top_rated':
                // probably use some kind of 'likes' or something
                // not implemented yet afaik -waffle
                break;
        }

        $mods = Mod::select(['id', 'name', 'slug', 'teaser', 'thumbnail', 'featured', 'created_at'])
            ->withTotalDownloads()
            ->with(['latestVersion', 'latestVersion.sptVersion', 'users:id,name'])
            ->whereHas('latestVersion')
            ->whereHas('latestVersion.sptVersion', function ($query) {
                $query->where('version', 'like', '%'.Str::trim($this->versionFilter).'%');
            })
            ->where('name', 'like', '%'.Str::trim($this->modSearch).'%')
            ->orderByDesc($section)
            ->paginate(12);

        $sptVersions = SptVersion::select(['id', 'version', 'color_class'])->orderByDesc('version')->get();

        return view('livewire.mod-index', ['mods' => $mods, 'sptVersions' => $sptVersions]);
    }

    public function changeSection($section): void
    {
        $this->sectionFilter = $section;
    }
}
