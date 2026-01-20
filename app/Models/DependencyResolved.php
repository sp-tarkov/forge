<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\DependencyResolvedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $dependable_type
 * @property int $dependable_id
 * @property int $dependency_id
 * @property int $resolved_mod_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ModVersion|AddonVersion $dependable
 * @property-read Dependency|null $dependency
 * @property-read ModVersion|null $resolvedModVersion
 */
class DependencyResolved extends Model
{
    /** @use HasFactory<DependencyResolvedFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'dependencies_resolved';

    /**
     * The polymorphic relationship between the resolved dependency and the parent version.
     *
     * @return MorphTo<Model, $this>
     */
    public function dependable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Backward compatible alias for existing code that expects modVersion relationship.
     * Only works when dependable_type is ModVersion.
     *
     * @return BelongsTo<ModVersion, $this>
     */
    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class, 'dependable_id');
    }

    /**
     * The relationship between the resolved dependency and the (unresolved) dependency.
     *
     * @return BelongsTo<Dependency, $this>
     */
    public function dependency(): BelongsTo
    {
        return $this->belongsTo(Dependency::class);
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
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
