<?php

declare(strict_types=1);

namespace App\Http\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserFilter extends QueryFilter
{
    /**
     * The sortable fields.
     *
     * @var array<int, string>
     */
    protected array $sortable = [
        'name',
        'created_at',
        'updated_at',
    ];

    /**
     * Filter by ID.
     *
     * @return Builder<Model>
     */
    public function id(string $value): Builder
    {
        return $this->filterWhereIn('id', $value);
    }

    /**
     * Filter by name.
     *
     * @return Builder<Model>
     */
    public function name(string $value): Builder
    {
        return $this->filterByWildcardLike('name', $value);
    }

    /**
     * Filter by created at date.
     *
     * @return Builder<Model>
     */
    public function created_at(string $value): Builder
    {
        return $this->filterByDate('created_at', $value);
    }

    /**
     * Filter by updated at date.
     *
     * @return Builder<Model>
     */
    public function updated_at(string $value): Builder
    {
        return $this->filterByDate('updated_at', $value);
    }
}
