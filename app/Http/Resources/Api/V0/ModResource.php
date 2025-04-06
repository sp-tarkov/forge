<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Composer\Semver\Semver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Override;
use UnexpectedValueException;

/**
 * @mixin Mod
 */
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
        $processedVersions = $this->processVersions($request);

        return [
            'id' => $this->id,
            'hub_id' => $this->hub_id,
            'owner' => $this->whenLoaded('owner', fn (): ?UserResource => $this->owner ? new UserResource($this->owner) : null),
            'name' => $this->name,
            'slug' => $this->slug,
            'teaser' => $this->teaser,
            'description' => $this->when($request->routeIs('api.v0.mods.show'), $this->description),
            'source_code_link' => $this->source_code_link,
            'featured' => (bool) $this->featured,
            'contains_ads' => (bool) $this->contains_ads,
            'contains_ai_content' => (bool) $this->contains_ai_content,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'authors' => UserResource::collection($this->whenLoaded('authors')),
            'versions' => ModVersionResource::collection($processedVersions),
            'license' => $this->whenLoaded('license', fn (): ?LicenseResource => $this->license ? new LicenseResource($this->license) : null),
        ];
    }

    /**
     * Filter and limit the loaded versions based on request parameters.
     *
     * TODO: If you can figure out a way to do this within the query builder, do it. Every attempt that I made seamed to
     *       be overwritten by the Spatie\QueryBuilder class or something... Major pain in the ass.
     *
     * @return Collection<int, ModVersion>
     */
    private function processVersions(Request $request): Collection
    {
        // Get the eager-loaded versions.
        $versions = $this->whenLoaded('versions');

        // If versions were not loaded or the relationship is empty, return an empty collection.
        if (! $versions instanceof Collection || $versions->isEmpty()) {
            return collect();
        }

        $sptConstraintFilter = $request->string('filter.spt_version', '')->toString();
        $versionsLimit = min(max(1, $request->integer('versions_limit', 1)), 10); // Between 1 and 10

        $filteredVersions = $versions;

        // Apply SPT version filtering if the parameter is present.
        if (! empty($sptConstraintFilter)) {

            $satisfyingSptVersions = $this->getSatisfyingSptVersions($sptConstraintFilter);

            if (! empty($satisfyingSptVersions)) {
                $filteredVersions = $versions->filter(function (ModVersion $version) use ($satisfyingSptVersions) {
                    return $version->sptVersions->pluck('version')->intersect($satisfyingSptVersions)->isNotEmpty();
                });
            } else {
                $filteredVersions = collect(); // No satisfying versions found, return empty collection.
            }
        }

        // Apply the limit to the filtered collection.
        return $filteredVersions->take($versionsLimit);
    }

    /**
     * Get satisfying SPT version strings based on a constraint.
     *
     * @return array<int, string>
     */
    private function getSatisfyingSptVersions(string $constraint): array
    {
        $availableSptVersions = SptVersion::allValidVersions();
        $satisfyingVersionStrings = [];

        try {
            $satisfyingVersionStrings = Semver::satisfiedBy($availableSptVersions, $constraint);
        } catch (UnexpectedValueException $unexpectedValueException) {
            Log::error('ModResource: Invalid SemVer constraint processing.', ['constraint' => $constraint, 'error' => $unexpectedValueException->getMessage()]);
        }

        return $satisfyingVersionStrings;
    }
}
