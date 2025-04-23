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
            $data['id'] = $this->id;
        }

        if ($this->shouldInclude('hub_id')) {
            $data['hub_id'] = $this->hub_id;
        }

        if ($this->shouldInclude('name')) {
            $data['name'] = $this->name;
        }

        if ($this->shouldInclude('slug')) {
            $data['slug'] = $this->slug;
        }

        if ($this->shouldInclude('teaser')) {
            $data['teaser'] = $this->teaser;
        }

        if ($this->shouldInclude('thumbnail')) {
            $data['thumbnail'] = $this->thumbnail;
        }

        if ($this->shouldInclude('downloads')) {
            $data['downloads'] = $this->downloads;
        }

        if ($this->shouldInclude('description')) {
            $data['description'] = $this->when($request->routeIs('api.v0.mods.show'), $this->description);
        }

        if ($this->shouldInclude('source_code_link')) {
            $data['source_code_link'] = $this->source_code_link;
        }

        if ($this->shouldInclude('featured')) {
            $data['featured'] = (bool) $this->featured;
        }

        if ($this->shouldInclude('contains_ads')) {
            $data['contains_ads'] = (bool) $this->contains_ads;
        }

        if ($this->shouldInclude('contains_ai_content')) {
            $data['contains_ai_content'] = (bool) $this->contains_ai_content;
        }

        if ($this->shouldInclude('published_at')) {
            $data['published_at'] = $this->published_at?->toISOString();
        }

        if ($this->shouldInclude('created_at')) {
            $data['created_at'] = $this->created_at?->toISOString();
        }

        if ($this->shouldInclude('updated_at')) {
            $data['updated_at'] = $this->updated_at?->toISOString();
        }

        // Handle relationships separately... they're only included when loaded.
        $data['owner'] = $this->whenLoaded('owner', fn (): ?UserResource => $this->owner ? new UserResource($this->owner) : null);
        $data['authors'] = UserResource::collection($this->whenLoaded('authors'));
        $data['versions'] = ModVersionResource::collection($this->whenLoaded('versions', fn (): Collection => $this->versions->take(10)));
        $data['license'] = $this->whenLoaded('license', fn (): ?LicenseResource => $this->license ? new LicenseResource($this->license) : null);

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
