<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use App\Contracts\Reportable;
use App\Contracts\Trackable;
use App\Models\Scopes\PublishedScope;
use App\Observers\ModObserver;
use App\Traits\HasComments;
use App\Traits\HasReports;
use Database\Factories\ModFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Shetabit\Visitor\Traits\Visitable;
use Stevebauman\Purify\Facades\Purify;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property string $guid
 * @property int|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string $teaser
 * @property string $description
 * @property string $thumbnail
 * @property int|null $license_id
 * @property int|null $category_id
 * @property int $downloads
 * @property bool $featured
 * @property bool $contains_ai_content
 * @property bool $contains_ads
 * @property bool $disabled
 * @property bool $comments_disabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $published_at
 * @property-read string $detail_url
 * @property-read string $description_html
 * @property-read User|null $owner
 * @property-read License|null $license
 * @property-read ModCategory|null $category
 * @property-read Collection<int, User> $authors
 * @property-read Collection<int, ModVersion> $versions
 * @property-read Collection<int, ModSourceCodeLink> $sourceCodeLinks
 * @property-read ModVersion|null $latestVersion
 * @property-read ModVersion|null $latestUpdatedVersion
 *
 * @implements Commentable<self>
 */
#[ScopedBy([PublishedScope::class])]
#[ObservedBy([ModObserver::class])]
class Mod extends Model implements Commentable, Reportable, Trackable
{
    /** @use HasComments<self> */
    use HasComments;

    /** @use HasFactory<ModFactory> */
    use HasFactory;

    /** @use HasReports<Mod> */
    use HasReports;

    use Searchable;
    use Visitable;

    protected $appends = [
        'detail_url',
    ];

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
     * The relationship between a mod and its source code links.
     * Links are sorted alphabetically by label (or URL if no label).
     *
     * @return HasMany<ModSourceCodeLink, $this>
     */
    public function sourceCodeLinks(): HasMany
    {
        return $this->hasMany(ModSourceCodeLink::class)
            ->orderByRaw("COALESCE(NULLIF(label, ''), url)");
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
     * The relationship between a mod and its category.
     *
     * @return BelongsTo<ModCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ModCategory::class);
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
            ->whereNotNull('published_at')
            ->where('disabled', false)
            ->whereHas('latestSptVersion')
            ->ofMany('updated_at', 'max');
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
            'guid' => $this->guid,
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

        // Check if mod has any versions compatible with active SPT releases
        if (! $this->hasActiveSptCompatibility()) {
            return false;
        }

        // All conditions are met; the mod should be searchable.
        return true;
    }

    /**
     * Check if a mod has publicly visible versions that are compatible with active SPT releases.
     * Used for search indexing to ensure only relevant mods appear in search results.
     */
    private function hasActiveSptCompatibility(): bool
    {
        // Get active SPT versions (last three minor versions) for search
        $activeSptVersions = Cache::remember('active_spt_versions_for_search', 60 * 60, fn (): Collection => SptVersion::getVersionsForLastThreeMinors());
        $activeSptVersionIds = $activeSptVersions->pluck('version')->toArray();

        // Use the scope to filter and then check for active SPT versions
        return $this->versions()
            ->publiclyVisible()
            ->whereHas('latestSptVersion', function (Builder $query) use ($activeSptVersionIds): void {
                $query->whereIn('version', $activeSptVersionIds);
            })
            ->exists();
    }

    /**
     * The relationship between a mod and its latest version.
     *
     * @return HasOne<ModVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(ModVersion::class)
            ->where('disabled', false)
            ->whereHas('latestSptVersion')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels');
    }

    /**
     * Build the URL to the mod's thumbnail.
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
     * Get the URL to the mod's detail page.
     *
     * @return Attribute<string, string>
     */
    protected function detailUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('mod.show', [$this->id, $this->slug]));
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'category_id' => 'integer',
            'featured' => 'boolean',
            'contains_ai_content' => 'boolean',
            'contains_ads' => 'boolean',
            'disabled' => 'boolean',
            'comments_disabled' => 'boolean',
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
     * Determine if this mod can receive comments.
     * Only published mods that don't have comments disabled can receive comments.
     */
    public function canReceiveComments(): bool
    {
        if ($this->comments_disabled) {
            return false;
        }

        return $this->published_at !== null && $this->published_at <= now();
    }

    /**
     * Get the display name for this commentable model.
     */
    public function getCommentableDisplayName(): string
    {
        return 'mod';
    }

    /**
     * Get the URL to view this mod.
     */
    public function getCommentableUrl(): string
    {
        return route('mod.show', [$this->id, $this->slug]);
    }

    /**
     * Get the title of this mod for display in notifications and UI.
     */
    public function getTitle(): string
    {
        return $this->name;
    }

    /**
     * Comments on mods are displayed on the 'comments' tab.
     */
    public function getCommentTabHash(): ?string
    {
        return 'comments';
    }

    /**
     * Get a human-readable display name for the reportable model.
     */
    public function getReportableDisplayName(): string
    {
        return 'mod';
    }

    /**
     * Get the title of the reportable model.
     */
    public function getReportableTitle(): string
    {
        return $this->name ?? 'mod #'.$this->id;
    }

    /**
     * Get an excerpt of the reportable content for display in notifications.
     */
    public function getReportableExcerpt(): ?string
    {
        return $this->description ? Str::words($this->description, 15, '...') : null;
    }

    /**
     * Get the URL to view the reportable content.
     */
    public function getReportableUrl(): string
    {
        return $this->detail_url;
    }

    /**
     * Get the URL to view this trackable resource.
     */
    public function getTrackingUrl(): string
    {
        return route('mod.show', [$this->id, $this->slug]);
    }

    /**
     * Get the display title for this trackable resource.
     */
    public function getTrackingTitle(): string
    {
        return $this->name;
    }

    /**
     * Get the snapshot data to store for this trackable resource.
     *
     * @return array<string, mixed>
     */
    public function getTrackingSnapshot(): array
    {
        $latestVersion = $this->versions()
            ->whereNotNull('published_at')
            ->where('disabled', false)
            ->whereHas('latestSptVersion')
            ->latest()
            ->first();

        return [
            'mod_name' => $this->name,
            'mod_description' => $this->description,
            'mod_version' => $latestVersion?->version,
        ];
    }

    /**
     * Get contextual information about this trackable resource.
     */
    public function getTrackingContext(): ?string
    {
        return $this->description;
    }

    /**
     * Check if the given user is an author or the owner of this mod.
     */
    public function isAuthorOrOwner(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->id === $this->owner?->id || $this->authors->pluck('id')->contains($user->id);
    }

    /**
     * Add a new source code link to this mod.
     */
    public function addSourceCodeLink(string $url, string $label = ''): ModSourceCodeLink
    {
        return $this->sourceCodeLinks()->create([
            'url' => $url,
            'label' => $label,
        ]);
    }
}
