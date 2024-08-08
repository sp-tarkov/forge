<?php

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    public static function shouldInclude(string $relationship): bool
    {
        $param = request()->get('include');

        if (! $param) {
            return false;
        }

        $includeValues = explode(',', Str::lower($param));

        return in_array(Str::lower($relationship), $includeValues);
    }
}
