<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModResolvedDependency extends Model
{
    /**
     * The relationship between the resolved dependency and the mod version.
     *
     * @return BelongsTo<ModVersion, ModResolvedDependency>
     */
    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'mod_version_id');
    }

    /**
     * The relationship between the resolved dependency and the dependency.
     *
     * @return BelongsTo<ModDependency, ModResolvedDependency>
     */
    public function dependency(): BelongsTo
    {
        return $this->belongsTo(ModDependency::class);
    }

    /**
     * The relationship between the resolved dependency and the resolved mod version.
     *
     * @return BelongsTo<ModVersion, ModResolvedDependency>
     */
    public function resolvedModVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'resolved_mod_version_id');
    }
}
