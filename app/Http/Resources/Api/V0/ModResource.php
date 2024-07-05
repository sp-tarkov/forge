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
                'teaser' => $this->teaser,
                'description' => $this->when(
                    $request->routeIs('api.v0.mods.show'),
                    $this->description
                ),
                'license_id' => $this->license_id,
                'source_code_link' => $this->source_code_link,
                'featured' => $this->featured,
                'contains_ai_content' => $this->contains_ai_content,
                'contains_ads' => $this->contains_ads,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
            'relationships' => [
                'users' => [
                    'data' => $this->users->map(fn ($user) => [
                        'type' => 'user',
                        'id' => $user->id,
                    ])->toArray(),

                    // TODO: Provide 'links.self' to user profile
                    //'links' => ['self' => '#'],
                ],
                'license' => [
                    'data' => [
                        'type' => 'license',
                        'id' => $this->license_id,
                    ],
                ],
            ],
            'included' => $this->users->map(fn ($user) => new UserResource($user)),

            // TODO: Provide 'included' data for attached 'license':
            //new LicenseResource($this->license)

            'links' => [
                'self' => route('mod.show', [
                    'mod' => $this->id,
                    'slug' => $this->slug,
                ]),
            ],
        ];
    }
}
