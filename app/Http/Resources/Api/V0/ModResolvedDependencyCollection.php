<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Override;

class ModResolvedDependencyCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        // Group dependencies by mod ID
        $grouped = $this->collection->groupBy(fn (ModResolvedDependencyResource $dependency): int => $dependency->mod->id);

        // Transform each group into a single mod resource with all its versions
        return $grouped
            ->map(function (Collection $dependencies) use ($request) {

                $firstDependency = $dependencies->first();
                $modData = (new ModResource($firstDependency->mod))->toArray($request);

                // Collect all versions from the dependencies
                $modData['versions'] = $dependencies->map(fn ($dependency): ModVersionResource => new ModVersionResource($dependency))->values();

                return $modData;
            })
            ->values()
            ->toArray();
    }
}
