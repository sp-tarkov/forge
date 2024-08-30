<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModResolvedDependency extends Model
{
    /**
     * The relationship between the resolved dependency and the mod version.
     */
    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'mod_version_id');
    }

    /**
     * The relationship between the resolved dependency and the dependency.
     */
    public function dependency(): BelongsTo
    {
        return $this->belongsTo(ModDependency::class);
    }

    /**
     * The relationship between the resolved dependency and the resolved mod version.
     */
    public function resolvedModVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'resolved_mod_version_id');
    }
}
