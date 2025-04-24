<?php

declare(strict_types=1);

namespace App\Support\Api\V0\QueryBuilder;

use App\Exceptions\Api\V0\InvalidQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

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
     * @var array<string, string>
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
     * Get the allowed filters for this query builder.
     *
     * @return array<string, callable>
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
     * Get the required fields that should always be loaded for relationships.
     *
     * @return array<string>
     */
    abstract public static function getRequiredFields(): array;

    /**
     * Get the dynamic attributes that can be included in the response.
     * The keys are the attribute names and the values are arrays of required database fields.
     *
     * @return array<string, array<string>>
     */
    protected static function getDynamicAttributes(): array
    {
        return [];
    }

    /**
     * Get all allowed fields (database columns and dynamic attributes).
     *
     * @return array<string>
     */
    public static function getAllAllowedFields(): array
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
    public function apply(): Builder
    {
        $this->applyFilters();
        $this->applyIncludes();
        $this->applyFields();
        $this->applySorts();
        $this->applySearch();

        return $this->builder;
    }

    /**
     * Apply the search query using Scout if available.
     */
    protected function applySearch(): void
    {
        if (empty($this->searchQuery)) {
            return;
        }

        $modelClass = $this->getModelClass();
        $model = new $modelClass;

        if (! in_array(Searchable::class, class_uses_recursive($model), true)) {
            // Model doesn't use Searchable trait, cannot perform Scout search.
            return;
        }

        /** @phpstan-var TModel&Searchable $model */

        // Get the search results with their relevance ordering
        /** @phpstan-var array{hits: list<array{id: int|string, ...}>, ...} $searchResults */
        $searchResults = $model->search($this->searchQuery)->raw();

        if (empty($searchResults['hits'])) {
            // If no search results, force no records to be returned
            $this->builder->whereRaw('1 = 0');

            return;
        }

        // Get the IDs in the order they were returned from Scout
        /** @var list<int|string> $orderedIds */
        $orderedIds = collect($searchResults['hits'])->pluck('id')->all();

        // If IDs array is empty after pluck (e.g., all hits lacked 'id'), force no records
        if (empty($orderedIds)) {
            $this->builder->whereRaw('1 = 0');

            return;
        }

        // Filter the main query by these IDs and preserve the order from Scout
        $this->builder->whereIn($model->getQualifiedKeyName(), $orderedIds)
            ->orderByRaw('FIELD('.$model->getQualifiedKeyName().', '.implode(',', array_map('intval', $orderedIds)).')');
    }

    /**
     * Apply the filters to the query.
     *
     * @throws InvalidQuery
     */
    protected function applyFilters(): void
    {
        if (empty($this->filters)) {
            return;
        }

        $allowedFilters = static::getAllowedFilters();
        $allowedFilterKeys = array_keys($allowedFilters);
        $requestedFilterKeys = array_keys($this->filters);

        $invalidFilters = array_diff($requestedFilterKeys, $allowedFilterKeys);

        if (! empty($invalidFilters)) {
            $invalidFilter = implode(', ', $invalidFilters);
            $validFilters = implode(', ', $allowedFilterKeys);
            throw new InvalidQuery(
                sprintf('Invalid filter(s): %s. Valid filters are: %s', $invalidFilter, $validFilters)
            );
        }

        foreach ($this->filters as $filter => $value) {
            $method = $allowedFilters[$filter];
            $this->{$method}($this->builder, $value);
        }
    }

    /**
     * Apply includes to the query.
     *
     * @throws InvalidQuery
     */
    protected function applyIncludes(): void
    {
        if (! empty($this->includes)) {
            $this->includes = array_filter($this->includes, fn ($include): bool => ! empty($include));
            if (empty($this->includes)) {
                return; // All includes were empty and filtered out, return early.
            }

            $allowedIncludes = static::getAllowedIncludes();

            // Check if we have a key-value array or a simple array
            $isKeyValueArray = ! empty($allowedIncludes) && ! is_numeric(key($allowedIncludes));

            if ($isKeyValueArray) {
                /** @var array<string, string|array<array<string>>> $allowedIncludes */
                $invalidIncludes = array_diff($this->includes, array_keys($allowedIncludes));
                $validIncludes = array_keys($allowedIncludes);

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
                $this->builder->with($this->includes);
            }

            if (! empty($invalidIncludes)) {
                $invalidInclude = implode(', ', $invalidIncludes);
                $validIncludeList = implode(', ', $validIncludes);
                throw new InvalidQuery(
                    sprintf('Invalid parameter(s): %s. Valid parameters are: %s', $invalidInclude, $validIncludeList)
                );
            }
        }
    }

    /**
     * Apply the fields to the query.
     *
     * @throws InvalidQuery
     */
    protected function applyFields(): void
    {
        $fields = array_filter($this->fields, fn ($field): bool => ! empty($field));
        $requiredFields = array_filter(static::getRequiredFields(), fn ($field): bool => ! empty($field));

        if (! empty($fields)) {
            // Get fields that need validation (excluding required fields)
            $fieldsToValidate = array_diff($fields, $requiredFields);

            if (! empty($fieldsToValidate)) {
                $allowedFields = static::getAllAllowedFields();
                $invalidFields = array_diff($fieldsToValidate, $allowedFields);

                if (! empty($invalidFields)) {
                    $invalidField = implode(', ', $invalidFields);
                    $validFields = implode(', ', $allowedFields);
                    throw new InvalidQuery(
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
            $dbFields = array_filter($fields, fn ($field): bool => ! array_key_exists($field, $dynamicAttributes));

            // Merge required fields, database fields, and dynamic attribute dependencies
            $this->builder->select(array_merge($dbFields, $requiredFields, $requiredDependencies));
        } else {
            // When no fields are specified, include all allowed fields plus required fields
            $allowedFields = static::getAllowedFields();
            $this->builder->select(array_merge($allowedFields, $requiredFields));
        }
    }

    /**
     * Apply the sorts to the query.
     *
     * @throws InvalidQuery
     */
    protected function applySorts(): void
    {
        if (! empty($this->sorts)) {
            $this->sorts = array_filter($this->sorts, fn ($sort): bool => ! empty($sort));
            if (empty($this->sorts)) {
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

            if (! empty($invalidSorts)) {
                $invalidSort = implode(', ', $invalidSorts);
                $validSorts = implode(', ', $allowedSorts);
                throw new InvalidQuery(
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
     * Set the filters for the query.
     *
     * @param  array<string, mixed>|null  $filters
     * @return self<TModel>
     */
    public function withFilters(?array $filters): self
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
    public function withIncludes(?array $includes): self
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
    public function withFields(?array $fields): self
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
    public function withSorts(?array $sorts): self
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
    public function withSearch(?string $query): self
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
    public function get(): Collection
    {
        return $this->apply()->get();
    }

    /**
     * Get the paginated results of the query.
     *
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage = 12): LengthAwarePaginator
    {
        return $this->apply()->paginate($perPage);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Model
    {
        return $this->apply()->findOrFail($id);
    }

    /**
     * Parse a boolean input value.
     */
    protected static function parseBooleanInput(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Parse a comma-separated input value.
     *
     * @return array<mixed>
     */
    protected static function parseCommaSeparatedInput(string $value, ?string $castReturn = null): array
    {
        $values = array_filter(explode(',', $value), fn ($value): bool => ! empty($value));

        if ($castReturn === null) {
            return $values;
        }

        return array_map(fn ($value): mixed => match ($castReturn) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => self::parseBooleanInput($value),
            default => $value,
        }, $values);
    }
}
