<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<int|string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        // Determine if the current request is for the authenticated user's own details
        $isCurrentUserRequest = $request->user()?->id === $this->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'profile_photo_url' => $this->profile_photo_url,
            'cover_photo_url' => $this->cover_photo_url,
            $this->mergeWhen($isCurrentUserRequest, [ // Include these fields if the request is for the current user
                'email' => $this->email,
                'email_verified_at' => $this->email_verified_at?->toISOString(),
            ]),
            'role' => $this->whenLoaded('role', fn (): ?RoleResource => $this->role ? new RoleResource($this->role) : null),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
