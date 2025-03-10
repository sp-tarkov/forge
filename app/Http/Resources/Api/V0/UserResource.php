<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Http\Controllers\Api\V0\ApiController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $this->load('role');

        return [
            'type' => 'user',
            'id' => $this->id,
            'attributes' => [
                'name' => $this->name,
                'user_role_id' => $this->user_role_id,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
            'relationships' => [
                'user_role' => [
                    'data' => [
                        'type' => 'user_role',
                        'id' => $this->user_role_id,
                    ],
                ],
            ],
            'includes' => $this->when(
                ApiController::shouldInclude('user_role'),
                new UserRoleResource($this->role),
            ),
            'links' => [
                'self' => $this->profileUrl(),
            ],
        ];
    }
}
