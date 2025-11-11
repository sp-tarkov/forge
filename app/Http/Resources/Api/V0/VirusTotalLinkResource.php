<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\VirusTotalLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin VirusTotalLink
 *
 * @property VirusTotalLink $resource
 */
class VirusTotalLinkResource extends JsonResource
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
            'url' => $this->resource->url,
            'label' => $this->resource->label,
        ];
    }
}
