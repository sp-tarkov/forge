<?php

declare(strict_types=1);

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string encode(array<int> $numbers)
 * @method static array<int> decode(string $id)
 *
 * @see \Sqids\Sqids
 */
class Sqids extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Sqids\Sqids::class;
    }
}
