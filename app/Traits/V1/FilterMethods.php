<?php

namespace App\Traits\V1;

use App\Http\Filters\V1\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @template TModelClass of Model
 *
 * @mixin QueryFilter<TModelClass>
 */
trait FilterMethods
{
    /**
     * Filter using a whereIn clause.
     *
     * @return Builder<TModelClass>
     */
    public function filterWhereIn(string $column, string $value): Builder
    {
        $ids = array_map('trim', explode(',', $value));

        $result = $this->builder->whereIn($column, $ids);

        /** @var Builder<TModelClass> $result */
        return $result;
    }

    /**
     * Filter using a LIKE clause with a wildcard characters.
     *
     * @return Builder<TModelClass>
     */
    public function filterByWildcardLike(string $column, string $value): Builder
    {
        $like = Str::replace('*', '%', $value);

        $result = $this->builder->where($column, 'like', $like);

        /** @var Builder<TModelClass> $result */
        return $result;
    }

    /**
     * Filter by date range or specific date.
     *
     * @return Builder<TModelClass>
     */
    public function filterByDate(string $column, string $value): Builder
    {
        $dates = array_map('trim', explode(',', $value));

        if (count($dates) > 1) {
            $result = $this->builder->whereBetween($column, $dates);
        } else {
            $result = $this->builder->whereDate($column, $dates[0]);
        }

        /** @var Builder<TModelClass> $result */
        return $result;
    }

    /**
     * Filter by boolean value.
     *
     * @return Builder<TModelClass>
     */
    public function filterByBoolean(string $column, string $value): Builder
    {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) {
            $result = $this->builder; // The unmodified builder
        } else {
            $result = $this->builder->where($column, $value);
        }

        /** @var Builder<TModelClass> $result */
        return $result;
    }

    /**
     * Apply the sort type to the query.
     *
     * @return Builder<TModelClass>
     */
    protected function sort(string $values): Builder
    {
        $result = $this->builder;
        $sortables = array_map('trim', explode(',', $values));

        foreach ($sortables as $sortable) {
            $direction = Str::startsWith($sortable, '-') ? 'desc' : 'asc';
            $column = Str::of($sortable)->remove('-')->value();

            if (in_array($column, $this->sortable)) {
                $result = $this->builder->orderBy($column, $direction);
            }
        }

        /** @var Builder<TModelClass> $result */
        return $result;
    }
}
