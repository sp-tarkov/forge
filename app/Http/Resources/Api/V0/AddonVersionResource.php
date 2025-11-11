<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\AddonVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin AddonVersion
 *
 * @property AddonVersion $resource
 */
class AddonVersionResource extends JsonResource
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
            ->map(fn (string $field): string => mb_trim($field))
            ->filter()
            ->all();

        $this->showAllFields = empty($this->requestedFields);

        $data = [];

        if ($this->shouldInclude('id')) {
            $data['id'] = $this->resource->id;
        }

        if ($this->shouldInclude('version')) {
            $data['version'] = $this->resource->version;
        }

        if ($this->shouldInclude('description')) {
            $data['description'] = $this->when(
                $request->routeIs('api.v0.addons.versions'),
                $this->resource->description_html
            );
        }

        if ($this->shouldInclude('link')) {
            $data['link'] = $this->resource->link;
        }

        if ($this->shouldInclude('content_length')) {
            $data['content_length'] = $this->resource->content_length;
        }

        if ($this->shouldInclude('mod_version_constraint')) {
            $data['mod_version_constraint'] = $this->resource->mod_version_constraint;
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

        // Handle relationships separately - they're only included when loaded via the include parameter.
        $data['virus_total_links'] = VirusTotalLinkResource::collection(
            $this->whenLoaded('virusTotalLinks')
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
        return $this->showAllFields
            || in_array($field, $this->requestedFields, true)
            || $field === 'id'; // ID is always included
    }
}
