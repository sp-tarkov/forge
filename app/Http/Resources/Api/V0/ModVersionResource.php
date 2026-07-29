<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Http\Resources\Api\V0\Collections\ModDependencyResolvedCollection;
use App\Models\ModVersion;
use App\Support\Api\V0\QueryBuilder\AbstractQueryBuilder;
use App\Support\Api\V0\QueryBuilder\ModVersionQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin ModVersion
 *
 * @property ModVersion $resource
 */
final class ModVersionResource extends JsonResource
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
     * @var class-string<AbstractQueryBuilder<ModVersion>>
     */
    private string $queryBuilder = ModVersionQueryBuilder::class;

    /**
     * Set the query builder that hydrated the version. Fields outside that builder's contract are omitted from the
     * response.
     *
     * @param  class-string<AbstractQueryBuilder<ModVersion>>  $queryBuilder
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
        // Use field filtering
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

        if ($this->shouldInclude('hub_id')) {
            $data['hub_id'] = $this->resource->hub_id;
        }

        if ($this->shouldInclude('version')) {
            $data['version'] = $this->resource->version;
        }

        if ($this->shouldInclude('description')) {
            $data['description'] = $request->routeIs('api.v0.mod.versions')
                ? $this->resource->description_html
                : $this->resource->description;
        }

        if ($this->shouldInclude('link')) {
            $data['link'] = $this->resource->downloadUrl(absolute: true);
        }

        if ($this->shouldInclude('content_length')) {
            $data['content_length'] = $this->resource->content_length;
        }

        if ($this->shouldInclude('spt_version_constraint')) {
            $data['spt_version_constraint'] = $this->resource->spt_version_constraint;
        }

        if ($this->shouldInclude('downloads')) {
            $data['downloads'] = $this->resource->downloads;
        }

        if ($this->shouldInclude('fika_compatibility')) {
            $data['fika_compatibility'] = $this->resource->fika_compatibility->value;
        }

        if ($this->shouldInclude('published_at')) {
            $data['published_at'] = $this->resource->published_at?->toISOString();
        }

        if ($this->shouldInclude('created_at')) {
            $data['created_at'] = $this->resource->created_at?->toISOString();
        }

        if ($this->shouldInclude('updated_at')) {
            $data['updated_at'] = $this->resource->updated_at?->toISOString();
        }

        $data['dependencies'] = new ModDependencyResolvedCollection(
            $this->whenLoaded('dependenciesResolved')
        );

        $data['virus_total_links'] = VirusTotalLinkResource::collection(
            $this->whenLoaded('virusTotalLinks')
        );

        return $data;
    }

    /**
     * Check if a field should be included in the response. Required fields are always included; every other field must
     * be exposed by the hydrating query builder and either requested or covered by an unfiltered request.
     *
     * @param  string  $field  The field name to check
     * @return bool Whether the field should be included
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
