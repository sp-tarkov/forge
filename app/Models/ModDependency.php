<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mod_version_id
 * @property int $dependency_mod_id
 * @property string $version_constraint
 * @property int|null $resolved_version_id
 */
class ModDependency extends Model
{
    use HasFactory;

    /**
     * The relationship between a mod dependency and mod version.
     */
    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class);
    }

    /**
     * The relationship between the mod dependency and the mod that is depended on.
     */
    public function dependencyMod(): BelongsTo
    {
        return $this->belongsTo(Mod::class, 'dependency_mod_id');
    }

    /**
     * The relationship between a mod dependency and resolved mod version.
     */
    public function resolvedVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'resolved_version_id');
    }
}
