<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ModCategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Override;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property string $title
 * @property string $slug
 * @property bool $shows_profile_binding_notice
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Mod> $mods
 */
final class ModCategory extends Model
{
    /** @use HasFactory<ModCategoryFactory> */
    use HasFactory;

    /**
     * Get all categories ordered by title, cached for 1 hour.
     *
     * @return Collection<int, self>
     */
    public static function cachedOrdered(): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = Cache::flexible('mod-categories:ordered', [3600, 7200], fn (): array => self::query()->orderBy('title')->get()->toArray());

        return self::hydrate($items);
    }

    /**
     * The mods in this category.
     *
     * @return HasMany<Mod, $this>
     */
    public function mods(): HasMany
    {
        return $this->hasMany(Mod::class, 'category_id');
    }

    /**
     * Boot the model.
     */
    #[Override]
    protected static function booted(): void
    {
        self::creating(function (ModCategory $modCategory): void {
            if (empty($modCategory->slug)) {
                $modCategory->slug = Str::slug($modCategory->title);
            }
        });

        self::updating(function (ModCategory $modCategory): void {
            if ($modCategory->isDirty('title')) {
                $modCategory->slug = Str::slug($modCategory->title);
            }
        });

        self::saved(fn () => Cache::forget('mod-categories:ordered'));
        self::deleted(fn () => Cache::forget('mod-categories:ordered'));
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
            'shows_profile_binding_notice' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
