<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\PublishedScope;
use Database\Factories\ModAddonFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * Mod Addon
 *
 * @property int $id
 * @property int $mod_addon_id
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
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $detail_url
 * @property-read User|null $owner
 * @property-read License|null $license
 * @property-read Collection<int, User> $authors
 * @property-read Collection<int, ModVersion> $versions
 * @property-read ModVersion|null $latestVersion
 * @property-read ModVersion|null $latestUpdatedVersion
 */
#[ScopedBy([PublishedScope::class])]
class ModAddon extends Model
{
    /** @use HasFactory<ModAddonFactory> */
    use HasFactory;

    use Searchable;

    /**
     * The relationship between a mod addon and its owner (User).
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Calculate the total number of downloads for the mod addon.
     */
    public function calculateDownloads(): void
    {
        DB::table('mod_addons')
            ->where('id', $this->id)
            ->update([
                'downloads' => DB::table('mod_addon_versions')
                    ->where('mod_addon_id', $this->id)
                    ->sum('downloads'),
            ]);

        $this->refresh();
    }

    /**
     * Build the URL to download the latest version of this mod addon.
     */
    public function downloadUrl(bool $absolute = false): string
    {
        $this->load('latestVersion');

        return route('mod.addon.version.download', [
            $this->id,
            $this->slug,
            $this->latestVersion->version,
        ], absolute: $absolute);
    }

    /**
     * The relationship between a mod addon and its authors (Users).
     *
     * @return BelongsToMany<User, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mod_addon_authors');
    }

    /**
     * The relationship between a mod addon and its license.
     *
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * The relationship between a mod addon and its last updated version.
     *
     * @return HasOne<ModAddonVersion, $this>
     */
    public function latestUpdatedVersion(): HasOne
    {
        return $this->versions()
            ->one()
            ->ofMany('updated_at', 'max');
    }

    /**
     * The relationship between a mod addon and its versions.
     *
     * @return HasMany<ModAddonVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ModAddonVersion::class)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels');
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

        // Eager load the latest mod addon version, and it's latest SPT version.
        $this->loadMissing([
            'latestVersion',
            'latestVersion.latestSptVersion',
        ]);

        // Ensure the mod addon has a latest version.
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
     * The relationship between a mod addon and its latest version.
     *
     * @return HasOne<ModAddonVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->versions()
            ->one()
            ->ofMany([
                'version_major' => 'max',
                'version_minor' => 'max',
                'version_patch' => 'max',
                'version_labels' => 'min',
            ]);
    }

    /**
     * Build the URL to the mod addon's thumbnail.
     *
     * @return Attribute<string, never>
     */
    protected function thumbnailUrl(): Attribute
    {
        $disk = config('filesystems.asset_upload', 'public');

        return Attribute::get(fn (): string => $this->thumbnail
            ? Storage::disk($disk)->url($this->thumbnail)
            : '');
    }

    /**
     * Get the URL to the mod addon's detail page.
     *
     * @return Attribute<string, string>
     */
    protected function detailUrl(): Attribute
    {
        return Attribute::get(fn () => route('mod.addon.show', [$this->id, $this->slug]));
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
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
}
