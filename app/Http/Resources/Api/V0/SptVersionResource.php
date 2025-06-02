<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Support\Api\V0\QueryBuilder\SptVersionQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class SptVersionResource extends JsonResource
{
    /**
     * The fields requested in the request.
     *
     * @var array<int, string>
     */
    protected array $requestedFields = [];

    /**
     * Whether to show all fields.
     */
    protected bool $showAllFields = true;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $this->requestedFields = $request->string('fields', '')
            ->explode(',')
            ->map(fn (string $field): string => trim($field))
            ->filter()
            ->toArray();

        $this->showAllFields = empty($this->requestedFields);

        $data = [];

        if ($this->shouldInclude('id')) {
            $data['id'] = $this->resource->id;
        }

        if ($this->shouldInclude('version')) {
            $data['version'] = $this->resource->version;
        }

        if ($this->shouldInclude('version_major')) {
            $data['version_major'] = $this->resource->version_major;
        }

        if ($this->shouldInclude('version_minor')) {
            $data['version_minor'] = $this->resource->version_minor;
        }

        if ($this->shouldInclude('version_patch')) {
            $data['version_patch'] = $this->resource->version_patch;
        }

        if ($this->shouldInclude('version_labels')) {
            $data['version_labels'] = $this->resource->version_labels;
        }

        if ($this->shouldInclude('mod_count')) {
            $data['mod_count'] = $this->resource->mod_count;
        }

        if ($this->shouldInclude('link')) {
            $data['link'] = $this->resource->link;
        }

        if ($this->shouldInclude('color_class')) {
            $data['color_class'] = $this->resource->color_class;
        }

        if ($this->shouldInclude('created_at')) {
            $data['created_at'] = $this->resource->created_at;
        }

        if ($this->shouldInclude('updated_at')) {
            $data['updated_at'] = $this->resource->updated_at;
        }

        return $data;
    }

    public function shouldInclude(string $field): bool
    {
        $requiredFields = SptVersionQueryBuilder::getRequiredFields();

        return $this->showAllFields
            || in_array($field, $this->requestedFields, true)
            || in_array($field, $requiredFields, true);
    }
}
