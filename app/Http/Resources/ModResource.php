<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Mod;
use App\Models\SourceCodeLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Mod */
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'source_code_links' => $this->sourceCodeLinks->map(fn (SourceCodeLink $link): array => [
                'url' => $link->url,
                'label' => $link->label,
            ]),
            'license_id' => $this->license_id,
            'license' => new LicenseResource($this->whenLoaded('license')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'published_at' => $this->published_at,
        ];
    }
}
