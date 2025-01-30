<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SptVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin SptVersion */
class SptVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'id' => $this->id,
            'version' => $this->version,
            'color_class' => $this->color_class,
        ];
    }
}
