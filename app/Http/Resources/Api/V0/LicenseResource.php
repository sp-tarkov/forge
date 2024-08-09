<?php

namespace App\Http\Resources\Api\V0;

use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin License */
class LicenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'license',
            'id' => $this->id,
            'attributes' => [
                'name' => $this->name,
                'link' => $this->link,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
        ];
    }
}
