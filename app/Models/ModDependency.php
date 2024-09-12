<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $mod_version_id
 * @property int $dependency_mod_id
 * @property string $constraint
 * @property int|null $resolved_version_id
 */
class ModDependency extends Model
{
    use HasFactory;

    /**
     * The relationship between the mod dependency and the mod version.
     */
    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class);
    }

    /**
     * The relationship between the mod dependency and the resolved dependency.
     */
    public function resolvedDependencies(): HasMany
    {
        return $this->hasMany(ModResolvedDependency::class, 'dependency_id')
            ->chaperone();
    }

    /**
     * The relationship between the mod dependency and the dependent mod.
     */
    public function dependentMod(): BelongsTo
    {
        return $this->belongsTo(Mod::class, 'dependent_mod_id');
    }
}
