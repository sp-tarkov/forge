<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidVersionNumberException;
use App\Models\Scopes\DisabledScope;
use App\Models\Scopes\PublishedScope;
use App\Support\Version;
use Database\Factories\ModVersionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Override;

/**
 * ModVersion Model
 *
 * @property int $id
 * @property int|null $hub_id
 * @property int $mod_id
 * @property string $version
 * @property int $version_major
 * @property int $version_minor
 * @property int $version_patch
 * @property string $version_pre_release
 * @property string $description
 * @property string $link
 * @property string $spt_version_constraint
 * @property string $virus_total_link
 * @property int $downloads
 * @property bool $disabled
 * @property Carbon|null $deleted_at
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Mod $mod
 * @property-read Collection<int, ModDependency> $dependencies
 * @property-read Collection<int, ModVersion> $resolvedDependencies
 * @property-read Collection<int, ModVersion> $latestResolvedDependencies
 * @property-read SptVersion|null $latestSptVersion
 * @property-read Collection<int, SptVersion> $sptVersions
 */
class ModVersion extends Model
{
    /** @use HasFactory<ModVersionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Update the parent mod's updated_at timestamp when the mod version is updated.
     *
     * @var string[]
     */
    protected $touches = ['mod'];

    /**
     * Post boot method to configure the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::addGlobalScope(new DisabledScope);

        static::addGlobalScope(new PublishedScope);

        static::saving(function (ModVersion $modVersion): void {
            // Extract the version sections from the version string.
            try {
                $version = new Version($modVersion->version);

                $modVersion->version_major = $version->getMajor();
                $modVersion->version_minor = $version->getMinor();
                $modVersion->version_patch = $version->getPatch();
                $modVersion->version_pre_release = $version->getPreRelease();
            } catch (InvalidVersionNumberException) {
                $modVersion->version_major = 0;
                $modVersion->version_minor = 0;
                $modVersion->version_patch = 0;
                $modVersion->version_pre_release = '';
            }
        });
    }

    /**
     * The relationship between a mod version and mod.
     *
     * @return BelongsTo<Mod, $this>
     */
    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    /**
     * The relationship between a mod version and its dependencies.
     *
     * @return HasMany<ModDependency, $this>
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(ModDependency::class)
            ->chaperone();
    }

    /**
     * The relationship between a mod version and its resolved dependencies.
     *
     * @return BelongsToMany<ModVersion, $this>
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
     * @return BelongsToMany<ModVersion, $this>
     */
    public function latestResolvedDependencies(): BelongsToMany
    {
        return $this->belongsToMany(ModVersion::class, 'mod_resolved_dependencies', 'mod_version_id', 'resolved_mod_version_id')
            ->withPivot('dependency_id')
            ->join('mod_versions as latest_versions', function ($join): void {
                $join->on('latest_versions.id', '=', 'mod_versions.id')
                    ->whereRaw('latest_versions.version = (SELECT MAX(mv.version) FROM mod_versions mv WHERE mv.mod_id = mod_versions.mod_id)');
            })
            ->with('mod:id,name,slug')
            ->withTimestamps();
    }

    /**
     * The relationship between a mod version and its latest SPT version.
     *
     * @return HasOneThrough<SptVersion, ModVersionSptVersion, $this>
     */
    public function latestSptVersion(): HasOneThrough
    {
        return $this->hasOneThrough(SptVersion::class, ModVersionSptVersion::class, 'mod_version_id', 'id', 'id', 'spt_version_id')
            ->orderByDesc('spt_versions.version_major')
            ->orderByDesc('spt_versions.version_minor')
            ->orderByDesc('spt_versions.version_patch')
            ->orderByDesc('spt_versions.version_pre_release')
            ->limit(1);
    }

    /**
     * The relationship between a mod version and its SPT versions.
     *
     * @return BelongsToMany<SptVersion, $this>
     */
    public function sptVersions(): BelongsToMany
    {
        return $this->belongsToMany(SptVersion::class)
            ->using(ModVersionSptVersion::class)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByDesc('version_pre_release');
    }

    /**
     * Build the download URL for this mod version.
     */
    public function downloadUrl(bool $absolute = false): string
    {
        return route('mod.version.download', [$this->mod->id, $this->mod->slug, $this->version], absolute: $absolute);
    }

    /**
     * Increment the download count for this mod version.
     */
    public function incrementDownloads(): int
    {
        $this->downloads++;
        $this->save();

        // Recalculate the total download count for this mod.
        $this->mod->calculateDownloads();

        return $this->downloads;
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'hub_id' => 'integer',
            'version_major' => 'integer',
            'version_minor' => 'integer',
            'version_patch' => 'integer',
            'downloads' => 'integer',
            'disabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
