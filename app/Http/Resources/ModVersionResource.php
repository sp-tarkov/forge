<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ModVersion */
class ModVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'id' => $this->id,
            'version' => $this->version,
            'description' => $this->description,
            'virus_total_link' => $this->virus_total_link,
            'downloads' => $this->downloads,

            'mod_id' => $this->mod_id,
            'spt_version_id' => $this->spt_version_id,

            'mod' => new ModResource($this->whenLoaded('mod')),
            'sptVersion' => new SptVersionResource($this->whenLoaded('sptVersion')),
        ];
    }
}
