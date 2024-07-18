<?php

namespace App\Livewire;

use App\Models\Mod;
use App\Models\User;
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

        if (Str::length($query)) {
            $results['data'] = [
                'user' => collect(User::search($query)->raw()['hits']),
                'mod' => collect(Mod::search($query)->raw()['hits']),
            ];
            $results['total'] = $this->countTotalResults($results['data']);
        }

        $this->showDropdown = Str::length($query) > 0;
        $this->noResults = $results['total'] === 0 && $this->showDropdown;

        return $results;
    }

    /**
     * Count the total number of results across all models.
     */
    protected function countTotalResults($results): int
    {
        return collect($results)->reduce(function ($carry, $result) {
            return $carry + $result->count();
        }, 0);
    }

    /**
     * Clear the search query and hide the dropdown.
     */
    public function clearSearch(): void
    {
        $this->query = '';
        $this->showDropdown = false;
        $this->noResults = false;
    }
}
