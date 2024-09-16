<?php

namespace App\Http\Resources;

use App\Models\ModVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ModVersion */
class ModVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'published_at' => $this->published_at,
            'id' => $this->id,
            'version' => $this->version,
            'description' => $this->description,
            'virus_total_link' => $this->virus_total_link,
            'downloads' => $this->downloads,
            'mod_id' => $this->mod_id,
            'mod' => new ModResource($this->whenLoaded('mod')),
            'sptVersion' => new SptVersionResource($this->whenLoaded('sptVersion')),
        ];
    }
}
