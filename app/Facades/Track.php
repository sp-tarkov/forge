<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\TrackService;
use Illuminate\Support\Facades\Facade;

class Track extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TrackService::class;
    }
}
