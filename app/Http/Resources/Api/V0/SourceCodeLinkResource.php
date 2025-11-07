<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\SourceCodeLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin SourceCodeLink
 *
 * @property SourceCodeLink $resource
 */
class SourceCodeLinkResource extends JsonResource
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
            'id' => $this->resource->id,
            'url' => $this->resource->url,
            'label' => $this->resource->label,
        ];
    }
}
