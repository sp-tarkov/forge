<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VirusTotalLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin VirusTotalLink */
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
            'id' => $this->id,
            'url' => $this->url,
            'label' => $this->label,
        ];
    }
}
