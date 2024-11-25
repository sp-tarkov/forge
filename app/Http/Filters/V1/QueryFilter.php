<?php

namespace App\Http\Filters\V1;

use App\Traits\V1\FilterMethods;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class QueryFilter
{
    /**
     * Include general filter methods.
     */
    use FilterMethods;

    /**
     * The query builder instance.
     */
    protected Builder $builder;

    /**
     * The request instance.
     */
    protected Request $request;

    /**
     * The sortable fields.
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
