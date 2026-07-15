<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\VerificationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin VerificationResult
 *
 * @property VerificationResult $resource
 */
final class VerificationFileTreeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $files = array_values($this->resource->file_tree ?? []);

        return [
            'verified_at' => $this->resource->completed_at?->toISOString(),
            'file_count' => count($files),
            'truncated' => (bool) ($this->resource->details['file_tree_truncated'] ?? false),
            'files' => $files,
        ];
    }
}
