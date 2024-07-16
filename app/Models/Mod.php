<?php

namespace App\Models;

use App\Models\Scopes\DisabledScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * @property string $slug
 */
class Mod extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected static function booted(): void
    {
        // Apply the global scope to exclude disabled mods.
        static::addGlobalScope(new DisabledScope);
    }

    /**
     * The users that belong to the mod.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ModVersion::class);
    }

    /**
     * Scope a query to include the total number of downloads for a mod.
     */
    public function scopeWithTotalDownloads($query)
    {
        return $query->addSelect([
            'total_downloads' => ModVersion::selectRaw('SUM(downloads) AS total_downloads')
                ->whereColumn('mod_id', 'mods.id'),
        ]);
    }

    public function lastUpdatedVersion(): HasOne
    {
        return $this->hasOne(ModVersion::class)->orderByDesc('updated_at')->with('sptVersion');
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        $latestSptVersion = $this->latestSptVersion()->first();

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'featured' => $this->featured,
            'created_at' => strtotime($this->created_at),
            'updated_at' => strtotime($this->updated_at),
            'latestSptVersion' => $latestSptVersion?->sptVersion->version,
            'latestSptVersionColorClass' => $latestSptVersion?->sptVersion->color_class,
        ];
    }

    public function latestSptVersion(): HasOne
    {
        return $this->hasOne(ModVersion::class)
            ->orderByDesc(
                SptVersion::select('version')
                    ->whereColumn('mod_versions.spt_version_id', 'spt_versions.id')
                    ->orderByDesc('version')
                    ->take(1),
            )
            ->with('sptVersion')
            ->orderByDesc('version')
            ->take(1);
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return ! $this->disabled;
    }

    /**
     * Get the URL to the thumbnail.
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
     * Get the disk where the thumbnail is stored.
     */
    protected function thumbnailDisk(): string
    {
        return match (config('app.env')) {
            'production' => 'r2', // Cloudflare R2 Storage
            default => 'public', // Local
        };
    }

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'contains_ai_content' => 'boolean',
            'contains_ads' => 'boolean',
            'disabled' => 'boolean',
        ];
    }

    /**
     * Ensure the slug is always lower case when retrieved and slugified when saved.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => Str::lower($value),
            set: fn (string $value) => Str::slug($value),
        );
    }
}
