<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $mod_version_id
 * @property int $spt_version_id
 * @property bool $pinned_to_spt_publish
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ModVersion $modVersion
 * @property-read SptVersion $sptVersion
 */
class ModVersionSptVersion extends Pivot
{
    /** @use HasFactory<Factory<ModVersionSptVersion>> */
    use HasFactory;

    public $incrementing = true;

    public $timestamps = true;

    /**
     * Get the mod version associated with this pivot.
     *
     * @return BelongsTo<ModVersion, $this>
     */
    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class);
    }

    /**
     * Get the SPT version associated with this pivot.
     *
     * @return BelongsTo<SptVersion, $this>
     */
    public function sptVersion(): BelongsTo
    {
        return $this->belongsTo(SptVersion::class);
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pinned_to_spt_publish' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
