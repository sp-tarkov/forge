<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Filters\V1\QueryFilter;
use App\Models\Scopes\PublishedScope;
use App\Traits\CanModerate;
use Database\Factories\ModFactory;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Override;

/**
 * Mod Model
 *
 * @property int $id
 * @property int|null $hub_id
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
 * @property-read License|null $license
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, ModVersion> $versions
 * @property-read ModVersion|null $latestVersion
 * @property-read ModVersion|null $latestUpdatedVersion
 */
class Mod extends Model
{
    use CanModerate;

    /** @use HasFactory<ModFactory> */
    use HasFactory;

    use Searchable;

    /**
     * Post boot method to configure the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::addGlobalScope(new PublishedScope);
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
     * The relationship between a mod and its users.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
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
            ->orderByDesc('version_labels')
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
            'thumbnail' => $this->thumbnail,
            'featured' => $this->featured,
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
            'published_at' => $this->published_at->timestamp,
            'latestVersion' => $this->latestVersion->latestSptVersion->version_formatted,
            'latestVersionColorClass' => $this->latestVersion->latestSptVersion->color_class,
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
        $this->load([
            'latestVersion',
            'latestVersion.latestSptVersion',
        ]);

        // Ensure the mod has a latest version.
        if ($this->latestVersion()->doesntExist()) {
            return false;
        }

        // Ensure the latest version has a latest SPT version.
        if ($this->latestVersion->latestSptVersion()->doesntExist()) {
            return false;
        }

        // Ensure the latest SPT version is within the last three minor versions.
        $activeSptVersions = Cache::remember('active-spt-versions', 60 * 60, fn (): Collection => SptVersion::getVersionsForLastThreeMinors());

        // All conditions are met; the mod should be searchable.
        return in_array($this->latestVersion->latestSptVersion->version, $activeSptVersions->pluck('version')->toArray());
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
    public function thumbnailUrl(): Attribute
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
            'production' => 'r2', // Cloudflare R2 Storage
            default => 'public', // Local
        };
    }

    /**
     * Scope a query by applying QueryFilter filters.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function scopeFilter(Builder $builder, QueryFilter $queryFilter): Builder
    {
        return $queryFilter->apply($builder);
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
            get: fn (string $value) => Str::lower($value),
            set: fn (string $value) => Str::slug($value),
        );
    }
}
