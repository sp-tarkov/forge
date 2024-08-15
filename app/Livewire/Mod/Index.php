<?php

namespace App\Livewire\Mod;

use App\Http\Filters\ModFilter;
use App\Models\SptVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // TODO: These `Url` properties should be saved to the browser's local storage to persist the filters.

    /**
     * The search query value.
     */
    #[Url]
    public string $query = '';

    /**
     * The sort order value.
     */
    #[Url]
    public string $order = 'created';

    /**
     * The SPT version filter value.
     */
    #[Url]
    public array $sptVersion = [];

    /**
     * The featured filter value.
     */
    #[Url]
    public string $featured = 'include';

    /**
     * The available SPT versions.
     */
    public Collection $availableSptVersions;

    /**
     * The component mount method, run only once when the component is mounted.
     */
    public function mount(): void
    {
        $this->availableSptVersions = SptVersion::select(['id', 'version', 'color_class'])->orderByDesc('version')->get();
        $this->sptVersion = $this->getLatestMinorVersions()->pluck('version')->toArray();
    }

    /**
     * Get all hotfix versions of the latest minor SPT version.
     */
    public function getLatestMinorVersions(): Collection
    {
        return $this->availableSptVersions->filter(function (SptVersion $sptVersion) {
            return $sptVersion->isLatestMinor();
        });
    }

    /**
     * The component mount method.
     */
    public function render(): View
    {
        // Fetch the mods using the filters saved to the component properties.
        $filters = [
            'query' => $this->query,
            'featured' => $this->featured,
            'order' => $this->order,
            'sptVersion' => $this->sptVersion,
        ];
        $mods = (new ModFilter($filters))->apply()->paginate(24);

        return view('livewire.mod.index', compact('mods'));
    }

    /**
     * The method to reset the filters.
     */
    public function resetFilters(): void
    {
        $this->query = '';
        $this->order = 'created';
        $this->sptVersion = $this->getLatestMinorVersions()->pluck('version')->toArray();
        $this->featured = 'include';
    }
}
