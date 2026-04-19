<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ModListItemFactory;
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
 * @property bool $added_as_dependency
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
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'added_as_dependency' => 'boolean',
            'position' => 'integer',
            'listable_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
