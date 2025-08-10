<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ModResolvedDependency Model
 *
 * @property int $id
 * @property int $mod_version_id
 * @property int $dependency_id
 * @property int $resolved_mod_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ModVersion|null $modVersion
 * @property-read ModDependency|null $dependency
 * @property-read ModVersion|null $resolvedModVersion
 */
class ModResolvedDependency extends Model
{
    /** @use HasFactory<Factory<ModResolvedDependency>> */
    use HasFactory;

    /**
     * The relationship between the resolved dependency and the *parent* mod version.
     *
     * @return BelongsTo<ModVersion, $this>
     */
    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'mod_version_id');
    }

    /**
     * The relationship between the resolved dependency and the (unresolved) dependency.
     *
     * @return BelongsTo<ModDependency, $this>
     */
    public function dependency(): BelongsTo
    {
        return $this->belongsTo(ModDependency::class);
    }

    /**
     * The relationship between the resolved dependency and the resolved *dependent* mod version.
     *
     * @return BelongsTo<ModVersion, $this>
     */
    public function resolvedModVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'resolved_mod_version_id');
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
