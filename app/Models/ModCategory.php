<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModCategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property string $title
 * @property string|null $description
 * @property int $show_order
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
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'hub_id' => 'integer',
            'show_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
