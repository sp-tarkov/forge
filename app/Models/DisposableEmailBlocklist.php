<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DisposableEmailBlocklistFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $domain
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table(name: 'disposable_email_blocklist')]
final class DisposableEmailBlocklist extends Model
{
    /** @use HasFactory<DisposableEmailBlocklistFactory> */
    use HasFactory;

    /**
     * Check if a domain is disposable.
     */
    public static function isDisposable(string $domain): bool
    {
        return Cache::remember('disposable_email_'.$domain, 3600, fn () => self::query()->where('domain', $domain)->exists());
    }

    /**
     * Clear the cache for a specific domain.
     */
    public static function clearDomainCache(string $domain): void
    {
        Cache::forget('disposable_email_'.$domain);
    }

    /**
     * Clear all disposable email caches.
     */
    public static function clearAllCaches(): void
    {
        Cache::flush();
    }
}
