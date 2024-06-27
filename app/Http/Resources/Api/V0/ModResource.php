<?php

namespace App\Http\Resources\Api\V0;

use App\Models\Mod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Mod */
class ModResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'mod',
            'id' => $this->id,
            'attributes' => [
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->when(
                    $request->routeIs('api.v0.mods.show'),
                    $this->description
                ),
                'source_code_link' => $this->source_code_link,
                'user_id' => $this->user_id,
                'license_id' => $this->license_id,
                'created_at' => $this->created_at,
            ],
            'relationships' => [
                'user' => [
                    'data' => [
                        'type' => 'user',
                        'id' => $this->user_id,
                    ],
                    // TODO: Provide 'links.self' to user profile:
                    //'links' => ['self' => '#'],
                ],
                'license' => [
                    'data' => [
                        'type' => 'license',
                        'id' => $this->license_id,
                    ],
                ],
            ],
            'included' => [
                new UserResource($this->user),
                // TODO: Provide 'included' data for attached 'license':
                //new LicenseResource($this->license),
            ],
            'links' => [
                'self' => route('mod.show', [
                    'mod' => $this->id,
                    'slug' => $this->slug,
                ]),
            ],
        ];
    }
}
