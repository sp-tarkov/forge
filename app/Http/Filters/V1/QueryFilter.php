<?php

namespace App\Http\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @template TModelClass of Model
 */
abstract class QueryFilter
{
    /**
     * The query builder instance.
     *
     * @var Builder<TModelClass>
     */
    protected Builder $builder;

    protected Request $request;

    /** @var array<string> */
    protected array $sortable = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply the filter to the query builder.
     *
     * @param  Builder<TModelClass>  $builder
     * @return Builder<TModelClass>
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->request->all() as $attribute => $value) {
            if (method_exists($this, $attribute)) {
                $this->$attribute($value);
            }
        }

        return $this->builder;
    }

    /**
     * Apply the sort type to the query.
     *
     * @return Builder<TModelClass>
     */
    protected function sort(string $values): Builder
    {
        $sortables = array_map('trim', explode(',', $values));

        foreach ($sortables as $sortable) {
            $direction = Str::startsWith($sortable, '-') ? 'desc' : 'asc';
            $column = Str::of($sortable)->remove('-')->value();

            if (in_array($column, $this->sortable)) {
                $this->builder->orderBy($column, $direction);
            }
        }

        return $this->builder;
    }
}
