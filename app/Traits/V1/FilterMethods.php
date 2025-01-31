<?php

declare(strict_types=1);

namespace App\Traits\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait FilterMethods
{
    /**
     * Filter using a whereIn clause.
     *
     * @return Builder<Model>
     */
    public function filterWhereIn(string $column, string $value): Builder
    {
        $ids = array_map('trim', explode(',', $value));

        return $this->builder->whereIn($column, $ids);
    }

    /**
     * Filter using a LIKE clause with a wildcard characters.
     *
     * @return Builder<Model>
     */
    public function filterByWildcardLike(string $column, string $value): Builder
    {
        $like = Str::replace('*', '%', $value);

        return $this->builder->where($column, 'like', $like);
    }

    /**
     * Filter by date range or specific date.
     *
     * @return Builder<Model>
     */
    public function filterByDate(string $column, string $value): Builder
    {
        $dates = array_map('trim', explode(',', $value));

        if (count($dates) > 1) {
            return $this->builder->whereBetween($column, $dates);
        }

        return $this->builder->whereDate($column, $dates[0]);
    }

    /**
     * Filter by boolean value.
     *
     * @return Builder<Model>
     */
    public function filterByBoolean(string $column, string $value): Builder
    {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) {
            return $this->builder; // The unmodified builder
        }

        return $this->builder->where($column, $value);
    }

    /**
     * Apply the sort type to the query.
     *
     * @return Builder<Model>
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
