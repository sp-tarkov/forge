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
     *
     * @var array<string, Collection<int, mixed>>
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
     *
     * @return array<string, Collection<int, mixed>>
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
     *
     * @return Collection<int, mixed>
     */
    protected function fetchUserResults(string $query): Collection
    {
        /** @var Collection<int, mixed> $searchHits */
        $searchHits = User::search($query)->raw()['hits'];

        return collect($searchHits);
    }

    /**
     * Fetch the mod search results.
     *
     * @return Collection<int, mixed>
     */
    protected function fetchModResults(string $query): Collection
    {
        /** @var Collection<int, mixed> $searchHits */
        $searchHits = Mod::search($query)->raw()['hits'];

        return collect($searchHits);
    }

    /**
     * Count the total number of results across all models.
     *
     * @param  array<string, Collection<int, mixed>>  $results
     */
    protected function countTotalResults(array $results): int
    {
        return (int) collect($results)->reduce(fn (int $carry, Collection $result): int => $carry + $result->count(), 0);
    }
}
