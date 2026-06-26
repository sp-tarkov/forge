<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Exceptions\Api\V0\InvalidQueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use LogicException;

/**
 * @template TModel of Model
 */
abstract class AbstractQueryBuilder
{
    /**
     * The base query builder instance.
     *
     * @var Builder<TModel>
     */
    protected Builder $builder;

    /**
     * The filters to apply to the query.
     *
     * @var array<string, mixed>
     */
    protected array $filters = [];

    /**
     * The relationships to include in the query.
     *
     * @var array<string>
     */
    protected array $includes = [];

    /**
     * The fields to select in the query.
     *
     * @var array<string>
     */
    protected array $fields = [];

    /**
     * The sort parameters for the query.
     *
     * @var array<string>
     */
    protected array $sorts = [];

    /**
     * The search query to use with Scout.
     */
    protected ?string $searchQuery = null;

    /**
     * Create a new query builder instance.
     */
    public function __construct()
    {
        $this->builder = $this->getBaseQuery();
    }

    /**
     * Get the base query for the model.
     *
     * @return Builder<TModel>
     */
    abstract protected function getBaseQuery(): Builder;

    /**
     * Get the model class for this query builder.
     *
     * @return class-string<TModel>
     */
    abstract protected function getModelClass(): string;

    /**
     * Get the allowed filters for this query builder. Keys being the filter names and values being the names of the
     * methods that apply the filter to the builder.
     *
     * @return array<string, string>
     */
    abstract public static function getAllowedFilters(): array;

    /**
     * Get a map of API include names to model relationship names.
     *
     * @return array<string, string|array<array<string>>>
     */
    abstract public static function getAllowedIncludes(): array;

    /**
     * Get the allowed fields for this query builder.
     *
     * @return array<string>
     */
    abstract public static function getAllowedFields(): array;

    /**
     * Get the allowed sorts for this query builder.
     *
     * @return array<string>
     */
    abstract public static function getAllowedSorts(): array;

    /**
     * Get the required fields that should always be loaded for relationships. These fields are not subject to field
     * white-listing and will be automatically included when needed.
     *
     * @return array<string>
     */
    abstract public static function getRequiredFields(): array;

    /**
     * Get all allowed fields (database columns and dynamic attributes).
     *
     * @return array<string>
     */
    final public static function getAllAllowedFields(): array
    {
        return array_merge(
            static::getAllowedFields(),
            array_keys(static::getDynamicAttributes())
        );
    }

    /**
     * Apply the filters to the query.
     *
     * @return Builder<TModel>
     */
    final public function apply(): Builder
    {
        $this->applyFilters();
        $this->applyIncludes();
        $this->applyFields();
        $this->applySorts();
        $this->applySearch();

        return $this->builder;
    }

    /**
     * Set the filters for the query.
     *
     * @param  array<string, mixed>|null  $filters
     * @return self<TModel>
     */
    final public function withFilters(?array $filters): self
    {
        if ($filters !== null) {
            $this->filters = $filters;
        }

        return $this;
    }

    /**
     * Set the includes for the query.
     *
     * @param  array<string>|null  $includes
     * @return self<TModel>
     */
    final public function withIncludes(?array $includes): self
    {
        if ($includes !== null) {
            $this->includes = $includes;
        }

        return $this;
    }

    /**
     * Set the fields for the query.
     *
     * @param  array<string>|null  $fields
     * @return self<TModel>
     */
    final public function withFields(?array $fields): self
    {
        if ($fields !== null) {
            $this->fields = $fields;
        }

        return $this;
    }

    /**
     * Set the sorts for the query.
     *
     * @param  array<string>|null  $sorts
     * @return self<TModel>
     */
    final public function withSorts(?array $sorts): self
    {
        if ($sorts !== null) {
            $this->sorts = $sorts;
        }

        return $this;
    }

    /**
     * Set the search query for the query.
     *
     * @return self<TModel>
     */
    final public function withSearch(?string $query): self
    {
        if ($query !== null) {
            $this->searchQuery = $query;
        }

        return $this;
    }

    /**
     * Get the results of the query.
     *
     * @return Collection<int, TModel>
     */
    final public function get(): Collection
    {
        /** @var Collection<int, TModel> */
        return $this->apply()->get();
    }

    /**
     * Get the paginated results of the query.
     *
     * @return LengthAwarePaginator<int, TModel>
     */
    final public function paginate(int $perPage = 12, int $allowed_max = 50): LengthAwarePaginator
    {
        $perPage = min($perPage, $allowed_max);

        $builder = $this->apply();

        return $builder->paginate($perPage, ['*'], 'page', null, $this->resolveTotal($builder));
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    final public function findOrFail(int $id): Model
    {
        return $this->apply()->findOrFail($id);
    }

    /**
     * Get the dynamic attributes that can be included in the response. The keys are the attribute names, and the values
     * are arrays of required database fields.
     *
     * @return array<string, array<string>>
     */
    protected static function getDynamicAttributes(): array
    {
        return [];
    }

    /**
     * Parse a boolean input value.
     */
    protected static function parseBooleanInput(string $value): bool
    {
        return in_array(mb_strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Parse a comma-separated input value.
     *
     * @return array<mixed>
     */
    protected static function parseCommaSeparatedInput(string $value, ?string $castReturn = null): array
    {
        $values = array_filter(explode(',', $value), fn (string $value): bool => $value !== '' && $value !== '0');

        if ($castReturn === null) {
            return $values;
        }

        return array_map(fn (string $value): mixed => match ($castReturn) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => self::parseBooleanInput($value),
            default => $value,
        }, $values);
    }

    /**
     * The cache signature that uniquely identifies this query's guest total, or null to disable count caching.
     *
     * Returning a value opts the builder into caching, so the signature MUST capture every input that changes the
     * result count: all filters, search terms, and any constructor-injected scoping. It must NOT include the page,
     * per-page, or sort, none of which affect the total.
     *
     * @return array<mixed>|null
     */
    protected function countCacheSignature(): ?array
    {
        return null;
    }

    /**
     * The number of seconds a cached guest pagination total remains valid.
     */
    protected function countCacheTtl(): int
    {
        return config()->integer('api.pagination.count_cache_ttl', 60);
    }

    /**
     * Apply the search query using Scout if available.
     */
    protected function applySearch(): void
    {
        if ($this->searchQuery === null || $this->searchQuery === '') {
            return;
        }

        $modelClass = $this->getModelClass();
        $model = new $modelClass;

        if (! in_array(Searchable::class, class_uses_recursive($model), true)) {
            // Model doesn't use Searchable trait, cannot perform Scout search.
            return;
        }

        // Get the search results with their relevance ordering and ranking scores (model uses Searchable trait,
        // verified above)
        throw_unless(method_exists($model, 'search'), LogicException::class, 'Searchable trait is present but search() method is missing.');

        /** @var \Laravel\Scout\Builder<Model> $scoutBuilder */
        $scoutBuilder = $model->search($this->searchQuery);
        /** @var array{hits?: array<int, array<string, mixed>>} $searchResults */
        $searchResults = $scoutBuilder->options(['showRankingScore' => true])->raw();

        if (empty($searchResults['hits'])) {
            // If no search results, force no records to be returned
            $this->builder->whereRaw('1 = 0');

            return;
        }

        // Sort results by version segments first, then by ranking score
        /** @var array<int, array<string, mixed>> $hits */
        $hits = $searchResults['hits'];
        $sortedHits = collect($hits)
            ->sortBy([
                ['latestVersionMajor', 'desc'],
                ['latestVersionMinor', 'desc'],
                ['latestVersionPatch', 'desc'],
                ['latestVersionLabel', 'asc'],
                ['_rankingScore', 'desc'],
            ])
            ->values();

        // Get the IDs in the sorted order
        /** @var list<int|string> $orderedIds */
        $orderedIds = $sortedHits->pluck('id')->all();

        // If IDs array is empty after pluck (e.g., all hits lacked 'id'), force no records
        if (empty($orderedIds)) {
            $this->builder->whereRaw('1 = 0');

            return;
        }

        // Filter the main query by these IDs and preserve the order from Scout
        $this->builder->whereIn($model->getQualifiedKeyName(), $orderedIds);
        $this->preserveScoutOrder($model, $orderedIds);
    }

    /**
     * Apply the filters to the query.
     *
     * @throws InvalidQueryException
     */
    protected function applyFilters(): void
    {
        if ($this->filters === []) {
            return;
        }

        $allowedFilters = static::getAllowedFilters();
        $allowedFilterKeys = array_keys($allowedFilters);
        $requestedFilterKeys = array_keys($this->filters);

        $invalidFilters = array_diff($requestedFilterKeys, $allowedFilterKeys);

        if ($invalidFilters !== []) {
            $invalidFilter = implode(', ', $invalidFilters);
            $validFilters = implode(', ', $allowedFilterKeys);
            throw new InvalidQueryException(
                sprintf('Invalid filter(s): %s. Valid filters are: %s', $invalidFilter, $validFilters)
            );
        }

        foreach ($this->filters as $filter => $value) {
            // Filter values arrive as comma-separated strings; bracket-array syntax such as filter[id][]=1 or
            // filter[id][neq]=2 yields an array that the string-typed filter methods cannot accept. Reject it as a
            // client error rather than letting it surface as a 500 TypeError.
            if ($value !== null && ! is_string($value)) {
                throw new InvalidQueryException(
                    sprintf("The '%s' filter must be a single value. Provide multiple values as a comma-separated string (e.g. filter[%s]=value1,value2).", $filter, $filter)
                );
            }

            $method = $allowedFilters[$filter];
            $this->{$method}($this->builder, $value);
        }
    }

    /**
     * Check if a specific filter is being used in the current request.
     */
    protected function hasFilter(string $filterName): bool
    {
        return request()->has('filter.'.$filterName);
    }

    /**
     * Apply includes to the query.
     *
     * @throws InvalidQueryException
     */
    protected function applyIncludes(): void
    {
        if ($this->includes !== []) {
            $this->includes = array_filter($this->includes, fn (?string $include): bool => $include !== null && $include !== '');
            if ($this->includes === []) {
                return; // All includes were empty and filtered out, return early.
            }

            $allowedIncludes = static::getAllowedIncludes();

            // Check if we have a key-value array or a simple array
            $isKeyValueArray = $allowedIncludes !== [] && ! is_numeric(key($allowedIncludes));

            if ($isKeyValueArray) {
                /** @var array<string, string|array<array<string>>> $allowedIncludes */
                $invalidIncludes = array_diff($this->includes, array_keys($allowedIncludes));
                $validIncludes = array_keys($allowedIncludes);

                // Check for invalid includes
                if ($invalidIncludes !== []) {
                    $invalidInclude = implode(', ', $invalidIncludes);
                    $validIncludeList = implode(', ', $validIncludes);
                    throw new InvalidQueryException(
                        sprintf('Invalid parameter(s): %s. Valid parameters are: %s', $invalidInclude, $validIncludeList)
                    );
                }

                // Map API includes to actual relationships
                $relationships = [];
                foreach ($this->includes as $include) {
                    $relationship = $allowedIncludes[$include];
                    if (is_array($relationship)) {
                        $relationships = array_merge($relationships, $relationship);
                    } else {
                        $relationships[] = $relationship;
                    }
                }

                $this->builder->with($relationships);
            } else {
                /** @var array<string> $allowedIncludes */
                $invalidIncludes = array_diff($this->includes, $allowedIncludes);
                $validIncludes = $allowedIncludes;

                if ($invalidIncludes !== []) {
                    $invalidInclude = implode(', ', $invalidIncludes);
                    $validIncludeList = implode(', ', $validIncludes);
                    throw new InvalidQueryException(
                        sprintf('Invalid parameter(s): %s. Valid parameters are: %s', $invalidInclude, $validIncludeList)
                    );
                }

                $this->builder->with($this->includes);
            }
        }
    }

    /**
     * Apply the fields to the query.
     *
     * @throws InvalidQueryException
     */
    protected function applyFields(): void
    {
        $fields = array_filter($this->fields, fn (?string $field): bool => $field !== null && $field !== '');
        $requiredFields = array_filter(static::getRequiredFields(), fn (string $field): bool => $field !== '' && $field !== '0');

        if ($fields !== []) {
            // Get fields that need validation (excluding required fields)
            $fieldsToValidate = array_diff($fields, $requiredFields);

            if ($fieldsToValidate !== []) {
                $allowedFields = self::getAllAllowedFields();
                $invalidFields = array_diff($fieldsToValidate, $allowedFields);

                if ($invalidFields !== []) {
                    $invalidField = implode(', ', $invalidFields);
                    $validFields = implode(', ', $allowedFields);
                    throw new InvalidQueryException(
                        sprintf('Invalid field(s): %s. Valid fields are: %s', $invalidField, $validFields)
                    );
                }
            }

            // Get dynamic attributes that are requested
            $dynamicAttributes = static::getDynamicAttributes();
            $requestedDynamicAttributes = array_intersect($fields, array_keys($dynamicAttributes));

            // Get required database fields for requested dynamic attributes
            $requiredDependencies = [];
            foreach ($requestedDynamicAttributes as $attribute) {
                $requiredDependencies = array_merge($requiredDependencies, $dynamicAttributes[$attribute]);
            }

            // Filter out dynamic attributes from the select statement
            $dbFields = array_filter($fields, fn (string $field): bool => ! array_key_exists($field, $dynamicAttributes));

            // Merge required fields, database fields, and dynamic attribute dependencies
            $this->builder->select(array_merge($dbFields, $requiredFields, $requiredDependencies));
        } else {
            // When no fields are specified, include all allowed fields plus required fields
            // and all dynamic attribute dependencies
            $allowedFields = static::getAllowedFields();
            $dynamicDependencies = array_merge(...array_values(static::getDynamicAttributes()) ?: [[]]);
            $this->builder->select(array_unique(array_merge($allowedFields, $requiredFields, $dynamicDependencies)));
        }
    }

    /**
     * Apply the sorts to the query.
     *
     * @throws InvalidQueryException
     */
    protected function applySorts(): void
    {
        if ($this->sorts !== []) {
            $this->sorts = array_filter($this->sorts, fn (?string $sort): bool => $sort !== null && $sort !== '');
            if ($this->sorts === []) {
                return; // All sorts were empty and filtered out, return early.
            }

            $allowedSorts = static::getAllowedSorts();
            $invalidSorts = [];

            foreach ($this->sorts as $sort) {
                $cleanName = Str::startsWith($sort, '-') ? Str::substr($sort, 1) : $sort;
                if (! in_array($cleanName, $allowedSorts, true)) {
                    $invalidSorts[] = $sort;
                }
            }

            if ($invalidSorts !== []) {
                $invalidSort = implode(', ', $invalidSorts);
                $validSorts = implode(', ', $allowedSorts);
                throw new InvalidQueryException(
                    sprintf('Invalid sort parameter(s): %s. Valid sorts are: %s', $invalidSort, $validSorts)
                );
            }

            foreach ($this->sorts as $sort) {
                $isReverse = Str::startsWith($sort, '-');
                $column = $isReverse ? Str::substr($sort, 1) : $sort;
                $this->builder->orderBy($column, $isReverse ? 'desc' : 'asc');
            }
        }
    }

    /**
     * Resolve the total row count used to build the paginator, caching it for guests when the builder opts in.
     *
     * The total is the most expensive part of a paginated request: a correlated COUNT that ignores the page and scans
     * the whole visible set. For an anonymous listing it only changes when records are published or hidden, so builders
     * that return a stable signature from countCacheSignature() have their guest total cached. Authenticated totals
     * depend on per-user visibility (PublishedScope) and are always computed live.
     *
     * @param  Builder<TModel>  $builder
     */
    private function resolveTotal(Builder $builder): int
    {
        $count = fn (): int => (clone $builder)->toBase()->getCountForPagination();

        $signature = $this->countCacheSignature();

        if ($signature === null || Auth::check()) {
            return $count();
        }

        $key = 'api:pagination-count:'.md5(serialize([static::class, $signature]));

        return Cache::remember($key, $this->countCacheTtl(), $count);
    }

    /**
     * Preserve search result ordering using FIELD() with parameterized bindings.
     *
     * @param  TModel  $model
     * @param  list<int|string>  $orderedIds
     */
    private function preserveScoutOrder(Model $model, array $orderedIds): void
    {
        // Build CASE WHEN ordering to preserve Scout relevance order without dynamic SQL
        $intIds = array_map(intval(...), $orderedIds);
        $bindings = [];
        $cases = [];

        foreach ($intIds as $position => $id) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $position;
        }

        $caseExpression = 'CASE '.$model->getQualifiedKeyName().' '.implode(' ', $cases).' END';
        $this->builder->getQuery()->orders[] = [
            'type' => 'Raw',
            'sql' => $caseExpression,
        ];
        $this->builder->getQuery()->addBinding($bindings, 'order');
    }
}
