<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\CachedGateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for CachedGateService.
 *
 * @method static bool allows(string $ability, mixed $arguments = [])
 * @method static bool denies(string $ability, mixed $arguments = [])
 * @method static array<string, bool> batchCheck(array<string> $abilities, mixed $arguments = [])
 * @method static array<int|string, bool> batchCheckModels(string $ability, array<Model> $models)
 * @method static array<int|string, array<string, bool>> batchCheckMultiple(array<string> $abilities, array<Model> $models)
 * @method static void authorize(string $ability, mixed $arguments = [])
 * @method static void clearCache()
 * @method static void clearForModel(Model $model)
 * @method static void clearForUser(int|string|null $userId = null)
 * @method static void clearForAbility(string $ability)
 * @method static array<string, int|float> getStats()
 *
 * @see CachedGateService
 */
class CachedGate extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return CachedGateService::class;
    }
}
