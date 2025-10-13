<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidVersionNumberException;
use App\Models\Scopes\PublishedSptVersionScope;
use App\Observers\SptVersionObserver;
use App\Support\Version;
use Carbon\Carbon;
use Database\Factories\SptVersionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Override;
use Throwable;

/**
 * @property int $id
 * @property string $version
 * @property int $version_major
 * @property int $version_minor
 * @property int $version_patch
 * @property string $version_labels
 * @property int $mod_count
 * @property string $link
 * @property string $color_class
 * @property Carbon|null $publish_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, ModVersion> $modVersions
 * @property-read string $version_formatted
 * @property-read bool $is_published
 */
#[ScopedBy([PublishedSptVersionScope::class])]
#[ObservedBy([SptVersionObserver::class])]
class SptVersion extends Model
{
    /** @use HasFactory<SptVersionFactory> */
    use HasFactory;

    /**
     * Get all versions for the last three minor versions.
     *
     * @param  bool|null  $includeUnpublished  If true, includes unpublished versions. If null, checks current user's permissions.
     * @return Collection<int, self>
     */
    public static function getVersionsForLastThreeMinors(?bool $includeUnpublished = null): Collection
    {
        $includeUnpublished ??= auth()->user()?->isModOrAdmin() ?? false;

        // Get the last three minor versions
        $lastThreeMinorVersions = self::getLastThreeMinorVersions($includeUnpublished);

        // Build the query
        return self::query()
            ->select(['spt_versions.id', 'spt_versions.version', 'spt_versions.color_class', 'spt_versions.mod_count', 'spt_versions.publish_date'])
            ->when($includeUnpublished, fn (Builder $query) => $query->withoutGlobalScope(PublishedSptVersionScope::class))
            ->where('spt_versions.version', '!=', '0.0.0')
            ->where(function (Builder $query) use ($lastThreeMinorVersions): void {
                foreach ($lastThreeMinorVersions as $minorVersion) {
                    $query->orWhere(function (Builder $subQuery) use ($minorVersion): void {
                        $subQuery->where('spt_versions.version_major', $minorVersion['major'])
                            ->where('spt_versions.version_minor', $minorVersion['minor']);
                    });
                }
            })
            ->groupBy('spt_versions.id', 'spt_versions.version', 'spt_versions.color_class', 'spt_versions.mod_count', 'spt_versions.publish_date')
            ->orderBy('spt_versions.version_major', 'DESC')
            ->orderBy('spt_versions.version_minor', 'DESC')
            ->orderBy('spt_versions.version_patch', 'DESC')
            ->orderByRaw('CASE WHEN spt_versions.version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('spt_versions.version_labels', 'ASC')
            ->get();
    }

    /**
     * Get the last three minor versions (major.minor format).
     *
     * @param  bool|null  $includeUnpublished  If true, includes unpublished versions. If null, checks current user's permissions.
     * @return array<int, array{major: int, minor: int}>
     */
    public static function getLastThreeMinorVersions(?bool $includeUnpublished = null): array
    {
        // Determine whether to include unpublished versions
        $includeUnpublished ??= auth()->user()?->isModOrAdmin() ?? false;

        return self::query()
            ->selectRaw('CONCAT(version_major, ".", version_minor) AS minor_version, version_major, version_minor')
            ->when($includeUnpublished, fn (Builder $query) => $query->withoutGlobalScope(PublishedSptVersionScope::class))
            ->where('version', '!=', '0.0.0')
            ->groupBy('version_major', 'version_minor')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->limit(3)
            ->get()
            ->map(fn (SptVersion $sptVersion): array => [
                'major' => (int) $sptVersion->version_major,
                'minor' => (int) $sptVersion->version_minor,
            ])
            ->toArray();
    }

    /**
     * Extract the version sections from the version string.
     *
     * @return array{major: int, minor: int, patch: int, labels: string}
     *
     * @throws InvalidVersionNumberException|Throwable
     */
    public static function extractVersionSections(string $version): array
    {
        $matches = [];

        // Perform the regex match to capture the version sections, including the possible preRelease section.
        preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-([a-zA-Z0-9]+))?$/', $version, $matches);

        throw_if($matches === [], InvalidVersionNumberException::class, 'Invalid SPT version number: '.$version);

        return [
            'major' => $matches[1] ?? 0,
            'minor' => $matches[2] ?? 0,
            'patch' => $matches[3] ?? 0,
            'labels' => $matches[4] ?? '',
        ];
    }

    /**
     * Get the latest SPT version.
     */
    public static function getLatest(): ?self
    {
        return self::query()
            ->select(['version', 'version_major', 'version_minor', 'version_patch', 'version_labels'])
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->first();
    }

    /**
     * Get all the minor/patch versions of the latest minor release.
     *
     * @param  bool|null  $includeUnpublished  If true, includes unpublished versions. If null, checks current user's permissions.
     * @return Collection<int, self>
     */
    public static function getLatestMinorVersions(?bool $includeUnpublished = null): Collection
    {
        // Determine whether to include unpublished versions
        $includeUnpublished ??= auth()->user()?->isModOrAdmin() ?? false;

        // Get the absolute latest version to determine the latest minor release
        $latestVersion = self::getLatest();
        if ($latestVersion === null) {
            return new Collection;
        }

        // Build the query
        return self::query()
            ->when($includeUnpublished, fn (Builder $query) => $query->withoutGlobalScope(PublishedSptVersionScope::class))
            ->where('version_major', $latestVersion->version_major)
            ->where('version_minor', $latestVersion->version_minor)
            ->where('version', '!=', '0.0.0')
            ->orderBy('version_patch', 'desc')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->get();
    }

    /**
     * Get all the valid SPT versions.
     *
     * @cached 1h with 5min grace period
     *
     * @param  bool  $includeUnpublished  If true, includes unpublished versions (bypasses global scope).
     * @return array<int, string>
     */
    public static function allValidVersions(bool $includeUnpublished = false): array
    {
        $cacheKey = $includeUnpublished ? 'spt-versions:all:authors' : 'spt-versions:all:user';

        return Cache::flexible($cacheKey, [5 * 60, 60 * 60], fn () => self::query()
            ->when($includeUnpublished, fn (Builder $query) => $query->withoutGlobalScope(PublishedSptVersionScope::class))
            ->where('version', '!=', '0.0.0')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->pluck('version')
            ->all());
    }

    /**
     * Update the mod count for this SptVersion.
     */
    public function updateModCount(): void
    {
        self::query()
            ->withoutGlobalScope(PublishedSptVersionScope::class)
            ->where('id', $this->id)
            ->update([
                'mod_count' => $this->modVersions()
                    ->distinct('mod_id')
                    ->count('mod_id'),
            ]);
    }

    /**
     * The relationship between an SPT version and mod version.
     *
     * @return BelongsToMany<ModVersion, $this>
     */
    public function modVersions(): BelongsToMany
    {
        return $this->belongsToMany(ModVersion::class)
            ->using(ModVersionSptVersion::class);
    }

    /**
     * Determine if the version is part of the latest version's minor releases. For example, if the latest version is
     * 1.2.0, this method will return true for 1.2.0, 1.2.1, 1.2.2, etc.
     */
    public function isLatestMinor(): bool
    {
        $latestVersion = self::getLatest();

        if (! $latestVersion instanceof self) {
            return false;
        }

        return $this->version_major === $latestVersion->version_major && $this->version_minor === $latestVersion->version_minor;
    }

    /**
     * Called when the model is booted.
     */
    #[Override]
    protected static function booted(): void
    {
        static::saving(function (SptVersion $sptVersion): void {
            // Extract the version sections from the version string.
            try {
                $version = new Version($sptVersion->version);

                $sptVersion->version_major = $version->getMajor();
                $sptVersion->version_minor = $version->getMinor();
                $sptVersion->version_patch = $version->getPatch();
                $sptVersion->version_labels = $version->getLabels();
            } catch (InvalidVersionNumberException $invalidVersionNumberException) {
                Log::warning('Invalid SPT version number: '.$invalidVersionNumberException->getMessage());

                $sptVersion->version_major = 0;
                $sptVersion->version_minor = 0;
                $sptVersion->version_patch = 0;
                $sptVersion->version_labels = '';
            }
        });
    }

    /**
     * Get the version with "SPT " prepended.
     *
     * @return Attribute<string, string>
     */
    protected function versionFormatted(): Attribute
    {
        return Attribute::make(
            get: fn (): string => 'SPT '.$this->version,
            set: fn (string $value): string => Str::after($value, 'SPT '),
        );
    }

    /**
     * Get whether the SPT version is published.
     *
     * @return Attribute<bool, never>
     */
    protected function isPublished(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => ! is_null($this->publish_date) && $this->publish_date->lte(now()),
        );
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
            'mod_count' => 'integer',
            'publish_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
