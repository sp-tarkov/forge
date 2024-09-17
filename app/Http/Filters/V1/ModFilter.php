<?php

namespace App\Http\Filters\V1;

use App\Models\Mod;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends QueryFilter<Mod>
 */
class ModFilter extends QueryFilter
{
    /**
     * The sortable fields.
     */
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

    /**
     * Filter by ID.
     */
    public function id(string $value): Builder
    {
        return $this->filterWhereIn('id', $value);
    }

    /**
     * Filter by hub ID.
     */
    public function hub_id(string $value): Builder
    {
        return $this->filterWhereIn('hub_id', $value);
    }

    /**
     * Filter by name.
     */
    public function name(string $value): Builder
    {
        return $this->filterByWildcardLike('name', $value);
    }

    /**
     * Filter by slug.
     */
    public function slug(string $value): Builder
    {
        return $this->filterByWildcardLike('slug', $value);
    }

    /**
     * Filter by teaser.
     */
    public function teaser(string $value): Builder
    {
        return $this->filterByWildcardLike('teaser', $value);
    }

    /**
     * Filter by source code link.
     */
    public function source_code_link(string $value): Builder
    {
        return $this->filterByWildcardLike('source_code_link', $value);
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

    /**
     * Filter by published at date.
     */
    public function published_at(string $value): Builder
    {
        return $this->filterByDate('published_at', $value);
    }

    /**
     * Filter by featured.
     */
    public function featured(string $value): Builder
    {
        return $this->filterByBoolean('featured', $value);
    }

    /**
     * Filter by contains ads.
     */
    public function contains_ads(string $value): Builder
    {
        return $this->filterByBoolean('contains_ads', $value);
    }

    /**
     * Filter by contains AI content.
     */
    public function contains_ai_content(string $value): Builder
    {
        return $this->filterByBoolean('contains_ai_content', $value);
    }
}
