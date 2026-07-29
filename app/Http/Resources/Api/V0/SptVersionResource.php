<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\SptVersion;
use App\Support\Api\V0\QueryBuilder\AbstractQueryBuilder;
use App\Support\Api\V0\QueryBuilder\SptVersionQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin SptVersion
 *
 * @property SptVersion $resource
 */
final class SptVersionResource extends JsonResource
{
    /**
     * The fields requested in the request.
     *
     * @var array<int, string>
     */
    private array $requestedFields = [];

    /**
     * Whether to show all fields.
     */
    private bool $showAllFields = true;

    /**
     * The query builder that hydrated the version, whose field contract bounds this response.
     *
     * @var class-string<AbstractQueryBuilder<SptVersion>>
     */
    private string $queryBuilder = SptVersionQueryBuilder::class;

    /**
     * Set the query builder that hydrated the version. Fields outside that builder's contract are omitted from the
     * response.
     *
     * @param  class-string<AbstractQueryBuilder<SptVersion>>  $queryBuilder
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
            $data['id'] = $this->resource->id;
        }

        if ($this->shouldInclude('version')) {
            $data['version'] = $this->resource->version;
        }

        if ($this->shouldInclude('version_major')) {
            $data['version_major'] = $this->resource->version_major;
        }

        if ($this->shouldInclude('version_minor')) {
            $data['version_minor'] = $this->resource->version_minor;
        }

        if ($this->shouldInclude('version_patch')) {
            $data['version_patch'] = $this->resource->version_patch;
        }

        if ($this->shouldInclude('version_labels')) {
            $data['version_labels'] = $this->resource->version_labels;
        }

        if ($this->shouldInclude('mod_count')) {
            $data['mod_count'] = $this->resource->mod_count;
        }

        if ($this->shouldInclude('link')) {
            $data['link'] = $this->resource->link;
        }

        if ($this->shouldInclude('color_class')) {
            $data['color_class'] = $this->resource->color_class;
        }

        if ($this->shouldInclude('created_at')) {
            $data['created_at'] = $this->resource->created_at->toISOString();
        }

        if ($this->shouldInclude('updated_at')) {
            $data['updated_at'] = $this->resource->updated_at->toISOString();
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
