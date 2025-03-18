<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Http\Controllers\Api\V0\ApiController;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Mod */
class ModResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $this->load(['users', 'versions', 'license']);

        return [
            'type' => 'mod',
            'id' => $this->id,
            'attributes' => [
                'hub_id' => $this->hub_id,
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
                'published_at' => $this->published_at,
            ],
            'relationships' => [
                'users' => $this->users->map(fn (User $user): array => [
                    'data' => [
                        'type' => 'user',
                        'id' => $user->id,
                    ],
                    'links' => [
                        'self' => $user->profile_url,
                    ],
                ])->toArray(),
                'versions' => $this->versions->map(fn (ModVersion $version): array => [
                    'data' => [
                        'type' => 'version',
                        'id' => $version->id,
                    ],
                    'links' => [
                        'self' => $version->downloadUrl(absolute: true),
                    ],

                ])->toArray(),
                'license' => [
                    [
                        'data' => [
                            'type' => 'license',
                            'id' => $this->license_id,
                        ],
                    ],
                ],
            ],
            'includes' => $this->when(
                ApiController::shouldInclude(['users', 'license', 'versions']),
                fn () => collect([
                    'users' => $this->users->map(fn ($user): UserResource => new UserResource($user)),
                    'license' => new LicenseResource($this->license),
                    'versions' => $this->versions->map(fn ($version): ModVersionResource => new ModVersionResource($version)),
                ])
                    ->filter(fn ($value, $key): bool => ApiController::shouldInclude($key))
                    ->flatten(1)
                    ->values()
            ),
            'links' => [
                'self' => $this->detailUrl(),
            ],
        ];
    }
}
