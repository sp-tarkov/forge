<?php

namespace App\Http\Resources\Api\V0;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'user',
            'id' => $this->id,
            'attributes' => [
                'name' => $this->name,
                'user_role_id' => $this->user_role_id,
                'created_at' => $this->created_at,
            ],
            'relationships' => [
                'user_role' => [
                    'data' => [
                        'type' => 'user_role',
                        'id' => $this->user_role_id,
                    ],
                ],
            ],

            // TODO: Provide 'included' data for attached 'user_role'
            //'included' => [new UserRoleResource($this->role)],

            'links' => [
                'self' => $this->profileUrl(),
            ],
        ];
    }
}
