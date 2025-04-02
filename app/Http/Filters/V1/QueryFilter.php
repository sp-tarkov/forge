<?php

declare(strict_types=1);

namespace App\Http\Filters\V1;

use App\Traits\V1\FilterMethods;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class QueryFilter
{
    /**
     * Include general filter methods.
     */
    use FilterMethods;

    /**
     * The query builder instance.
     *
     * @var Builder<Model>
     */
    protected Builder $builder;

    /**
     * The sortable fields.
     *
     * @var array<int, string>
     */
    protected array $sortable = [];

    /**
     * Create a new QueryFilter instance.
     */
    public function __construct(
        /**
         * The request instance.
         */
        protected Request $request
    ) {}

    /**
     * Iterate over each of the filter options and call the appropriate method if it exists.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Model>
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
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        // Iterate through request params and apply specific filter methods.
        foreach ($this->request->all() as $attribute => $value) {
            if (method_exists($this, $attribute)) {
                $this->$attribute($value);
            }
        }

        // Apply sorting if present (from FilterMethods trait).
        if ($this->request->has('sort')) {
            $this->sort($this->request->input('sort'));
        }

        return $this->builder;
    }
}
