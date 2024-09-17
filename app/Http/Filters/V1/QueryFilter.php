<?php

namespace App\Http\Filters\V1;

use App\Traits\V1\FilterMethods;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * @template TModelClass of Model
 */
abstract class QueryFilter
{
    /**
     * Include general filter methods.
     *
     * @use FilterMethods<TModelClass>
     */
    use FilterMethods;

    /**
     * The query builder instance.
     *
     * @var Builder<TModelClass>
     */
    protected Builder $builder;

    /**
     * The request instance.
     */
    protected Request $request;

    /**
     * The sortable fields.
     *
     * @var array<string>
     */
    protected array $sortable = [];

    /**
     * Create a new QueryFilter instance.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Iterate over each of the filter options and call the appropriate method if it exists.
     *
     * @param  array<string, string>  $filters
     * @return Builder<TModelClass>
     */
    public function filter(array $filters): Builder
    {
        foreach ($filters as $attribute => $value) {
            if (method_exists($this, $attribute)) {
                $this->$attribute($value);
            }
        }

        return $this->builder;
    }

    /**
     * Iterate over all request data and call the appropriate method if it exists.
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
}
