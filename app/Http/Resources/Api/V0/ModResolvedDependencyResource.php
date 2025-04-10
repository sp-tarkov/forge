<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class ModResolvedDependencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<int|string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $modData = (new ModResource($this->resolvedModVersion->mod))->toArray($request);
        $modData['versions'] = [new ModVersionResource($this->resolvedModVersion)];

        return $modData;
    }
}
