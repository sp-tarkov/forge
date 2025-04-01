<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\SptVersion;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use ModeratesMod;
    use WithPagination;

    /**
     * The search query value.
     */
    #[Session]
    #[Url]
    public string $query = '';

    /**
     * The sort order value.
     */
    #[Session]
    #[Url]
    public string $order = 'created';

    /**
     * The number of results to show on a single page.
     */
    #[Session]
    #[Url]
    public int $perPage = 12;

    /**
     * The options that are available for the per page setting.
     *
     * @var array<int>
     */
    #[Locked]
    public array $perPageOptions = [6, 12, 24, 50];

    /**
     * The SPT versions filter value.
     *
     * @var array<int, string>|null
     */
    #[Session]
    #[Url(as: 'versions')]
    public ?array $sptVersions = null;

    /**
     * The featured filter value.
     */
    #[Session]
    #[Url]
    public string $featured = 'include';

    /**
     * The available SPT versions.
     *
     * @var Collection<int, SptVersion>
     */
    public Collection $availableSptVersions;

    /**
     * Called when a component is created.
     */
    public function mount(): void
    {
        // Fetch all versions in the last three minor versions.
        $this->availableSptVersions ??= Cache::remember(
            'active-spt-versions',
            600,
            fn (): Collection => SptVersion::getVersionsForLastThreeMinors()
        );

        // Only set the default version filter values if the property is empty after URL and session hydration.
        if ($this->sptVersions === null) {
            $this->sptVersions = $this->defaultSptVersions();
        }
    }

    /**
     * The method to reset the filters.
     */
    public function resetFilters(): void
    {
        $this->query = '';
        $this->sptVersions = $this->defaultSptVersions();
        $this->featured = 'include';
    }

    /**
     * Fetch the default value for the sptVersions property.
     *
     * @return array<int, string>
     */
    public function defaultSptVersions(): array
    {
        return SptVersion::getLatestMinorVersions()->pluck('version')->toArray();
    }

    /**
     * Validate that the selected perPage value is an allowed option, resetting to the closest valid option.
     */
    public function updatedPerPage(int $value): void
    {
        $allowed = collect($this->perPageOptions)->sort()->values();

        if ($allowed->contains($value)) {
            return; // The value is allowed.
        }

        // Find the closest allowed value.
        $this->perPage = $allowed->sortBy(fn ($item): int => abs($item - $value))->first();
    }

    /**
     * Validate the order value.
     */
    public function updatedOrder(string $value): void
    {
        if (! in_array($value, ['created', 'updated', 'downloaded'])) {
            $this->order = 'created';
        }
    }

    /**
     * Check if the current page is greater than the last page. Redirect if it is.
     *
     * @param  LengthAwarePaginator<Mod>  $paginatedMods
     */
    private function redirectOutOfBoundsPage(LengthAwarePaginator $paginatedMods): void
    {
        if ($paginatedMods->currentPage() > $paginatedMods->lastPage()) {
            $this->redirectRoute('mods', ['page' => $paginatedMods->lastPage()]);
        }
    }

    /**
     * Compute the split of the active SPT versions.
     *
     * @return array<int, Collection<int, SptVersion>>
     */
    #[Computed]
    public function splitSptVersions(): array
    {
        $versions = $this->availableSptVersions;
        $half = (int) ceil($versions->count() / 2);

        return [
            $versions->take($half),
            $versions->slice($half),
        ];
    }

    /**
     * Compute the count of active filters.
     */
    #[Computed]
    public function filterCount(): int
    {
        $count = 0;
        if ($this->query !== '') {
            $count++;
        }

        if ($this->featured !== 'include') {
            $count++;
        }

        return $count + count($this->sptVersions);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        // Fetch the mods using the filters saved to the component properties.
        $filters = new ModFilter([
            'query' => $this->query,
            'featured' => $this->featured,
            'order' => $this->order,
            'sptVersions' => $this->sptVersions,
        ]);

        $paginatedMods = $filters->apply()->paginate($this->perPage);

        $this->redirectOutOfBoundsPage($paginatedMods);

        return view('livewire.page.mod.index', ['mods' => $paginatedMods]);
    }
}
