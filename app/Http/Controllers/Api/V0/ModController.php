<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Filters\V1\ModFilter;
use App\Http\Resources\Api\V0\ModResource;
use App\Models\Mod;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\UrlParam;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ModController extends ApiController
{
    /**
     * Get Mods
     *
     * List, filter, and sort basic information about mods.
     *
     * @group Mods
     */
    #[QueryParam('include', 'string', 'The relationships to include within the `includes` key. By default no relationships are automatically included.', required: false, example: 'users,versions,license')]
    #[QueryParam('filter[id]', 'string', 'Filter by the `id`. Select multiple by separating the IDs with a comma.', required: false, example: '5,10,15')]
    #[QueryParam('filter[hub_id]', 'string', 'Filter by the `hub_id` attribute. Select multiple by separating the IDs with a comma.', required: false, example: '20')]
    #[QueryParam('filter[name]', 'string', 'Filter by the `name` attribute. Use `*` as the wildcard character.', required: false, example: '*SAIN*')]
    #[QueryParam('filter[slug]', 'string', 'Filter by the `slug` attribute. Use `*` as the wildcard character.', required: false, example: '*raid-times')]
    #[QueryParam('filter[teaser]', 'string', 'Filter by the `teaser` attribute. Use `*` as the wildcard character.', required: false, example: '*weighted*random*times*')]
    #[QueryParam('filter[source_code_link]', 'string', 'Filter by the `source_code_link` attribute. Use `*` as the wildcard character.', required: false, example: '*https*.net*')]
    #[QueryParam('filter[featured]', 'boolean', 'Filter by the `featured` attribute. All "truthy" or "falsy" values are supported.', required: false, example: 'true')]
    #[QueryParam('filter[contains_ads]', 'boolean', 'Filter by the `contains_ads` attribute. All "truthy" or "falsy" values are supported.', required: false, example: 'true')]
    #[QueryParam('filter[contains_ai_content]', 'boolean', 'Filter by the `contains_ai_content` attribute. All "truthy" or "falsy" values are supported.', required: false, example: 'true')]
    #[QueryParam('filter[created_at]', 'string', 'Filter by the `created_at` attribute. Ranges are possible by separating the dates with a comma.', required: false, example: '2023-12-31,2024-12-31')]
    #[QueryParam('filter[updated_at]', 'string', 'Filter by the `updated_at` attribute. Ranges are possible by separating the dates with a comma.', required: false, example: '2023-12-31,2024-12-31')]
    #[QueryParam('filter[published_at]', 'string', 'Filter by the `published_at` attribute. Ranges are possible by seperating the dates with a comma.', required: false, example: '2023-12-31,2024-12-31')]
    #[QueryParam('sort', 'string', 'Sort the results by a comma seperated list of attributes. The default sort direction is ASC, append the attribute name with a minus to sort DESC.', required: false, example: '-featured,name')]
    public function index(ModFilter $modFilter): AnonymousResourceCollection
    {
        $mods = Mod::filter($modFilter)->paginate();

        throw_if($mods->isEmpty(), new NotFoundHttpException);

        return ModResource::collection($mods);
    }

    /**
     * Get Mod
     *
     * Display more detailed information about a specific mod.
     *
     * @group Mods
     */
    #[UrlParam('id', 'integer', 'The ID of the mod.', required: true, example: 558)]
    #[QueryParam('include', 'string', 'The relationships to include within the `includes` key. By default no relationships are automatically included.', required: false, example: 'users,versions,license')]
    public function show(Mod $mod): ModResource
    {
        return new ModResource($mod);
    }
}
