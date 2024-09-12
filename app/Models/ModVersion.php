<?php

namespace App\Models;

use App\Models\Scopes\DisabledScope;
use App\Models\Scopes\PublishedScope;
use Database\Factories\ModFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModVersion extends Model
{
    /** @use HasFactory<ModFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Post boot method to configure the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new DisabledScope);
        static::addGlobalScope(new PublishedScope);
    }

    /**
     * The relationship between a mod version and mod.
     *
     * @return BelongsTo<Mod, ModVersion>
     */
    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    /**
     * The relationship between a mod version and its dependencies.
     *
     * @return HasMany<ModDependency>
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(ModDependency::class)
            ->chaperone();
    }

    /**
     * The relationship between a mod version and its resolved dependencies.
     *
     * @return BelongsToMany<ModVersion>
     */
    public function resolvedDependencies(): BelongsToMany
    {
        return $this->belongsToMany(ModVersion::class, 'mod_resolved_dependencies', 'mod_version_id', 'resolved_mod_version_id')
            ->withPivot('dependency_id')
            ->withTimestamps();
    }

    /**
     * The relationship between a mod version and its each of it's resolved dependencies' latest versions.
     *
     * @return BelongsToMany<ModVersion>
     */
    public function latestResolvedDependencies(): BelongsToMany
    {
        return $this->belongsToMany(ModVersion::class, 'mod_resolved_dependencies', 'mod_version_id', 'resolved_mod_version_id')
            ->withPivot('dependency_id')
            ->join('mod_versions as latest_versions', function ($join) {
                $join->on('latest_versions.id', '=', 'mod_versions.id')
                    ->whereRaw('latest_versions.version = (SELECT MAX(mv.version) FROM mod_versions mv WHERE mv.mod_id = mod_versions.mod_id)');
            })
            ->with('mod:id,name,slug')
            ->withTimestamps();
    }

    /**
     * The relationship between a mod version and each of its SPT versions' latest version.
     * Hint: Be sure to call `->first()` on this to get the actual instance.
     *
     * @return BelongsToMany<SptVersion>
     */
    public function latestSptVersion(): BelongsToMany
    {
        return $this->belongsToMany(SptVersion::class, 'mod_version_spt_version')
            ->orderBy('version', 'desc')
            ->limit(1);
    }

    /**
     * The relationship between a mod version and its SPT versions.
     *
     * @return BelongsToMany<SptVersion>
     */
    public function sptVersions(): BelongsToMany
    {
        return $this->belongsToMany(SptVersion::class, 'mod_version_spt_version')
            ->orderByDesc('version');
    }
}
