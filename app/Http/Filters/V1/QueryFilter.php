<?php

declare(strict_types=1);

namespace App\Http\Filters\V1;

use App\Models\Mod;
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
        $this->builder = $this->applyBaseFilters($builder);

        foreach ($this->request->all() as $attribute => $value) {
            if (method_exists($this, $attribute)) {
                $this->$attribute($value);
            }
        }

        return $this->builder;
    }

    /**
     * Apply the base filters to the query builder.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    private function applyBaseFilters(Builder $builder): Builder
    {
        $builder->where('disabled', false);

        // If this builder is for a mod, ensure it has a latest version.
        if ($builder->getModel() instanceof Mod) {
            $builder->whereHas('latestVersion', function (Builder $query): void {
                // Ensure the latest version has a resolved SPT version.
                $query->whereHas('latestSptVersion');
            });
        }

        return $builder;
    }
}
