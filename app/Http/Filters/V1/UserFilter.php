<?php

namespace App\Http\Filters\V1;

use Illuminate\Database\Eloquent\Builder;

class UserFilter extends QueryFilter
{
    /**
     * The sortable fields.
     */
    protected array $sortable = [
        'name',
        'created_at',
        'updated_at',
    ];

    /**
     * Filter by ID.
     */
    public function id(string $value): Builder
    {
        return $this->filterWhereIn('id', $value);
    }

    /**
     * Filter by name.
     */
    public function name(string $value): Builder
    {
        return $this->filterByWildcardLike('name', $value);
    }

    /**
     * Filter by created at date.
     */
    public function created_at(string $value): Builder
    {
        return $this->filterByDate('created_at', $value);
    }

    /**
     * Filter by updated at date.
     */
    public function updated_at(string $value): Builder
    {
        return $this->filterByDate('updated_at', $value);
    }
}
