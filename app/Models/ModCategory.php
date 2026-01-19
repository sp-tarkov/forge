<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModCategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Override;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property string $title
 * @property string $slug
 * @property bool $shows_profile_binding_notice
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Mod> $mods
 */
class ModCategory extends Model
{
    /** @use HasFactory<ModCategoryFactory> */
    use HasFactory;

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
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ModCategory $modCategory): void {
            if (empty($modCategory->slug)) {
                $modCategory->slug = Str::slug($modCategory->title);
            }
        });

        static::updating(function (ModCategory $modCategory): void {
            if ($modCategory->isDirty('title')) {
                $modCategory->slug = Str::slug($modCategory->title);
            }
        });
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
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
