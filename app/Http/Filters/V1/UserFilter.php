<?php

namespace App\Http\Filters\V1;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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

    // TODO: Many of these are repeated across UserFilter and ModFilter. Consider refactoring into a shared trait.
    //       Also, consider using common filter types and making the field names dynamic.

    /**
     * Filter by ID.
     *
     * @return Builder<User>
     */
    public function id(string $value): Builder
    {
        $ids = array_map('trim', explode(',', $value));

        return $this->builder->whereIn('id', $ids);
    }

    /**
     * Filter by name.
     *
     * @return Builder<User>
     */
    public function name(string $value): Builder
    {
        // The API handles the wildcard character as an asterisk (*), but the database uses the percentage sign (%).
        $like = Str::replace('*', '%', $value);

        return $this->builder->where('name', 'like', $like);
    }

    /**
     * Filter by created at date.
     *
     * @return Builder<User>
     */
    public function created_at(string $value): Builder
    {
        // The API allows for a range of dates to be passed as a comma-separated list.
        $dates = array_map('trim', explode(',', $value));
        if (count($dates) > 1) {
            return $this->builder->whereBetween('created_at', $dates);
        }

        return $this->builder->whereDate('created_at', $value);
    }

    /**
     * Filter by updated at date.
     *
     * @return Builder<User>
     */
    public function updated_at(string $value): Builder
    {
        // The API allows for a range of dates to be passed as a comma-separated list.
        $dates = array_map('trim', explode(',', $value));
        if (count($dates) > 1) {
            return $this->builder->whereBetween('updated_at', $dates);
        }

        return $this->builder->whereDate('updated_at', $value);
    }
}
