<?php

namespace App\Models;

use App\Http\Filters\V1\QueryFilter;
use App\Models\Scopes\DisabledScope;
use App\Models\Scopes\PublishedScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Mod extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    /**
     * Post boot method to configure the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new DisabledScope);
        static::addGlobalScope(new PublishedScope);
    }

    /**
     * Calculate the total number of downloads for the mod.
     */
    public function calculateDownloads(): void
    {
        $this->downloads = $this->versions->sum('downloads');
        $this->saveQuietly();
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
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * The relationship between a mod and its license.
     *
     * @return BelongsTo<License, Mod>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * The relationship between a mod and its last updated version.
     *
     * @return HasOne<ModVersion>
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
     * @return HasMany<ModVersion>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ModVersion::class)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByDesc('version_pre_release')
            ->chaperone();
    }

    /**
     * The data that is searchable by Scout.
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
            'created_at' => strtotime($this->created_at),
            'updated_at' => strtotime($this->updated_at),
            'published_at' => strtotime($this->published_at),
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
        $activeSptVersions = Cache::remember('active-spt-versions', 60 * 60, function () {
            return SptVersion::getVersionsForLastThreeMinors();
        });
        if (! in_array($this->latestVersion->latestSptVersion->version, $activeSptVersions->pluck('version')->toArray())) {
            return false;
        }

        // All conditions are met; the mod should be searchable.
        return true;
    }

    /**
     * The relationship between a mod and its latest version.
     *
     * @return HasOne<ModVersion>
     */
    public function latestVersion(): HasOne
    {
        return $this->versions()
            ->one()
            ->ofMany([
                'version_major' => 'max',
                'version_minor' => 'max',
                'version_patch' => 'max',
                'version_pre_release' => 'max',
            ])
            ->chaperone();
    }

    /**
     * Build the URL to the mod's thumbnail.
     */
    public function thumbnailUrl(): Attribute
    {
        return Attribute::get(function (): string {
            return $this->thumbnail
                ? Storage::disk($this->thumbnailDisk())->url($this->thumbnail)
                : '';
        });
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
     */
    public function scopeFilter(Builder $builder, QueryFilter $filters): Builder
    {
        return $filters->apply($builder);
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
            'deleted_at' => 'datetime',
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
