<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
    public function toArray(Request $request): array
    {
        // Determine if the current request is for the authenticated user's own details
        $isCurrentUserRequest = $request->user()?->id === $this->id;

        return [
            // Public fields
            'id' => $this->id,
            'name' => $this->name,
            'profile_photo_url' => $this->profile_photo_url,
            'cover_photo_url' => $this->cover_photo_url,
            'created_at' => $this->created_at->toISOString(),

            // Conditionally include sensitive fields only for the user themselves
            $this->mergeWhen($isCurrentUserRequest, [
                'email' => $this->email,
                'email_verified_at' => $this->email_verified_at?->toISOString(),
            ]),

            // Conditionally include loaded relationships based on the 'include' parameter
            'role' => $this->whenLoaded('role', function () {
                return $this->role ? new RoleResource($this->role) : null;
            }),
        ];
    }
}
