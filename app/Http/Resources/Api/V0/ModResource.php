<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\Mod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin Mod
 */
class ModResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hub_id' => $this->hub_id,
            'owner' => $this->whenLoaded('owner', fn (): ?UserResource => $this->owner ? new UserResource($this->owner) : null),
            'name' => $this->name,
            'slug' => $this->slug,
            'teaser' => $this->teaser,
            'description' => $this->when($request->routeIs('api.v0.mods.show'), $this->description),
            'source_code_link' => $this->source_code_link,
            'featured' => (bool) $this->featured,
            'contains_ads' => (bool) $this->contains_ads,
            'contains_ai_content' => (bool) $this->contains_ai_content,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'authors' => UserResource::collection($this->whenLoaded('authors')),
            'versions' => ModVersionResource::collection($this->whenLoaded('versions')),
            'latest_version' => $this->whenLoaded('latestVersion', fn (): ModVersionResource => new ModVersionResource($this->latestVersion)),
            'license' => $this->whenLoaded('license', fn (): ?LicenseResource => $this->license ? new LicenseResource($this->license) : null),
        ];
    }
}
