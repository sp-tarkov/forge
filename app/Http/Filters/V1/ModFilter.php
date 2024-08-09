<?php

namespace App\Http\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ModFilter extends QueryFilter
{
    protected array $sortable = [
        'name',
        'slug',
        'teaser',
        'source_code_link',
        'featured',
        'contains_ads',
        'contains_ai_content',
        'created_at',
        'updated_at',
        'published_at',
    ];

    // TODO: Many of these are repeated across UserFilter and ModFilter. Consider refactoring into a shared trait.
    //       Also, consider using common filter types and making the field names dynamic.

    public function id(string $value): Builder
    {
        $ids = array_map('trim', explode(',', $value));

        return $this->builder->whereIn('id', $ids);
    }

    public function hub_id(string $value): Builder
    {
        $ids = array_map('trim', explode(',', $value));

        return $this->builder->whereIn('hub_id', $ids);
    }

    public function name(string $value): Builder
    {
        // The API handles the wildcard character as an asterisk (*), but the database uses the percentage sign (%).
        $like = Str::replace('*', '%', $value);

        return $this->builder->where('name', 'like', $like);
    }

    public function slug(string $value): Builder
    {
        // The API handles the wildcard character as an asterisk (*), but the database uses the percentage sign (%).
        $like = Str::replace('*', '%', $value);

        return $this->builder->where('slug', 'like', $like);
    }

    public function teaser(string $value): Builder
    {
        // The API handles the wildcard character as an asterisk (*), but the database uses the percentage sign (%).
        $like = Str::replace('*', '%', $value);

        return $this->builder->where('teaser', 'like', $like);
    }

    public function source_code_link(string $value): Builder
    {
        // The API handles the wildcard character as an asterisk (*), but the database uses the percentage sign (%).
        $like = Str::replace('*', '%', $value);

        return $this->builder->where('source_code_link', 'like', $like);
    }

    public function created_at(string $value): Builder
    {
        // The API allows for a range of dates to be passed as a comma-separated list.
        $dates = array_map('trim', explode(',', $value));
        if (count($dates) > 1) {
            return $this->builder->whereBetween('created_at', $dates);
        }

        return $this->builder->whereDate('created_at', $value);
    }

    public function updated_at(string $value): Builder
    {
        // The API allows for a range of dates to be passed as a comma-separated list.
        $dates = array_map('trim', explode(',', $value));
        if (count($dates) > 1) {
            return $this->builder->whereBetween('updated_at', $dates);
        }

        return $this->builder->whereDate('updated_at', $value);
    }

    public function published_at(string $value): Builder
    {
        // The API allows for a range of dates to be passed as a comma-separated list.
        $dates = array_map('trim', explode(',', $value));
        if (count($dates) > 1) {
            return $this->builder->whereBetween('published_at', $dates);
        }

        return $this->builder->whereDate('published_at', $value);
    }

    public function featured(string $value): Builder
    {
        // We need to convert the string user input to a boolean, or null if it's not a valid "truthy/falsy" value.
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // This column is not nullable.
        if ($value === null) {
            return $this->builder;
        }

        return $this->builder->where('featured', $value);
    }

    public function contains_ads(string $value): Builder
    {
        // We need to convert the string user input to a boolean, or null if it's not a valid "truthy/falsy" value.
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // This column is not nullable.
        if ($value === null) {
            return $this->builder;
        }

        return $this->builder->where('contains_ads', $value);
    }

    public function contains_ai_content(string $value): Builder
    {
        // We need to convert the string user input to a boolean, or null if it's not a valid "truthy/falsy" value.
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        // This column is not nullable.
        if ($value === null) {
            return $this->builder;
        }

        return $this->builder->where('contains_ai_content', $value);
    }
}
