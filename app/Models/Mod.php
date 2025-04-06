<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\PublishedScope;
use App\Observers\ModObserver;
use Composer\Semver\Semver;
use Database\Factories\ModFactory;
use Exception;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use UnexpectedValueException;

/**
 * Mod Model
 *
 * @property int $id
 * @property int|null $hub_id
 * @property int|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string $teaser
 * @property string $description
 * @property string $thumbnail
 * @property int|null $license_id
 * @property int $downloads
 * @property string $source_code_link
 * @property bool $featured
 * @property bool $contains_ai_content
 * @property bool $contains_ads
 * @property bool $disabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $published_at
 * @property-read User|null $owner
 * @property-read License|null $license
 * @property-read Collection<int, User> $authors
 * @property-read Collection<int, ModVersion> $versions
 * @property-read ModVersion|null $latestVersion
 * @property-read ModVersion|null $latestUpdatedVersion
 */
#[ScopedBy([PublishedScope::class])]
#[ObservedBy([ModObserver::class])]
class Mod extends Model
{
    /** @use HasFactory<ModFactory> */
    use HasFactory;

    use Searchable;

    /**
     * The relationship between a mod and its owner (User).
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Calculate the total number of downloads for the mod.
     */
    public function calculateDownloads(): void
    {
        DB::table('mods')
            ->where('id', $this->id)
            ->update([
                'downloads' => DB::table('mod_versions')
                    ->where('mod_id', $this->id)
                    ->sum('downloads'),
            ]);

        $this->refresh();
    }

    /**
     * Build the URL to download the latest version of this mod.
     */
    public function downloadUrl(bool $absolute = false): string
    {
        $this->load('latestVersion');

        return route('mod.version.download', [
            $this->id,
            $this->slug,
            $this->latestVersion->version,
        ], absolute: $absolute);
    }

    /**
     * The relationship between a mod and its authors (Users).
     *
     * @return BelongsToMany<User, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mod_authors');
    }

    /**
     * The relationship between a mod and its license.
     *
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * The relationship between a mod and its last updated version.
     *
     * @return HasOne<ModVersion, $this>
     */
    public function latestUpdatedVersion(): HasOne
    {
        return $this->versions()
            ->one()
            ->ofMany('updated_at', 'max')
            ->chaperone();
    }

    /**
     * The relationship between a mod and its versions.
     *
     * @return HasMany<ModVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ModVersion::class)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderBy('version_labels')
            ->chaperone();
    }

    /**
     * The data that is searchable by Scout.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->load([
            'latestVersion',
            'latestVersion.latestSptVersion',
        ]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail' => $this->thumbnailUrl,
            'featured' => $this->featured,
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
            'published_at' => $this->published_at?->timestamp,
            'latestVersion' => $this->latestVersion?->latestSptVersion?->version_formatted,
            'latestVersionColorClass' => $this->latestVersion?->latestSptVersion?->color_class,
        ];
    }

    /**
     * Determine if the model instance should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        // Ensure the mod is not disabled.
        if ($this->disabled) {
            return false;
        }

        // Ensure the mod has a publish date.
        if (is_null($this->published_at)) {
            return false;
        }

        // Eager load the latest mod version, and it's latest SPT version.
        $this->loadMissing([
            'latestVersion',
            'latestVersion.latestSptVersion',
        ]);

        // Ensure the mod has a latest version.
        if (! $this->latestVersion) {
            return false;
        }

        // Ensure the latest version has a latest SPT version.
        if (! $this->latestVersion->latestSptVersion) {
            return false;
        }

        // Ensure the latest SPT version is within the last three minor versions.
        $activeSptVersions = Cache::remember('active_spt_versions_for_search', 60 * 60, fn (): Collection => SptVersion::getVersionsForLastThreeMinors());

        // All conditions are met; the mod should be searchable.
        return $activeSptVersions->contains('version', $this->latestVersion->latestSptVersion->version);
    }

    /**
     * The relationship between a mod and its latest version.
     *
     * @return HasOne<ModVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->versions()
            ->one()
            ->ofMany([
                'version_major' => 'max',
                'version_minor' => 'max',
                'version_patch' => 'max',
                'version_labels' => 'max',
            ])
            ->chaperone();
    }

    /**
     * Build the URL to the mod's thumbnail.
     *
     * @return Attribute<string, never>
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->thumbnail
            ? Storage::disk($this->thumbnailDisk())->url($this->thumbnail)
            : '');
    }

    /**
     * Get the disk where the thumbnail is stored based on the current environment.
     */
    protected function thumbnailDisk(): string
    {
        return match (config('app.env')) {
            'production' => config('filesystems.asset_upload_disk.production', 'r2'),
            'testing' => config('filesystems.asset_upload_disk.testing', 'public'),
            default => config('filesystems.asset_upload_disk.local', 'public'),
        };
    }

    /**
     * Build the URL to the mod's detail page.
     */
    public function detailUrl(): string
    {
        return route('mod.show', [$this->id, $this->slug]);
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'featured' => 'boolean',
            'contains_ai_content' => 'boolean',
            'contains_ads' => 'boolean',
            'disabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Mutate the slug attribute to always be lower case on get and slugified on set.
     *
     * @return Attribute<string, string>
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Str::lower($value) : '',
            set: fn (?string $value) => $value ? Str::slug($value) : '',
        );
    }

    /**
     * Scope a query to only include mods suitable for public API listing.
     *
     * @param  Builder<Mod>  $query
     */
    public function scopeApiQueryable(Builder $query): void
    {
        // Ensure the mod is not disabled.
        $query->where('mods.disabled', false);

        // Ensure the relationship exists AND the related version is not disabled.
        $query->whereHas('latestVersion', function (Builder $versionQuery): void {
            $versionQuery->where('disabled', false);
        });
    }

    /**
     * Scope a query to only include mods created between the given dates.
     *
     * @param  Builder<Mod>  $query
     * @param  string|array<int, string>  $dates
     */
    public function scopeCreatedAtBetween(Builder $query, string|array ...$dates): void
    {
        if (count($dates) === 2) {
            try {
                $start = Carbon::parse(Str::trim($dates[0]))->startOfDay();
                $end = Carbon::parse(Str::trim($dates[1]))->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
            } catch (Exception) {
                Log::debug('Invalid date format for created_at filter', ['dates' => $dates]);
            }
        }
    }

    /**
     * Scope a query to only include mods updated between the given dates.
     *
     * @param  Builder<Mod>  $query
     * @param  string|array<int, string>  $dates
     */
    public function scopeUpdatedAtBetween(Builder $query, string|array ...$dates): void
    {
        if (count($dates) === 2) {
            try {
                $start = Carbon::parse(Str::trim($dates[0]))->startOfDay();
                $end = Carbon::parse(Str::trim($dates[1]))->endOfDay();
                $query->whereBetween('updated_at', [$start, $end]);
            } catch (Exception) {
                Log::debug('Invalid date format for updated_at filter', ['dates' => $dates]);
            }
        }
    }

    /**
     * Scope a query to only include mods published between the given dates.
     *
     * @param  Builder<Mod>  $query
     * @param  string|array<int, string>  $dates
     */
    public function scopePublishedAtBetween(Builder $query, string|array ...$dates): void
    {
        if (count($dates) === 2) {
            try {
                $start = Carbon::parse(Str::trim($dates[0]))->startOfDay();
                $end = Carbon::parse(Str::trim($dates[1]))->endOfDay();
                $query->whereBetween('published_at', [$start, $end]);
            } catch (Exception) {
                Log::debug('Invalid date format for published_at filter', ['dates' => $dates]);
            }
        }
    }

    /**
     * Scope a query to only include mods that have at least one version associated with an SPT version satisfying the
     * given constraint string.
     *
     * @param  Builder<Mod>  $query
     * @param  string  $constraintString  The SemVer constraint string (e.g., "^3.8.0")
     */
    public function scopeSptVersion(Builder $query, string $constraintString): void
    {
        $availableSptVersions = SptVersion::allValidVersions();

        try {
            $satisfyingVersionStrings = Semver::satisfiedBy($availableSptVersions, $constraintString);
        } catch (UnexpectedValueException $unexpectedValueException) {
            $satisfyingVersionStrings = [];
            Log::debug('scopeForSptVersion: Invalid constraint string provided to Semver.', ['constraint' => $constraintString, 'error' => $unexpectedValueException->getMessage()]);
        }

        if (empty($satisfyingVersionStrings)) {
            // If no versions satisfy the constraint, ensure no mods are returned by this filter.
            $query->whereHas('versions', fn ($q) => $q->whereRaw('1 = 0'));

            return;
        }

        // Filter mods where at least one mod version has an associated spt version whose version string is IN the list
        // of satisfying versions.
        $query->whereHas('versions', function (Builder $versionQuery) use ($satisfyingVersionStrings): void {
            $versionQuery->whereHas('sptVersions', function (Builder $sptQuery) use ($satisfyingVersionStrings): void {
                $sptQuery->whereIn('spt_versions.version', $satisfyingVersionStrings);
            });
        });
    }
}
