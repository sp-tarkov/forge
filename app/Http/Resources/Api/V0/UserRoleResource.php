<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin UserRole */
class UserRoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'type' => 'user_role',
            'id' => $this->id,
            'attributes' => [
                'name' => $this->name,
                'short_name' => $this->short_name,
                'description' => $this->description,
                'color_class' => $this->color_class,
            ],
        ];
    }
}
