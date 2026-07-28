<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Reads and invalidates the cached guest-viewpoint id lists behind the homepage sections. Each section is cached under
 * its own key, letting mod-driven sections and the comment feed invalidate independently.
 */
final class HomepageSectionCache
{
    public const string FEATURED = 'featured';

    public const string NEWEST = 'newest';

    public const string UPDATED = 'updated';

    public const string COMMENTS = 'comments';

    /**
     * The sections whose id lists are derived from mod and mod version data.
     */
    private const array MOD_SECTIONS = [self::FEATURED, self::NEWEST, self::UPDATED];

    /**
     * The cache key prefix shared by every homepage section.
     */
    private const string PREFIX = 'homepage:sections:';

    /**
     * Seconds the cached ids are fresh, then served stale while a deferred refresh runs.
     *
     * @var array{int, int}
     */
    private const array TTL = [300, 900];

    /**
     * Read the cached id list for the given section, computing it with the callback on a miss.
     *
     * @param  callable(): list<int>  $callback
     * @return list<int>
     */
    public static function remember(string $section, callable $callback): array
    {
        return Cache::flexible(self::PREFIX.$section, self::TTL, $callback);
    }

    /**
     * Forget the cached featured, newest, and updated sections.
     */
    public static function flushModSections(): void
    {
        foreach (self::MOD_SECTIONS as $section) {
            self::forget($section);
        }
    }

    /**
     * Forget the cached comment feed section.
     */
    public static function flushComments(): void
    {
        self::forget(self::COMMENTS);
    }

    /**
     * Forget a section's cache entry and the created timestamp Cache::flexible() tracks for it.
     */
    private static function forget(string $section): void
    {
        Cache::forget(self::PREFIX.$section);
        Cache::forget('illuminate:cache:flexible:created:'.self::PREFIX.$section);
    }
}
