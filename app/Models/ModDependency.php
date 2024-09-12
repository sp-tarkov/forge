<?php

namespace App\Models;

use Database\Factories\ModDependencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModDependency extends Model
{
    /** @use HasFactory<ModDependencyFactory> */
    use HasFactory;

    /**
     * The relationship between the mod dependency and the mod version.
     *
     * @return BelongsTo<ModVersion, ModDependency>
     */
    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class);
    }

    /**
     * The relationship between the mod dependency and the resolved dependency.
     *
     * @return HasMany<ModResolvedDependency>
     */
    public function resolvedDependencies(): HasMany
    {
        return $this->hasMany(ModResolvedDependency::class, 'dependency_id')
            ->chaperone();
    }

    /**
     * The relationship between the mod dependency and the dependent mod.
     *
     * @return BelongsTo<Mod, ModDependency>
     */
    public function dependentMod(): BelongsTo
    {
        return $this->belongsTo(Mod::class, 'dependent_mod_id');
    }
}
