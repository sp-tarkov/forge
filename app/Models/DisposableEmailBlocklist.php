<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $domain
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DisposableEmailBlocklist extends Model
{
    protected $table = 'disposable_email_blocklist';

    /**
     * Check if a domain is disposable.
     */
    public static function isDisposable(string $domain): bool
    {
        $version = (int) Cache::get('disposable_email_version', 0);

        return Cache::remember("disposable_email_v{$version}_{$domain}", 3600, fn () => self::query()->where('domain', $domain)->exists());
    }

    /**
     * Clear the cache for a specific domain.
     */
    public static function clearDomainCache(string $domain): void
    {
        $version = (int) Cache::get('disposable_email_version', 0);
        Cache::forget("disposable_email_v{$version}_{$domain}");
    }

    /**
     * Invalidate all disposable email caches by incrementing the version key.
     */
    public static function clearAllCaches(): void
    {
        Cache::increment('disposable_email_version');
    }
}
