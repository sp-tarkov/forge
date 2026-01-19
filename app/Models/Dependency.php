<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\DependencyObserver;
use Carbon\Carbon;
use Database\Factories\DependencyFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $dependable_type
 * @property int $dependable_id
 * @property int $dependent_mod_id
 * @property string $constraint
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ModVersion|AddonVersion $dependable
 * @property-read Mod $dependentMod
 * @property-read Collection<int, ResolvedDependency> $resolvedDependencies
 */
#[ObservedBy([DependencyObserver::class])]
class Dependency extends Model
{
    /** @use HasFactory<DependencyFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'dependencies';

    /**
     * The polymorphic relationship between the dependency and either a mod version or addon version.
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
     * The relationship between the dependency and the resolved dependencies.
     *
     * @return HasMany<ResolvedDependency, $this>
     */
    public function resolvedDependencies(): HasMany
    {
        return $this->hasMany(ResolvedDependency::class, 'dependency_id');
    }

    /**
     * The relationship between the dependency and the dependent mod.
     *
     * @return BelongsTo<Mod, $this>
     */
    public function dependentMod(): BelongsTo
    {
        return $this->belongsTo(Mod::class, 'dependent_mod_id');
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
