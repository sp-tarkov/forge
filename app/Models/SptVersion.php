<?php

namespace App\Models;

use App\Exceptions\InvalidVersionNumberException;
use Database\Factories\SptVersionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class SptVersion extends Model
{
    /** @use HasFactory<SptVersionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Get all versions for the last three minor versions.
     *
     * @return Collection<int, SptVersion>
     */
    public static function getVersionsForLastThreeMinors(): Collection
    {
        $lastThreeMinorVersions = self::getLastThreeMinorVersions();

        // Extract major and minor arrays.
        $majorVersions = array_column($lastThreeMinorVersions, 'major');
        $minorVersions = array_column($lastThreeMinorVersions, 'minor');

        // Fetch all versions for the last three minor versions with mod count.
        return self::select(['spt_versions.id', 'spt_versions.version', 'spt_versions.color_class', 'spt_versions.mod_count'])
            ->join('mod_version_spt_version', 'spt_versions.id', '=', 'mod_version_spt_version.spt_version_id')
            ->join('mod_versions', 'mod_version_spt_version.mod_version_id', '=', 'mod_versions.id')
            ->join('mods', 'mod_versions.mod_id', '=', 'mods.id')
            ->where('spt_versions.version', '!=', '0.0.0')
            ->whereIn('spt_versions.version_major', $majorVersions)
            ->whereIn('spt_versions.version_minor', $minorVersions)
            ->where('spt_versions.mod_count', '>', 0)
            ->groupBy('spt_versions.id', 'spt_versions.version', 'spt_versions.color_class', 'spt_versions.mod_count')
            ->orderBy('spt_versions.version_major', 'DESC')
            ->orderBy('spt_versions.version_minor', 'DESC')
            ->orderBy('spt_versions.version_patch', 'DESC')
            ->orderBy('spt_versions.version_pre_release', 'ASC')
            ->get();
    }

    /**
     * Get the last three minor versions (major.minor format).
     *
     * @return array<int, array{major: int, minor: int}>
     */
    public static function getLastThreeMinorVersions(): array
    {
        return self::selectRaw('CONCAT(version_major, ".", version_minor) AS minor_version, version_major, version_minor')
            ->where('version', '!=', '0.0.0')
            ->groupBy('version_major', 'version_minor')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->limit(3)
            ->get()
            ->map(function (SptVersion $version) {
                return [
                    'major' => (int) $version->version_major,
                    'minor' => (int) $version->version_minor,
                ];
            })
            ->toArray();
    }

    /**
     * Called when the model is booted.
     */
    protected static function booted(): void
    {
        // Callback that runs before saving the model.
        static::saving(function (SptVersion $model) {
            // Extract the version sections from the version string.
            if (! empty($model->version)) {
                // Default values in case there's an exception.
                $model->version_major = 0;
                $model->version_minor = 0;
                $model->version_patch = 0;
                $model->version_pre_release = '';

                try {
                    $versionSections = self::extractVersionSections($model->version);
                } catch (InvalidVersionNumberException $e) {
                    return;
                }

                $model->version_major = $versionSections['major'];
                $model->version_minor = $versionSections['minor'];
                $model->version_patch = $versionSections['patch'];
                $model->version_pre_release = $versionSections['pre_release'];
            }
        });
    }

    /**
     * Extract the version sections from the version string.
     *
     * @return array{major: int, minor: int, patch: int, pre_release: string}
     *
     * @throws InvalidVersionNumberException
     */
    public static function extractVersionSections(string $version): array
    {
        $matches = [];

        // Perform the regex match to capture the version sections, including the possible preRelease section.
        preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-([a-zA-Z0-9]+))?$/', $version, $matches);

        if (! $matches) {
            throw new InvalidVersionNumberException('Invalid SPT version number: '.$version);
        }

        return [
            'major' => $matches[1] ?? 0,
            'minor' => $matches[2] ?? 0,
            'patch' => $matches[3] ?? 0,
            'pre_release' => $matches[4] ?? '',
        ];
    }

    /**
     * Update the mod count for this SptVersion.
     */
    public function updateModCount(): void
    {
        $modCount = $this->modVersions()
            ->distinct('mod_id')
            ->count('mod_id');

        $this->mod_count = $modCount;
        $this->saveQuietly();
    }

    /**
     * The relationship between an SPT version and mod version.
     *
     * @return BelongsToMany<ModVersion>
     */
    public function modVersions(): BelongsToMany
    {
        return $this->belongsToMany(ModVersion::class, 'mod_version_spt_version');
    }

    /**
     * Get the version with "SPT " prepended.
     */
    public function getVersionFormattedAttribute(): string
    {
        return __('SPT ').$this->version;
    }

    /**
     * Determine if the version is part of the latest version's minor releases.
     * For example, if the latest version is 1.2.0, this method will return true for 1.2.0, 1.2.1, 1.2.2, etc.
     */
    public function isLatestMinor(): bool
    {
        $latestVersion = self::getLatest();

        if (! $latestVersion) {
            return false;
        }

        return $this->version_major == $latestVersion->version_major && $this->version_minor == $latestVersion->version_minor;
    }

    /**
     * Get the latest SPT version.
     *
     * @cached latest_spt_version 300s
     */
    public static function getLatest(): ?SptVersion
    {
        return Cache::remember('latest_spt_version', 300, function () {
            return SptVersion::select(['version', 'version_major', 'version_minor', 'version_patch', 'version_pre_release'])
                ->orderByDesc('version')
                ->first();
        });
    }
}
