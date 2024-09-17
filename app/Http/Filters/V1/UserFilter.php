<?php

namespace App\Http\Filters\V1;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends QueryFilter<User>
 */
class UserFilter extends QueryFilter
{
    /**
     * The sortable fields.
     *
     * @var array<string>
     */
    protected array $sortable = [
        'name',
        'created_at',
        'updated_at',
    ];

    /**
     * Filter by ID.
     *
     * @return Builder<User>
     */
    public function id(string $value): Builder
    {
        return $this->filterWhereIn('id', $value);
    }

    /**
     * Filter by name.
     *
     * @return Builder<User>
     */
    public function name(string $value): Builder
    {
        return $this->filterByWildcardLike('name', $value);
    }

    /**
     * Filter by created at date.
     *
     * @return Builder<User>
     */
    public function created_at(string $value): Builder
    {
        return $this->filterByDate('created_at', $value);
    }

    /**
     * Filter by updated at date.
     *
     * @return Builder<User>
     */
    public function updated_at(string $value): Builder
    {
        return $this->filterByDate('updated_at', $value);
    }
}
