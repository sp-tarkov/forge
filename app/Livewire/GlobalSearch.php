<?php

namespace App\Livewire;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

class GlobalSearch extends Component
{
    /**
     * The search query.
     */
    public string $query = '';

    /**
     * Whether to show the search result dropdown.
     */
    public bool $showDropdown = false;

    /**
     * Whether to show the "no results found" message.
     */
    public bool $noResults = false;

    public function render(): View
    {
        return view('livewire.global-search', [
            'results' => $this->executeSearch($this->query),
        ]);
    }

    /**
     * Execute the search against each of the searchable models.
     */
    protected function executeSearch(string $query): array
    {
        $query = Str::trim($query);
        $results = ['data' => [], 'total' => 0];

        if (Str::length($query) > 0) {
            $results['data'] = [
                'user' => $this->fetchUserResults($query),
                'mod' => $this->fetchModResults($query),
            ];
            $results['total'] = $this->countTotalResults($results['data']);
        }

        $this->noResults = $results['total'] === 0;

        return $results;
    }

    /**
     * Fetch the user search results.
     */
    protected function fetchUserResults(string $query): Collection
    {
        /** @var array<int, array<string, mixed>> $userHits */
        $userHits = User::search($query)->raw()['hits'];

        return collect($userHits);
    }

    /**
     * Fetch the mod search results.
     */
    protected function fetchModResults(string $query): Collection
    {
        /** @var array<int, array<string, mixed>> $modHits */
        $modHits = Mod::search($query)->raw()['hits'];

        return collect($modHits);
    }

    /**
     * Count the total number of results across all models.
     */
    protected function countTotalResults(array $results): int
    {
        return collect($results)->reduce(function (int $carry, Collection $result) {
            return $carry + $result->count();
        }, 0);
    }
}
