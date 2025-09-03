<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * A caching wrapper for Laravel's Gate system.
 *
 * Caches gate authorization results for the duration of the request to prevent redundant policy checks. Particularly
 * useful for views that check the same permissions multiple times.
 */
class CachedGate
{
    /**
     * Request-scoped cache for gate results.
     *
     * @var array<string, bool>
     */
    private static array $cache = [];

    /**
     * Cache statistics for debugging.
     *
     * @var array<string, int>
     */
    private static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'calls' => 0,
    ];

    /**
     * Check if the user is authorized for a given ability.
     */
    public static function allows(string $ability, mixed $arguments = []): bool
    {
        self::$stats['calls']++;

        $key = self::makeCacheKey($ability, $arguments);

        if (array_key_exists($key, self::$cache)) {
            self::$stats['hits']++;

            return self::$cache[$key];
        }

        self::$stats['misses']++;

        return self::$cache[$key] = Gate::allows($ability, $arguments);
    }

    /**
     * Check if the user is denied for a given ability.
     */
    public static function denies(string $ability, mixed $arguments = []): bool
    {
        return ! self::allows($ability, $arguments);
    }

    /**
     * Check multiple permissions at once for efficiency.
     *
     * @param  array<string>  $abilities
     * @return array<string, bool>
     */
    public static function batchCheck(array $abilities, mixed $arguments = []): array
    {
        $results = [];

        foreach ($abilities as $ability) {
            $results[$ability] = self::allows($ability, $arguments);
        }

        return $results;
    }

    /**
     * Check permissions for multiple models at once.
     *
     * @param  array<Model>  $models
     * @return array<int|string, bool>
     */
    public static function batchCheckModels(string $ability, array $models): array
    {
        $results = [];

        foreach ($models as $model) {
            $key = $model->getKey();
            $results[$key] = self::allows($ability, $model);
        }

        return $results;
    }

    /**
     * Authorize or throw an exception (equivalent to Gate::authorize).
     *
     * @throws AuthorizationException
     */
    public static function authorize(string $ability, mixed $arguments = []): void
    {
        if (self::denies($ability, $arguments)) {
            Gate::authorize($ability, $arguments); // Let Laravel handle the exception
        }
    }

    /**
     * Clear the entire cache.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$stats = [
            'hits' => 0,
            'misses' => 0,
            'calls' => 0,
        ];
    }

    /**
     * Clear cache for a specific model.
     */
    public static function clearForModel(Model $model): void
    {
        $modelClass = $model::class;
        $modelKey = $model->getKey();
        $userId = auth()->id() ?? 'guest';

        // Clear all cached permissions for this specific model
        $pattern = sprintf('gate.%s.', $userId);
        $modelPattern = sprintf('.%s.%s', $modelClass, $modelKey);

        foreach (array_keys(self::$cache) as $key) {
            if (str_starts_with($key, $pattern) && str_contains($key, $modelPattern)) {
                unset(self::$cache[$key]);
            }
        }
    }

    /**
     * Clear cache for a specific user.
     */
    public static function clearForUser(int|string|null $userId = null): void
    {
        $userId ??= auth()->id() ?? 'guest';
        $pattern = sprintf('gate.%s.', $userId);

        foreach (array_keys(self::$cache) as $key) {
            if (str_starts_with($key, $pattern)) {
                unset(self::$cache[$key]);
            }
        }
    }

    /**
     * Clear cache for a specific ability.
     */
    public static function clearForAbility(string $ability): void
    {
        $userId = auth()->id() ?? 'guest';
        $pattern = sprintf('gate.%s.%s.', $userId, $ability);

        foreach (array_keys(self::$cache) as $key) {
            if (str_starts_with($key, $pattern)) {
                unset(self::$cache[$key]);
            }
        }
    }

    /**
     * Get cache statistics for debugging.
     *
     * @return array<string, int|float>
     */
    public static function getStats(): array
    {
        $hitRate = self::$stats['calls'] > 0
            ? round((self::$stats['hits'] / self::$stats['calls']) * 100, 2)
            : 0;

        return [
            ...self::$stats,
            'hit_rate' => $hitRate,
            'cache_size' => count(self::$cache),
        ];
    }

    /**
     * Generate a unique cache key for the given ability and arguments.
     */
    private static function makeCacheKey(string $ability, mixed $arguments): string
    {
        $userId = auth()->id() ?? 'guest';

        // Handle different argument types
        if ($arguments === null || $arguments === []) {
            $argsHash = 'null';
        } elseif (is_array($arguments)) {
            $argsHash = self::hashArguments($arguments);
        } elseif ($arguments instanceof Model) {
            $argsHash = $arguments::class.'.'.$arguments->getKey();
        } else {
            $argsHash = md5(serialize($arguments));
        }

        return sprintf('gate.%s.%s.%s', $userId, $ability, $argsHash);
    }

    /**
     * Create a hash for an array of arguments.
     *
     * @param  array<mixed>  $arguments
     */
    private static function hashArguments(array $arguments): string
    {
        $normalized = [];

        foreach ($arguments as $arg) {
            if ($arg instanceof Model) {
                $normalized[] = $arg::class.'.'.$arg->getKey();
            } else {
                $normalized[] = $arg;
            }
        }

        return md5(serialize($normalized));
    }
}
