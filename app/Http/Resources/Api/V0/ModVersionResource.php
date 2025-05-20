<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\ModVersion;
use App\Support\Api\V0\QueryBuilder\ModVersionQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin ModVersion
 *
 * @property ModVersion $resource
 */
class ModVersionResource extends JsonResource
{
    /**
     * The fields requested in the request.
     *
     * @var array<int, string>
     */
    protected array $requestedFields = [];

    /**
     * Whether to show all fields.
     */
    protected bool $showAllFields = true;

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
            ->map(fn (string $field): string => trim($field))
            ->filter()
            ->toArray();

        $this->showAllFields = empty($this->requestedFields);

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
            $data['description'] = $this->resource->description;
        }

        if ($this->shouldInclude('description')) {
            $data['description'] = $this->when(
                $request->routeIs('api.v0.mod-versions.index'),
                $this->resource->description_html
            );
        }

        if ($this->shouldInclude('link')) {
            $data['link'] = $this->resource->link;
        }

        if ($this->shouldInclude('spt_version_constraint')) {
            $data['spt_version_constraint'] = $this->resource->spt_version_constraint;
        }

        if ($this->shouldInclude('virus_total_link')) {
            $data['virus_total_link'] = $this->resource->virus_total_link;
        }

        if ($this->shouldInclude('downloads')) {
            $data['downloads'] = $this->resource->downloads;
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

        $data['dependencies'] = new ModResolvedDependencyCollection(
            $this->whenLoaded('resolvedDependencies')
        );

        return $data;
    }

    /**
     * Check if a field should be included in the response.
     *
     * @param  string  $field  The field name to check
     * @return bool Whether the field should be included
     */
    protected function shouldInclude(string $field): bool
    {
        $requiredFields = ModVersionQueryBuilder::getRequiredFields();

        return $this->showAllFields
            || in_array($field, $this->requestedFields, true)
            || in_array($field, $requiredFields, true);
    }
}
