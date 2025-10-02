<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * A caching service for Laravel's Gate system.
 *
 * Caches gate authorization results for the duration of the request to prevent redundant policy checks.
 */
#[Scoped]
class CachedGateService
{
    /**
     * Request-scoped cache for gate results.
     *
     * @var array<string, bool>
     */
    private array $cache = [];

    /**
     * Cache statistics for debugging.
     *
     * @var array<string, int>
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'calls' => 0,
    ];

    /**
     * Check if the user is authorized for a given ability.
     */
    public function allows(string $ability, mixed $arguments = []): bool
    {
        $this->stats['calls']++;

        $key = $this->makeCacheKey($ability, $arguments);

        if (array_key_exists($key, $this->cache)) {
            $this->stats['hits']++;

            return $this->cache[$key];
        }

        $this->stats['misses']++;

        return $this->cache[$key] = Gate::allows($ability, $arguments);
    }

    /**
     * Check if the user is denied for a given ability.
     */
    public function denies(string $ability, mixed $arguments = []): bool
    {
        return ! $this->allows($ability, $arguments);
    }

    /**
     * Check multiple permissions at once for efficiency.
     *
     * @param  array<string>  $abilities
     * @return array<string, bool>
     */
    public function batchCheck(array $abilities, mixed $arguments = []): array
    {
        $results = [];

        foreach ($abilities as $ability) {
            $results[$ability] = $this->allows($ability, $arguments);
        }

        return $results;
    }

    /**
     * Check permissions for multiple models at once.
     *
     * @param  array<Model>  $models
     * @return array<int|string, bool>
     */
    public function batchCheckModels(string $ability, array $models): array
    {
        $results = [];

        foreach ($models as $model) {
            $key = $model->getKey();
            $results[$key] = $this->allows($ability, $model);
        }

        return $results;
    }

    /**
     * Authorize or throw an exception (equivalent to Gate::authorize).
     *
     * @throws AuthorizationException
     */
    public function authorize(string $ability, mixed $arguments = []): void
    {
        if ($this->denies($ability, $arguments)) {
            Gate::authorize($ability, $arguments); // Let Laravel handle the exception
        }
    }

    /**
     * Clear the entire cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'calls' => 0,
        ];
    }

    /**
     * Clear cache for a specific model.
     */
    public function clearForModel(Model $model): void
    {
        $modelClass = $model::class;
        $modelKey = $model->getKey();
        $userId = auth()->id() ?? 'guest';

        // Clear all cached permissions for this specific model
        $pattern = sprintf('gate.%s.', $userId);
        $modelPattern = sprintf('.%s.%s', $modelClass, $modelKey);

        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $pattern) && str_contains($key, $modelPattern)) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * Clear cache for a specific user.
     */
    public function clearForUser(int|string|null $userId = null): void
    {
        $userId ??= auth()->id() ?? 'guest';
        $pattern = sprintf('gate.%s.', $userId);

        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $pattern)) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * Clear cache for a specific ability.
     */
    public function clearForAbility(string $ability): void
    {
        $userId = auth()->id() ?? 'guest';
        $pattern = sprintf('gate.%s.%s.', $userId, $ability);

        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $pattern)) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * Get cache statistics for debugging.
     *
     * @return array<string, int|float>
     */
    public function getStats(): array
    {
        $hitRate = $this->stats['calls'] > 0
            ? round(($this->stats['hits'] / $this->stats['calls']) * 100, 2)
            : 0;

        return [
            ...$this->stats,
            'hit_rate' => $hitRate,
            'cache_size' => count($this->cache),
        ];
    }

    /**
     * Generate a unique cache key for the given ability and arguments.
     */
    private function makeCacheKey(string $ability, mixed $arguments): string
    {
        $userId = auth()->id() ?? 'guest';

        // Handle different argument types
        if ($arguments === null || $arguments === []) {
            $argsHash = 'null';
        } elseif (is_array($arguments)) {
            $argsHash = $this->hashArguments($arguments);
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
    private function hashArguments(array $arguments): string
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
