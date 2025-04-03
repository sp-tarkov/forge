<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\ModVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin ModVersion
 */
class ModVersionResource extends JsonResource
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
            'mod' => $this->when(
                $this->relationLoaded('mod') && ! $request->routeIs('api.v0.mods.index'),
                fn () => new ModResource($this->mod)
            ),
            'version' => $this->version,
            'description' => $this->description,
            'link' => $this->link,
            'spt_version_constraint' => $this->spt_version_constraint,
            'virus_total_link' => $this->virus_total_link,
            'downloads' => $this->downloads,
            'disabled' => (bool) $this->disabled,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
