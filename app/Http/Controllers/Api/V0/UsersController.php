<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V0;

use App\Http\Filters\V1\UserFilter;
use App\Http\Resources\Api\V0\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\QueryParam;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UsersController extends ApiController
{
    /**
     * Get Users
     *
     * List, filter, and sort basic information about users.
     *
     * @group Users
     */
    #[QueryParam('include', 'string', 'The relationships to include within the `includes` key. By default no relationships are automatically included.', required: false, example: 'user_role')]
    #[QueryParam('filter[id]', 'string', 'Filter by the `id`. Select multiple by separating the IDs with a comma.', required: false, example: '5,10,15')]
    #[QueryParam('filter[name]', 'string', 'Filter by the `name` attribute. Use `*` as the wildcard character.', required: false, example: '*fringe')]
    #[QueryParam('filter[created_at]', 'string', 'Filter by the `created_at` attribute. Ranges are possible by separating the dates with a comma.', required: false, example: '2023-12-31,2024-12-31')]
    #[QueryParam('filter[updated_at]', 'string', 'Filter by the `updated_at` attribute. Ranges are possible by separating the dates with a comma.', required: false, example: '2023-12-31,2024-12-31')]
    #[QueryParam('sort', 'string', 'Sort the results by a comma seperated list of attributes. The default sort direction is ASC, append the attribute name with a minus to sort DESC.', required: false, example: 'created_at,-name')]
    public function index(UserFilter $userFilter): AnonymousResourceCollection
    {
        $users = User::filter($userFilter)->paginate();

        if ($users->isEmpty()) {
            throw new NotFoundHttpException;
        }

        return UserResource::collection($users);
    }

    /**
     * Get User
     *
     * Display more detailed information about a specific user.
     *
     * @group Users
     */
    #[QueryParam('include', 'string', 'The relationships to include within the `includes` key. By default no relationships are automatically included.', required: false, example: 'user_role')]
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }
}
