<?php

namespace App\Models;

use App\Models\Scopes\DisabledScope;
use App\Models\Scopes\PublishedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $mod_id
 * @property string $version
 */
class ModVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new DisabledScope);
        static::addGlobalScope(new PublishedScope);
    }

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    /**
     * The relationship between a mod version and its dependencies.
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(ModDependency::class);
    }

    public function sptVersion(): BelongsTo
    {
        return $this->belongsTo(SptVersion::class);
    }
}
