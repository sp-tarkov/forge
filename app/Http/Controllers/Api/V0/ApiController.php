<?php

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ApiController extends Controller
{
    /**
     * Determine if the given relationship should be included in the request. If more than one relationship is provided,
     * only one needs to be present in the request for this method to return true.
     *
     * @param  string|string[]  $relationships
     */
    public static function shouldInclude(string|array $relationships): bool
    {
        try {
            $param = request()->get('include');
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            return false;
        }

        if (! $param) {
            return false;
        }

        $includeValues = explode(',', Str::lower($param));

        if (is_array($relationships)) {
            foreach ($relationships as $relationship) {
                if (in_array(Str::lower($relationship), $includeValues)) {
                    return true;
                }
            }

            return false;
        }

        return in_array(Str::lower($relationships), $includeValues);
    }
}
