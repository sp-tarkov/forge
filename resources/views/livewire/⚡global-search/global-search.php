<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Component;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;

new class extends Component
{
    /**
     * The search query.
     */
    public string $query = '';

    /**
     * The session variable for showing Mod category in search results.
     */
    #[Session]
    public bool $isModCatVisible = true;

    /**
     * The session variable for showing User category in search results.
     */
    #[Session]
    public bool $isUserCatVisible = true;

    /**
     * The session variable for showing Addon category in search results.
     */
    #[Session]
    public bool $isAddonCatVisible = true;

    /**
     * Toggle the visibility of a search result category.
     */
    public function toggleTypeVisibility(string $type): void
    {
        $typeProperty = 'is'.ucfirst($type).'CatVisible';
        if (property_exists($this, $typeProperty)) {
            $this->$typeProperty = ! $this->$typeProperty;
        }
    }

    /**
     * Check if a search result category is visible.
     */
    public function isTypeCategoryVisible(string $type): bool
    {
        $typeProperty = 'is'.ucfirst($type).'CatVisible';

        return property_exists($this, $typeProperty) && $this->$typeProperty;
    }

    /**
     * Get the search results using Meilisearch multi-search API.
     *
     * @return array<string, Collection<int, mixed>>
     */
    #[Computed]
    public function results(): array
    {
        $query = Str::trim($this->query);

        if (Str::length($query) === 0) {
            return [];
        }

        return $this->executeMultiSearch($query);
    }

    /**
     * Get the total count of search results.
     */
    #[Computed]
    public function totalCount(): int
    {
        return (int) collect($this->results)->reduce(fn (int $carry, Collection $result): int => $carry + $result->count(), 0);
    }

    /**
     * Check if there are any search results.
     */
    #[Computed]
    public function hasResults(): bool
    {
        return $this->totalCount > 0;
    }

    /**
     * Execute a multi-search query against the configured search driver.
     *
     * @return array<string, Collection<int, mixed>>
     */
    protected function executeMultiSearch(string $query): array
    {
        if (config('scout.driver') === 'meilisearch') {
            return $this->executeMeilisearchMultiSearch($query);
        }

        return $this->executeScoutSearch($query);
    }

    /**
     * Execute a multi-search query using the Meilisearch API directly.
     *
     * @return array<string, Collection<int, mixed>>
     */
    protected function executeMeilisearchMultiSearch(string $query): array
    {
        $client = resolve(Client::class);

        /** @var string $prefix */
        $prefix = config('scout.prefix');

        $queries = [
            new SearchQuery()
                ->setIndexUid($prefix.new Mod()->getTable())
                ->setQuery($query)
                ->setShowRankingScore(true),
            new SearchQuery()
                ->setIndexUid($prefix.new Addon()->getTable())
                ->setQuery($query)
                ->setShowRankingScore(true),
            new SearchQuery()->setIndexUid($prefix.new User()->getTable())->setQuery($query),
        ];

        /** @var array{results: array<int, array{hits: array<int, mixed>}>} $response */
        $response = $client->multiSearch($queries);

        return [
            'mod' => $this->processModResults($response['results'][0]['hits']),
            'addon' => $this->processAddonResults($response['results'][1]['hits']),
            'user' => collect($response['results'][2]['hits']),
        ];
    }

    /**
     * Execute search queries using Laravel Scout's driver-agnostic API.
     *
     * @return array<string, Collection<int, mixed>>
     */
    protected function executeScoutSearch(string $query): array
    {
        /** @var array<int, mixed> $modHits */
        $modHits = Mod::search($query)->get()->map->toSearchableArray()->all();
        /** @var array<int, mixed> $addonHits */
        $addonHits = Addon::search($query)->get()->map->toSearchableArray()->all();

        /** @var array<int, mixed> $userHits */
        $userHits = User::search($query)->get()->map->toSearchableArray()->all();

        return [
            'mod' => $this->processModResults($modHits),
            'addon' => $this->processAddonResults($addonHits),
            'user' => collect($userHits),
        ];
    }

    /**
     * Process and sort mod search results.
     *
     * @param  array<int, mixed>  $hits
     * @return Collection<int, mixed>
     */
    protected function processModResults(array $hits): Collection
    {
        return collect($hits)
            ->sortBy([['latestVersionMajor', 'desc'], ['latestVersionMinor', 'desc'], ['latestVersionPatch', 'desc'], ['latestVersionLabel', 'asc'], ['_rankingScore', 'desc']])
            ->values();
    }

    /**
     * Process and sort addon search results.
     *
     * @param  array<int, mixed>  $hits
     * @return Collection<int, mixed>
     */
    protected function processAddonResults(array $hits): Collection
    {
        return collect($hits)->sortByDesc('_rankingScore')->values();
    }
};
