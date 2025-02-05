<?php

declare(strict_types=1);

namespace App\Http\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModFilter extends QueryFilter
{
    /**
     * The sortable fields.
     *
     * @var array<int, string>
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
     *
     * @return Builder<Model>
     */
    public function id(string $value): Builder
    {
        return $this->filterWhereIn('id', $value);
    }

    /**
     * Filter by hub ID.
     *
     * @return Builder<Model>
     */
    public function hub_id(string $value): Builder
    {
        return $this->filterWhereIn('hub_id', $value);
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
     * Filter by slug.
     *
     * @return Builder<Model>
     */
    public function slug(string $value): Builder
    {
        return $this->filterByWildcardLike('slug', $value);
    }

    /**
     * Filter by teaser.
     *
     * @return Builder<Model>
     */
    public function teaser(string $value): Builder
    {
        return $this->filterByWildcardLike('teaser', $value);
    }

    /**
     * Filter by source code link.
     *
     * @return Builder<Model>
     */
    public function source_code_link(string $value): Builder
    {
        return $this->filterByWildcardLike('source_code_link', $value);
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

    /**
     * Filter by published at date.
     *
     * @return Builder<Model>
     */
    public function published_at(string $value): Builder
    {
        return $this->filterByDate('published_at', $value);
    }

    /**
     * Filter by featured.
     *
     * @return Builder<Model>
     */
    public function featured(string $value): Builder
    {
        return $this->filterByBoolean('featured', $value);
    }

    /**
     * Filter by contains ads.
     *
     * @return Builder<Model>
     */
    public function contains_ads(string $value): Builder
    {
        return $this->filterByBoolean('contains_ads', $value);
    }

    /**
     * Filter by contains AI content.
     *
     * @return Builder<Model>
     */
    public function contains_ai_content(string $value): Builder
    {
        return $this->filterByBoolean('contains_ai_content', $value);
    }
}
