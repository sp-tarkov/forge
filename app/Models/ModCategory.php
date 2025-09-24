<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ModCategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property int|null $parent_category_id
 * @property string $title
 * @property string|null $description
 * @property int $show_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ModCategory|null $parent
 * @property-read Collection<int, ModCategory> $children
 * @property-read Collection<int, Mod> $mods
 */
class ModCategory extends Model
{
    /** @use HasFactory<ModCategoryFactory> */
    use HasFactory;

    /**
     * The parent category relationship.
     *
     * @return BelongsTo<ModCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ModCategory::class, 'parent_category_id');
    }

    /**
     * The child categories relationship.
     *
     * @return HasMany<ModCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ModCategory::class, 'parent_category_id')->orderBy('show_order');
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
     * Get all ancestors of this category.
     *
     * @return Collection<int, ModCategory>
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors->reverse();
    }

    /**
     * Check if this category is a root category.
     */
    public function isRoot(): bool
    {
        return $this->parent_category_id === null;
    }

    /**
     * Check if this category has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'hub_id' => 'integer',
            'parent_category_id' => 'integer',
            'show_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
