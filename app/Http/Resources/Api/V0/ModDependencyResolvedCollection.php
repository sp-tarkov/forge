<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection as SupportCollection;
use Override;

/**
 * Represents a collection of resolved dependencies, grouped by Mod.
 * The underlying collection contains ModVersion models (the dependent versions).
 *
 * @property EloquentCollection<int, ModVersion>|MissingValue $collection
 */
class ModDependencyResolvedCollection extends ResourceCollection
{
    /**
     * Indicates if the resource's collection keys should be preserved. The output is a plain array.
     */
    public bool $preserveKeys = false;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int, array<string, mixed>>|MissingValue
     */
    #[Override]
    public function toArray(Request $request): array|MissingValue
    {
        if ($this->collection instanceof MissingValue) {
            return [];
        }

        /** @var EloquentCollection<int, ModVersion> $modVersionsCollection */
        $modVersionsCollection = $this->collection;

        if ($modVersionsCollection->isEmpty()) {
            return [];
        }

        // Group dependencies by the dependent mod's ID
        /** @var SupportCollection<int, SupportCollection<int, ModVersion>> $grouped */
        $grouped = $modVersionsCollection->groupBy(
            // The item here is a dependent ModVersion model
            fn (ModVersion $dependencyVersion): int => $dependencyVersion->mod_id,
            preserveKeys: true
        );

        // Transform each group into a single mod resource with all its versions
        $result = $grouped->map(
            /**
             * @param  SupportCollection<int, ModVersion>  $dependencyVersions
             * @param  int  $modId  // Key from groupBy
             */
            function (SupportCollection $dependencyVersions, int $modId) use ($request): array {

                /** @var ModVersion|null $firstDependencyVersion */
                $firstDependencyVersion = $dependencyVersions->first();

                // Should not happen if groupBy worked correctly and collection wasn't empty
                if (! $firstDependencyVersion) {
                    return [];
                }

                $mod = $firstDependencyVersion->mod;
                $modData = new ModResource($mod)->toArray($request);

                // Collect all versions from the dependencies for this specific mod.
                $modData['versions'] = $dependencyVersions->map(
                    fn (ModVersion $depVersion): ModVersionResource => new ModVersionResource($depVersion)
                )->values()->all();

                return $modData;
            }
        );

        // Return the final array, indexed numerically
        return $result->values()->all();
    }
}
