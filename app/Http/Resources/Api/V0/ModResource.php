<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\Mod;
use App\Support\Api\V0\QueryBuilder\ModQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Override;

/**
 * @mixin Mod
 *
 * @property Mod $resource
 */
class ModResource extends JsonResource
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
            ->map(fn (string $field): string => trim($field))
            ->filter()
            ->toArray();

        $this->showAllFields = empty($this->requestedFields);

        $data = [];

        // Handle regular fields
        if ($this->shouldInclude('id')) {
            $data['id'] = $this->resource->id;
        }

        if ($this->shouldInclude('hub_id')) {
            $data['hub_id'] = $this->resource->hub_id;
        }

        if ($this->shouldInclude('guid')) {
            $data['guid'] = $this->resource->guid;
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
            $data['thumbnail'] = $this->resource->thumbnail;
        }

        if ($this->shouldInclude('downloads')) {
            $data['downloads'] = $this->resource->downloads;
        }

        if ($this->shouldInclude('description')) {
            $data['description'] = $this->when(
                $request->routeIs('api.v0.mods.show'),
                $this->resource->description_html,
            );
        }

        if ($this->shouldInclude('source_code_url')) {
            $data['source_code_url'] = $this->resource->source_code_url;
        }

        if ($this->shouldInclude('detail_url')) {
            $data['detail_url'] = $this->resource->detail_url;
        }

        if ($this->shouldInclude('featured')) {
            $data['featured'] = (bool) $this->resource->featured;
        }

        if ($this->shouldInclude('contains_ads')) {
            $data['contains_ads'] = (bool) $this->resource->contains_ads;
        }

        if ($this->shouldInclude('contains_ai_content')) {
            $data['contains_ai_content'] = (bool) $this->resource->contains_ai_content;
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

        // Handle relationships separately... they're only included when loaded.
        $data['owner'] = $this->whenLoaded('owner', fn (): ?UserResource => $this->resource->owner ? new UserResource($this->resource->owner) : null);
        $data['authors'] = UserResource::collection($this->whenLoaded('authors'));
        $data['versions'] = ModVersionResource::collection($this->whenLoaded('versions', fn (): Collection => $this->resource->versions->take(10)));
        $data['license'] = $this->whenLoaded('license', fn (): ?LicenseResource => $this->resource->license ? new LicenseResource($this->resource->license) : null);

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
        $requiredFields = ModQueryBuilder::getRequiredFields();

        return $this->showAllFields
            || in_array($field, $this->requestedFields, true)
            || in_array($field, $requiredFields, true);
    }
}
