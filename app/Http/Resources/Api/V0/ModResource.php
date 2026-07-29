<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V0;

use App\Models\Mod;
use App\Support\Api\V0\QueryBuilder\AbstractQueryBuilder;
use App\Support\Api\V0\QueryBuilder\ModQueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Override;

/**
 * @mixin Mod
 *
 * @property Mod $resource
 */
final class ModResource extends JsonResource
{
    /**
     * The fields requested by the client.
     *
     * @var array<string>
     */
    private array $requestedFields = [];

    /**
     * Whether to show all fields (no specific fields requested).
     */
    private bool $showAllFields = true;

    /**
     * The query builder that hydrated the mod, whose field contract bounds this response.
     *
     * @var class-string<AbstractQueryBuilder<Mod>>
     */
    private string $queryBuilder = ModQueryBuilder::class;

    /**
     * Set the query builder that hydrated the mod. Fields outside that builder's contract are omitted from the response.
     *
     * @param  class-string<AbstractQueryBuilder<Mod>>  $queryBuilder
     */
    public function hydratedBy(string $queryBuilder): self
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        // Use field filtering
        $this->requestedFields = $request->string('fields', '')
            ->explode(',')
            ->map(fn (string $field): string => mb_trim($field))
            ->filter()
            ->all();

        $this->showAllFields = $this->requestedFields === [];

        $data = [];

        // Handle regular fields
        if ($this->shouldInclude('id')) {
            $data['id'] = $this->resource->id;
        }

        if ($this->shouldInclude('hub_id')) {
            $data['hub_id'] = $this->resource->hub_id;
        }

        if ($this->shouldInclude('guid')) {
            $data['guid'] = $this->resource->guid;
        }

        if ($this->shouldInclude('name')) {
            $data['name'] = $this->resource->name;
        }

        if ($this->shouldInclude('slug')) {
            $data['slug'] = $this->resource->slug;
        }

        if ($this->shouldInclude('teaser')) {
            $data['teaser'] = $this->resource->teaser;
        }

        if ($this->shouldInclude('thumbnail')) {
            $data['thumbnail'] = $this->resource->thumbnailUrl;
        }

        if ($this->shouldInclude('downloads')) {
            $data['downloads'] = $this->resource->downloads;
        }

        if ($this->shouldInclude('favourites_count')) {
            $data['favourites_count'] = $this->resource->favourites_count;
        }

        if ($this->shouldInclude('description')) {
            $data['description'] = $this->when(
                $request->routeIs('api.v0.mods.show'),
                $this->resource->description_html,
            );
        }

        if ($this->shouldInclude('detail_url')) {
            $data['detail_url'] = $this->resource->detail_url;
        }

        if ($this->shouldInclude('fika_compatibility')) {
            $data['fika_compatibility'] = $this->resource->fika_compatibility;
        }

        if ($this->shouldInclude('featured')) {
            $data['featured'] = (bool) $this->resource->featured;
        }

        if ($this->shouldInclude('contains_ads')) {
            $data['contains_ads'] = (bool) $this->resource->contains_ads;
        }

        if ($this->shouldInclude('contains_ai_content')) {
            $data['contains_ai_content'] = (bool) $this->resource->contains_ai_content;
        }

        if ($this->shouldInclude('custom_ai_disclosure')) {
            $data['custom_ai_disclosure'] = $this->when(
                $request->routeIs('api.v0.mods.show'),
                $this->resource->custom_ai_disclosure_html,
            );
        }

        if ($this->shouldInclude('shows_profile_binding_notice')) {
            $data['shows_profile_binding_notice'] = (bool) $this->resource->shows_profile_binding_notice;
        }

        if ($this->shouldInclude('cheat_notice')) {
            $data['cheat_notice'] = (bool) $this->resource->cheat_notice;
        }

        if ($this->shouldInclude('category_id')) {
            $data['category_id'] = $this->resource->category_id;
        }

        if ($this->shouldInclude('published_at')) {
            $data['published_at'] = $this->resource->published_at?->toISOString();
        }

        if ($this->shouldInclude('created_at')) {
            $data['created_at'] = $this->resource->created_at?->toISOString();
        }

        if ($this->shouldInclude('updated_at')) {
            $data['updated_at'] = $this->resource->updated_at?->toISOString();
        }

        // Owner and additional_authors are serialized when the hydrating query builder loads authorship
        if ($this->hydratesAuthorship()) {
            $data['owner'] = $this->resource->owner ? new UserResource($this->resource->owner) : null;
            $data['additional_authors'] = UserResource::collection($this->resource->additionalAuthors);
        }

        // Other relationships are only included when loaded
        $data['versions'] = ModVersionResource::collection($this->whenLoaded('versions', fn (): Collection => $this->resource->versions->take(10)));
        $data['license'] = $this->whenLoaded('license', fn (): ?LicenseResource => $this->resource->license ? new LicenseResource($this->resource->license) : null);
        $data['category'] = $this->whenLoaded('category', fn (): ?ModCategoryResource => $this->resource->category ? new ModCategoryResource($this->resource->category) : null);
        $data['source_code_links'] = SourceCodeLinkResource::collection($this->whenLoaded('sourceCodeLinks'));

        return $data;
    }

    /**
     * Check if the hydrating query builder selects the ownership column the authorship relationships resolve through.
     */
    private function hydratesAuthorship(): bool
    {
        $queryBuilder = $this->queryBuilder;

        return in_array('owner_id', $queryBuilder::getRequiredFields(), true)
            || in_array('owner_id', $queryBuilder::getAllAllowedFields(), true);
    }

    /**
     * Check if a field should be included in the response. Required fields are always included; every other field
     * must be exposed by the hydrating query builder and either requested or covered by an unfiltered request.
     *
     * @param  string  $field  The field name to check
     * @return bool Whether the field should be included
     */
    private function shouldInclude(string $field): bool
    {
        $queryBuilder = $this->queryBuilder;

        if (in_array($field, $queryBuilder::getRequiredFields(), true)) {
            return true;
        }

        if (! in_array($field, $queryBuilder::getAllAllowedFields(), true)) {
            return false;
        }

        return $this->showAllFields || in_array($field, $this->requestedFields, true);
    }
}
