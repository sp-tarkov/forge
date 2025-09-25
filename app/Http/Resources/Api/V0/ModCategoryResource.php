<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\ModCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin ModCategory
 */
class ModCategoryResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
        ];
    }
}
