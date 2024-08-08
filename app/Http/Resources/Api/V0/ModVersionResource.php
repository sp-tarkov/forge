<?php

namespace App\Http\Resources\Api\V0;

use App\Models\ModVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ModVersion */
class ModVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'mod_version',
            'id' => $this->id,
            'attributes' => [
                'hub_id' => $this->hub_id,
                'mod_id' => $this->mod_id,
                'version' => $this->version,

                // TODO: This should only be visible on the mod version show route(?) which doesn't exist.
                //'description' => $this->when(
                //    $request->routeIs('api.v0.modversion.show'),
                //    $this->description
                //),

                // TODO: The download link to the version can be placed here, but I'd like to track the number of
                //       downloads that are made, so we'll need a new route/feature for that. #35
                'link' => $this->link,

                'spt_version_id' => $this->spt_version_id,
                'virus_total_link' => $this->virus_total_link,
                'downloads' => $this->downloads,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'published_at' => $this->published_at,
            ],
            'relationships' => [
                'spt_version' => [
                    [
                        'data' => [
                            'type' => 'spt_version',
                            'id' => $this->spt_version_id,
                        ],
                    ],
                ],
            ],
        ];
    }
}
