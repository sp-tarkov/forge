<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Mod */
class ModResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'source_code_link' => $this->source_code_link,

            'user_id' => $this->user_id,
            'license_id' => $this->license_id,

            'license' => new LicenseResource($this->whenLoaded('license')),
        ];
    }
}
