<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\ModDependencyObserver;
use Carbon\Carbon;
use Database\Factories\ModDependencyFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ModDependency Model
 *
 * @property int $id
 * @property int $mod_version_id
 * @property int $dependent_mod_id
 * @property string $constraint
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ModVersion $modVersion
 * @property-read Mod $dependentMod
 * @property-read Collection<int, ModResolvedDependency> $resolvedDependencies
 */
#[ObservedBy([ModDependencyObserver::class])]
class ModDependency extends Model
{
    /** @use HasFactory<ModDependencyFactory> */
    use HasFactory;

    /**
     * The relationship between the mod dependency and the mod version.
     *
     * @return BelongsTo<ModVersion, $this>
     */
    public function modVersion(): BelongsTo
    {
        return $this->belongsTo(ModVersion::class);
    }

    /**
     * The relationship between the mod dependency and the resolved dependency.
     *
     * @return HasMany<ModResolvedDependency, $this>
     */
    public function resolvedDependencies(): HasMany
    {
        return $this->hasMany(ModResolvedDependency::class, 'dependency_id')->chaperone();
    }

    /**
     * The relationship between the mod dependency and the dependent mod.
     *
     * @return BelongsTo<Mod, $this>
     */
    public function dependentMod(): BelongsTo
    {
        return $this->belongsTo(Mod::class, 'dependent_mod_id');
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
