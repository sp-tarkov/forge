<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Trackable;
use App\Exceptions\InvalidVersionNumberException;
use App\Models\Scopes\PublishedScope;
use App\Models\Scopes\PublishedSptVersionScope;
use App\Observers\ModVersionObserver;
use App\Support\Version;
use Carbon\Carbon;
use Database\Factories\ModVersionFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Override;
use Shetabit\Visitor\Traits\Visitable;
use Stevebauman\Purify\Facades\Purify;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property int $mod_id
 * @property string $version
 * @property int $version_major
 * @property int $version_minor
 * @property int $version_patch
 * @property string $version_labels
 * @property string $description
 * @property string $link
 * @property int|null $content_length
 * @property string $spt_version_constraint
 * @property string $virus_total_link
 * @property int $downloads
 * @property bool $disabled
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $description_html
 * @property-read Mod $mod
 * @property-read Collection<int, ModDependency> $dependencies
 * @property-read Collection<int, ModVersion> $resolvedDependencies
 * @property-read Collection<int, ModVersion> $latestResolvedDependencies
 * @property-read SptVersion|null $latestSptVersion
 * @property-read Collection<int, SptVersion> $sptVersions
 */
#[ScopedBy([PublishedScope::class])]
#[ObservedBy([ModVersionObserver::class])]
class ModVersion extends Model implements Trackable
{
    /** @use HasFactory<ModVersionFactory> */
    use HasFactory;

    use Visitable;

    /**
     * Update the parent mod's updated_at timestamp when the mod version is updated.
     *
     * @var string[]
     */
    protected $touches = ['mod'];

    /**
     * Get all the version numbers for a mod.
     *
     * @cached 2h
     *
     * @return array<int, string>
     */
    public static function versionNumbers(int $modId): array
    {
        return Cache::flexible('mod_version_numbers_'.$modId, [5 * 60, 10 * 60], fn () => self::query()
            ->where('mod_id', $modId)
            ->where('version', '!=', '0.0.0')
            ->whereNotNull('version')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->pluck('version')
            ->all());
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
        return $this->hasMany(ModDependency::class);
    }

    /**
     * The relationship between a mod version and its resolved dependencies.
     *
     * @return BelongsToMany<ModVersion, $this>
     */
    public function resolvedDependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mod_resolved_dependencies', 'mod_version_id', 'resolved_mod_version_id')
            ->withPivot('dependency_id')
            ->withTimestamps();
    }

    /**
     * The relationship between a mod version and each of it's resolved dependencies' latest versions.
     *
     * @return BelongsToMany<ModVersion, $this>
     */
    public function latestResolvedDependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mod_resolved_dependencies', 'mod_version_id', 'resolved_mod_version_id')
            ->withPivot('dependency_id')
            ->join('mod_versions as latest_versions', function (JoinClause $join): void {
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
            ->orderByRaw('CASE WHEN spt_versions.version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('spt_versions.version_labels')
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
            ->withoutGlobalScope(PublishedSptVersionScope::class)
            ->withPivot('pinned_to_spt_publish')
            ->withTimestamps()
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels');
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
        DB::table('mod_versions')
            ->where('id', $this->id)
            ->increment('downloads');

        $this->refresh();

        $this->mod->calculateDownloads();

        return $this->downloads;
    }

    /**
     * Get the URL to view this trackable resource.
     */
    public function getTrackingUrl(): string
    {
        return route('mod.show', [$this->mod->id, $this->mod->slug]);
    }

    /**
     * Get the display title for this trackable resource.
     */
    public function getTrackingTitle(): string
    {
        return sprintf('%s v%s', $this->mod->name, $this->version);
    }

    /**
     * Get the snapshot data to store for this trackable resource.
     *
     * @return array<string, mixed>
     */
    public function getTrackingSnapshot(): array
    {
        return [
            'version_name' => $this->version,
            'mod_name' => $this->mod->name,
            'version_changelog' => $this->description,
        ];
    }

    /**
     * Get contextual information about this trackable resource.
     */
    public function getTrackingContext(): ?string
    {
        return sprintf('Version %s of %s', $this->version, $this->mod->name);
    }

    /**
     * Check if this mod version is publicly visible.
     *
     * A version is considered publicly visible if it's published, enabled, and has SPT compatibility tags. If the
     * version is pinned to an SPT version's publish date, it waits for that SPT version to be published.
     */
    public function isPubliclyVisible(): bool
    {
        if (is_null($this->published_at) || $this->disabled || is_null($this->latestSptVersion)) {
            return false;
        }

        if ($this->isPinnedToUnpublishedSptVersion()) {
            return false;
        }

        return true;
    }

    /**
     * Check if this mod version is pinned to an unpublished SPT version.
     */
    public function isPinnedToUnpublishedSptVersion(): bool
    {
        $this->loadMissing('sptVersions');

        foreach ($this->sptVersions as $sptVersion) {
            /** @phpstan-ignore property.notFound */
            $pivot = $sptVersion->pivot;
            if ($pivot->pinned_to_spt_publish) {
                if (! $sptVersion->is_published) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the latest unpublished SPT version publish date this mod is pinned to.
     * Returns the furthest date in the future to ensure the mod waits for ALL pinned SPT versions.
     */
    public function getLatestPinnedSptPublishDate(): ?Carbon
    {
        $this->loadMissing('sptVersions');

        $latestDate = null;

        foreach ($this->sptVersions as $sptVersion) {
            /** @phpstan-ignore property.notFound */
            $pivot = $sptVersion->pivot;
            if ($pivot->pinned_to_spt_publish && ! $sptVersion->is_published) {
                if (is_null($latestDate) || $sptVersion->publish_date > $latestDate) {
                    $latestDate = $sptVersion->publish_date;
                }
            }
        }

        return $latestDate;
    }

    /**
     * Post-boot method to configure the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::saving(function (ModVersion $modVersion): void {
            // Extract the version sections from the version string.
            try {
                $version = new Version($modVersion->version);

                $modVersion->version_major = $version->getMajor();
                $modVersion->version_minor = $version->getMinor();
                $modVersion->version_patch = $version->getPatch();
                $modVersion->version_labels = $version->getLabels();
            } catch (InvalidVersionNumberException) {
                $modVersion->version_major = 0;
                $modVersion->version_minor = 0;
                $modVersion->version_patch = 0;
                $modVersion->version_labels = '';
            }
        });
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
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
            'discord_notification_sent' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Generate the cleaned version of the HTML description.
     *
     * @return Attribute<string, never>
     */
    protected function descriptionHtml(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Purify::config('description')->clean(
                Markdown::convert($this->description)->getContent()
            )
        )->shouldCache();
    }

    /**
     * Get the formatted file size in MB.
     *
     * @return Attribute<string|null, never>
     */
    protected function formattedFileSize(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->content_length
                ? number_format($this->content_length / 1024 / 1024, 1).' MB'
                : null
        );
    }

    /**
     * Query scope for mod versions that are publicly visible.
     * These are versions that are published, enabled, and have SPT compatibility tags.
     *
     * @param  Builder<ModVersion>  $query
     * @return Builder<ModVersion>
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('disabled', false)
            ->whereHas('latestSptVersion');
    }
}
