<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\ModVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin ModVersion */
class ModVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $this->load(['resolvedDependencies', 'latestResolvedDependencies', 'sptVersions', 'latestSptVersion']);

        return [
            'type' => 'mod_version',
            'id' => $this->id,
            'attributes' => [
                'hub_id' => $this->hub_id,
                'mod_id' => $this->mod_id,
                'version' => $this->version,
                'version_major' => $this->version_major,
                'version_minor' => $this->version_minor,
                'version_patch' => $this->version_patch,
                'version_labels' => $this->version_labels,

                // TODO: This should only be visible on the mod version show route(?) which doesn't exist.
                // 'description' => $this->when(
                //    $request->routeIs('api.v0.modversion.show'),
                //    $this->description
                // ),

                'link' => $this->downloadUrl(absolute: true),
                'spt_version_constraint' => $this->spt_version_constraint,
                'virus_total_link' => $this->virus_total_link,
                'downloads' => $this->downloads,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'published_at' => $this->published_at,
            ],
            'relationships' => [
                'mod' => [
                    'data' => [
                        'type' => 'mod',
                        'id' => $this->mod_id,
                    ],
                    'links' => [
                        'self' => $this->mod->detailUrl(),
                    ],
                ],
                'dependencies' => $this->resolvedDependencies->map(fn (ModVersion $modVersion): array => [
                    'data' => [
                        'type' => 'dependency',
                        'id' => $modVersion->id,
                    ],
                ])->toArray(),
                'latest_dependencies' => $this->latestResolvedDependencies->map(fn (ModVersion $modVersion): array => [
                    'data' => [
                        'type' => 'dependency',
                        'id' => $modVersion->id,
                    ],
                ])->toArray(),
                'spt_versions' => $this->sptVersions->map(fn ($sptVersion): array => [
                    'data' => [
                        'type' => 'spt_version',
                        'id' => $sptVersion->id,
                    ],
                ])->toArray(),
                'latest_spt_version' => [
                    'data' => [
                        'type' => 'spt_version',
                        'id' => $this->latestSptVersion->id,
                    ],
                ],
            ],
            //            // TODO: give the options to include detailed relationship data.
            //            'includes' => $this->when(
            //                ApiController::shouldInclude(['authors', 'license', 'versions']), [
            //                    'authors' => $this->when(
            //                        ApiController::shouldInclude('authors'),
            //                        $this->authors->map(fn ($user): UserResource => new UserResource($user)),
            //                    ),
            //                    'license' => $this->when(
            //                        ApiController::shouldInclude('license'),
            //                        new LicenseResource($this->license),
            //                    ),
            //                ]
            //            ),
            'links' => [
                'self' => $this->mod->detailUrl(),
            ],
        ];
    }
}
