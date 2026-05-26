<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ModListItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property int $id
 * @property int $mod_list_id
 * @property string $listable_type
 * @property int $listable_id
 * @property string|null $note
 * @property int $position
 * @property CarbonImmutable|null $tombstoned_at
 * @property string|null $tombstoned_name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Mod|Addon|null $listable
 * @property-read ModList $modList
 */
final class ModListItem extends Model
{
    /** @use HasFactory<ModListItemFactory> */
    use HasFactory;

    /**
     * The list this item belongs to.
     *
     * @return BelongsTo<ModList, $this>
     */
    public function modList(): BelongsTo
    {
        return $this->belongsTo(ModList::class);
    }

    /**
     * The underlying Mod or Addon this item references.
     *
     * @return MorphTo<Model, $this>
     */
    public function listable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether this item references a Mod.
     */
    public function isMod(): bool
    {
        return $this->listable_type === Mod::class;
    }

    /**
     * Whether this item references an Addon.
     */
    public function isAddon(): bool
    {
        return $this->listable_type === Addon::class;
    }

    /**
     * Whether this item is a tombstone left behind after the author opted out of mod lists.
     */
    public function isTombstone(): bool
    {
        return $this->tombstoned_at !== null;
    }

    /**
     * Scope a query to active (non-tombstoned) items.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('tombstoned_at');
    }

    /**
     * Scope a query to tombstoned items.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeTombstoned(Builder $query): Builder
    {
        return $query->whereNotNull('tombstoned_at');
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
            'position' => 'integer',
            'listable_id' => 'integer',
            'tombstoned_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
