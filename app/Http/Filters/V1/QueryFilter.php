<?php

namespace App\Http\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class QueryFilter
{
    protected Builder $builder;

    protected Request $request;

    protected array $sortable = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

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

    protected function filter(array $filters): Builder
    {
        foreach ($filters as $attribute => $value) {
            if (method_exists($this, $attribute)) {
                $this->$attribute($value);
            }
        }

        return $this->builder;
    }

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
