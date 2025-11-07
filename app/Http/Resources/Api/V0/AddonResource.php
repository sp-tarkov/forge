<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\Addon;
use App\Support\Api\V0\QueryBuilder\AddonQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin Addon
 *
 * @property Addon $resource
 */
class AddonResource extends JsonResource
{
    /**
     * The fields requested by the client.
     *
     * @var array<string>
     */
    protected array $requestedFields = [];

    /**
     * Whether to show all fields (no specific fields requested).
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

        // Handle regular fields
        if ($this->shouldInclude('id')) {
            $data['id'] = $this->resource->id;
        }

        if ($this->shouldInclude('name')) {
            $data['name'] = $this->resource->name;
        }

        if ($this->shouldInclude('slug')) {
            $data['slug'] = $this->resource->slug;
        }

        if ($this->shouldInclude('teaser')) {
            $data['teaser'] = $this->resource->teaser;
        }

        if ($this->shouldInclude('thumbnail')) {
            $data['thumbnail'] = $this->resource->thumbnailUrl;
        }

        if ($this->shouldInclude('downloads')) {
            $data['downloads'] = $this->resource->downloads;
        }

        if ($this->shouldInclude('description')) {
            $data['description'] = $this->when(
                $request->routeIs('api.v0.addons.show'),
                $this->resource->description_html,
            );
        }

        if ($this->shouldInclude('detail_url')) {
            $data['detail_url'] = $this->resource->detail_url;
        }

        if ($this->shouldInclude('contains_ads')) {
            $data['contains_ads'] = (bool) $this->resource->contains_ads;
        }

        if ($this->shouldInclude('contains_ai_content')) {
            $data['contains_ai_content'] = (bool) $this->resource->contains_ai_content;
        }

        if ($this->shouldInclude('mod_id')) {
            $data['mod_id'] = $this->resource->mod_id;
        }

        if ($this->shouldInclude('is_detached')) {
            $data['is_detached'] = $this->resource->isDetached();
        }

        if ($this->shouldInclude('detached_at')) {
            $data['detached_at'] = $this->resource->detached_at?->toISOString();
        }

        if ($this->shouldInclude('published_at')) {
            $data['published_at'] = $this->resource->published_at?->toISOString();
        }

        if ($this->shouldInclude('created_at')) {
            $data['created_at'] = $this->resource->created_at->toISOString();
        }

        if ($this->shouldInclude('updated_at')) {
            $data['updated_at'] = $this->resource->updated_at->toISOString();
        }

        // Handle relationships - always include when loaded
        $data['owner'] = new UserResource($this->whenLoaded('owner'));
        $data['authors'] = UserResource::collection($this->whenLoaded('authors'));
        $data['mod'] = new ModResource($this->whenLoaded('mod'));
        $data['license'] = new LicenseResource($this->whenLoaded('license'));
        $data['latest_version'] = new AddonVersionResource($this->whenLoaded('latestVersion'));
        $data['source_code_links'] = SourceCodeLinkResource::collection($this->whenLoaded('sourceCodeLinks'));
        $data['versions'] = AddonVersionResource::collection($this->whenLoaded('versions', fn () =>
            // Limit versions in list view to the 6 most recent versions
            $this->resource->versions
                ->sortByDesc('version_major')
                ->sortByDesc('version_minor')
                ->sortByDesc('version_patch')
                ->sortBy('version_labels')
                ->take(6)));

        return $data;
    }

    /**
     * Determine if the given field should be included in the response.
     */
    protected function shouldInclude(string $field): bool
    {
        // ID is always included
        if ($field === 'id') {
            return true;
        }

        // If no fields requested, show all allowed fields
        if ($this->showAllFields) {
            return in_array($field, AddonQueryBuilder::getAllAllowedFields(), true);
        }

        // Otherwise, only show requested fields
        return in_array($field, $this->requestedFields, true);
    }
}
