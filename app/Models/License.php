<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Override;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property string $name
 * @property string $link
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Mod> $mods
 */
final class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    /**
     * Get all licenses ordered by name, cached for 1 hour.
     *
     * @return Collection<int, self>
     */
    public static function cachedOrdered(): Collection
    {
        return Cache::flexible('licenses:ordered', [3600, 7200], fn (): Collection => self::query()->orderBy('name')->get());
    }

    /**
     * The relationship between a license and mod.
     *
     * @return HasMany<Mod, $this>
     */
    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class);
    }

    /**
     * Boot the model.
     */
    #[Override]
    protected static function booted(): void
    {
        self::saved(fn () => Cache::forget('licenses:ordered'));
        self::deleted(fn () => Cache::forget('licenses:ordered'));
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'hub_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
