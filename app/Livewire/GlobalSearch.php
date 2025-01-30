<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GlobalSearch extends Component
{
    /**
     * The search query.
     */
    public string $query = '';

    /**
     * The search results.
     */
    #[Locked]
    public array $result = [];

    /**
     * The total number of search results.
     */
    #[Locked]
    public int $count = 0;

    /**
     * Render the component.
     */
    public function render(): View
    {
        $this->result = $this->executeSearch($this->query);
        $this->count = $this->countTotalResults($this->result);

        return view('livewire.global-search');
    }

    /**
     * Execute the search against each of the searchable models.
     */
    protected function executeSearch(string $query): array
    {
        $query = Str::trim($query);

        if (Str::length($query) > 0) {
            return [
                'user' => $this->fetchUserResults($query),
                'mod' => $this->fetchModResults($query),
            ];
        }

        return [];
    }

    /**
     * Fetch the user search results.
     */
    protected function fetchUserResults(string $query): Collection
    {
        return collect(User::search($query)->raw()['hits']);
    }

    /**
     * Fetch the mod search results.
     */
    protected function fetchModResults(string $query): Collection
    {
        return collect(Mod::search($query)->raw()['hits']);
    }

    /**
     * Count the total number of results across all models.
     */
    protected function countTotalResults(array $results): int
    {
        return collect($results)->reduce(fn (int $carry, Collection $result): int => $carry + $result->count(), 0);
    }
}
