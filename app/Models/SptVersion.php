<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidVersionNumberException;
use App\Support\Version;
use Carbon\Carbon;
use Database\Factories\SptVersionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Override;
use Throwable;

/**
 * SptVersion Model
 *
 * @property int $id
 * @property int|null $hub_id
 * @property string $version
 * @property int $version_major
 * @property int $version_minor
 * @property int $version_patch
 * @property string $version_pre_release
 * @property int $mod_count
 * @property string $link
 * @property string $color_class
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, ModVersion> $modVersions
 * @property-read string $version_formatted
 */
class SptVersion extends Model
{
    /** @use HasFactory<SptVersionFactory> */
    use HasFactory;

    /**
     * Get all versions for the last three minor versions.
     *
     * @return Collection<int, $this>
     */
    public static function getVersionsForLastThreeMinors(): Collection
    {
        $lastThreeMinorVersions = self::getLastThreeMinorVersions();

        // Extract major and minor arrays.
        $majorVersions = array_column($lastThreeMinorVersions, 'major');
        $minorVersions = array_column($lastThreeMinorVersions, 'minor');

        // Fetch all versions for the last three minor versions with mod count.
        return self::query()->select(['spt_versions.id', 'spt_versions.version', 'spt_versions.color_class', 'spt_versions.mod_count'])
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
        return self::query()
            ->selectRaw('CONCAT(version_major, ".", version_minor) AS minor_version, version_major, version_minor')
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
     * @return array{major: int, minor: int, patch: int, pre_release: string}
     *
     * @throws InvalidVersionNumberException|Throwable
     */
    public static function extractVersionSections(string $version): array
    {
        $matches = [];

        // Perform the regex match to capture the version sections, including the possible preRelease section.
        preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-([a-zA-Z0-9]+))?$/', $version, $matches);

        throw_if($matches === [], new InvalidVersionNumberException('Invalid SPT version number: '.$version));

        return [
            'major' => $matches[1] ?? 0,
            'minor' => $matches[2] ?? 0,
            'patch' => $matches[3] ?? 0,
            'pre_release' => $matches[4] ?? '',
        ];
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
                $sptVersion->version_pre_release = $version->getPreRelease();
            } catch (InvalidVersionNumberException) {
                $sptVersion->version_major = 0;
                $sptVersion->version_minor = 0;
                $sptVersion->version_patch = 0;
                $sptVersion->version_pre_release = '';
            }
        });
    }

    /**
     * Update the mod count for this SptVersion.
     */
    public function updateModCount(): void
    {
        DB::table('spt_versions')
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
     * Get the version with "SPT " prepended.
     */
    public function getVersionFormattedAttribute(): string
    {
        return __('SPT ').$this->version;
    }

    /**
     * Determine if the version is part of the latest version's minor releases. For example, if the latest version is
     * 1.2.0, this method will return true for 1.2.0, 1.2.1, 1.2.2, etc.
     */
    public function isLatestMinor(): bool
    {
        $latestVersion = self::getLatest();

        if (! $latestVersion instanceof \App\Models\SptVersion) {
            return false;
        }

        return $this->version_major == $latestVersion->version_major && $this->version_minor == $latestVersion->version_minor;
    }

    /**
     * Get the latest SPT version.
     */
    public static function getLatest(): ?SptVersion
    {
        return self::query()
            ->select(['version', 'version_major', 'version_minor', 'version_patch', 'version_pre_release'])
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->first();
    }

    /**
     * Get all the minor/patch versions of the latest major version.
     *
     * @return Collection<int, $this>
     */
    public static function getLatestMinorVersions(): Collection
    {
        $latestMajor = self::getLatest();
        if ($latestMajor === null) {
            return new Collection;
        }

        return self::query()
            ->where('version_major', $latestMajor->version_major)
            ->where('version_minor', $latestMajor->version_minor)
            ->orderBy('version_patch', 'desc')
            ->get();
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
            'mod_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
