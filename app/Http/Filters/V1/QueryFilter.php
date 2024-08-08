<?php

namespace App\Http\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class QueryFilter
{
    protected Builder $builder;

    public function __construct(protected Request $request) {}

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
}
