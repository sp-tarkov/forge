<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\ModCategory;
use App\Support\Api\V0\QueryBuilder\AbstractQueryBuilder;
use App\Support\Api\V0\QueryBuilder\ModCategoryQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin ModCategory
 */
final class ModCategoryResource extends JsonResource
{
    /**
     * The fields requested by the client.
     *
     * @var array<string>
     */
    private array $requestedFields = [];

    /**
     * Whether to show all fields (no specific fields requested).
     */
    private bool $showAllFields = true;

    /**
     * The query builder that hydrated the category, whose field contract bounds this response.
     *
     * @var class-string<AbstractQueryBuilder<ModCategory>>
     */
    private string $queryBuilder = ModCategoryQueryBuilder::class;

    /**
     * Set the query builder that hydrated the category. Fields outside that builder's contract are omitted from the
     * response.
     *
     * @param  class-string<AbstractQueryBuilder<ModCategory>>  $queryBuilder
     */
    public function hydratedBy(string $queryBuilder): self
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $this->requestedFields = $request->string('fields', '')
            ->explode(',')
            ->map(fn (string $field): string => mb_trim($field))
            ->filter()
            ->all();

        $this->showAllFields = $this->requestedFields === [];

        $data = [];

        if ($this->shouldInclude('id')) {
            $data['id'] = $this->id;
        }

        if ($this->shouldInclude('hub_id')) {
            $data['hub_id'] = $this->hub_id;
        }

        if ($this->shouldInclude('title')) {
            $data['title'] = $this->title;
        }

        if ($this->shouldInclude('slug')) {
            $data['slug'] = $this->slug;
        }

        if ($this->shouldInclude('description')) {
            $data['description'] = $this->description;
        }

        return $data;
    }

    /**
     * Check if a field should be included in the response. Required fields are always included; every other field must
     * be exposed by the hydrating query builder and either requested or covered by an unfiltered request.
     */
    private function shouldInclude(string $field): bool
    {
        $queryBuilder = $this->queryBuilder;

        if (in_array($field, $queryBuilder::getRequiredFields(), true)) {
            return true;
        }

        if (! in_array($field, $queryBuilder::getAllAllowedFields(), true)) {
            return false;
        }

        return $this->showAllFields || in_array($field, $this->requestedFields, true);
    }
}
