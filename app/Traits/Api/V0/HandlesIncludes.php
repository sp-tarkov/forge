<?php

declare(strict_types=1);

namespace App\Traits\Api\V0;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HandlesIncludes
{
    /**
     * Parses the 'include' query parameter and loads allowed relationships.
     *
     * @param  Request  $request  The incoming request.
     * @param  Model  $model  The Eloquent model instance to load relationships onto.
     * @param  array<int, string>  $allowedIncludes  List of relationship names allowed for inclusion.
     */
    protected function loadIncludes(Request $request, Model $model, array $allowedIncludes): void
    {
        $requestedIncludes = $request->query('include', '');
        $includes = ! empty($requestedIncludes) ? explode(',', $requestedIncludes) : [];

        // Filter to only include allowed relationships
        $validIncludes = array_intersect($includes, $allowedIncludes);

        // Eager load the valid requested relationships
        if (! empty($validIncludes)) {
            $model->loadMissing($validIncludes);
        }
    }
}
