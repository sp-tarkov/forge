<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use App\Contracts\Reportable;
use App\Contracts\Trackable;
use App\Enums\FikaCompatibility;
use App\Models\Scopes\PublishedScope;
use App\Observers\ModObserver;
use App\Traits\HasComments;
use App\Traits\HasReports;
use Carbon\CarbonImmutable;
use Database\Factories\ModFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Override;
use Shetabit\Visitor\Traits\Visitable;
use Stevebauman\Purify\Facades\Purify;

/**
 * @property int $id
 * @property int|null $hub_id
 * @property string|null $guid
 * @property int|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string $teaser
 * @property string $description
 * @property string $thumbnail
 * @property int|null $license_id
 * @property int|null $category_id
 * @property int $downloads
 * @property ModVersion|null $latestCompatibleVersion Dynamic property for dependency tree endpoint
 * @property array<int, mixed> $dependencies Dynamic property for dependency tree endpoint
 * @property bool $conflict Dynamic property for dependency tree endpoint indicating version constraint conflicts
 * @property bool $featured
 * @property bool $contains_ai_content
 * @property bool $contains_ai_content_locked
 * @property string|null $custom_ai_disclosure
 * @property bool $contains_ads
 * @property bool $disabled
 * @property bool $comments_disabled
 * @property bool $addons_disabled
 * @property bool $lists_disabled
 * @property bool $profile_binding_notice_disabled
 * @property bool $cheat_notice
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $published_at
 * @property-read string $detail_url
 * @property-read string $description_html
 * @property-read string $custom_ai_disclosure_html
 * @property-read bool $addons_enabled
 * @property-read bool $lists_enabled
 * @property-read bool $fika_compatibility
 * @property-read bool $shows_profile_binding_notice
 * @property-read User|null $owner
 * @property-read License|null $license
 * @property-read ModCategory|null $category
 * @property-read Collection<int, User> $additionalAuthors
 * @property-read Collection<int, ModVersion> $versions
 * @property-read Collection<int, SourceCodeLink> $sourceCodeLinks
 * @property-read Collection<int, Addon> $addons
 * @property-read ModVersion|null $latestVersion
 * @property-read ModVersion|null $latestUpdatedVersion
 *
 * @implements Commentable<self>
 */
#[ScopedBy([PublishedScope::class])]
#[ObservedBy([ModObserver::class])]
#[Appends([
    'detail_url',
])]
final class Mod extends Model implements Commentable, Reportable, Trackable
{
    /** @use HasComments<self> */
    use HasComments;

    /** @use HasFactory<ModFactory> */
    use HasFactory;

    /** @use HasReports<Mod> */
    use HasReports;

    use Searchable;
    use Visitable;

    /**
     * The validation pattern for a mod GUID. Lowercase reverse-domain notation allowing digits and hyphens within
     * each dot-separated segment (for example "com.example.my-mod").
     */
    public const string GUID_REGEX = '/^[a-z0-9-]+(\.[a-z0-9-]+)*$/';

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
     * @return MorphMany<SourceCodeLink, $this>
     */
    public function sourceCodeLinks(): MorphMany
    {
        return $this->morphMany(SourceCodeLink::class, 'sourceable')
            ->orderByRaw("COALESCE(NULLIF(label, ''), url)");
    }

    /**
     * Calculate the total number of downloads for the mod.
     */
    public function calculateDownloads(): void
    {
        $cacheKey = 'mod_downloads_cached_'.$this->id;

        // Get the cached download count in a single call to avoid race conditions.
        // If no cache exists, $cachedDownloads will be null.
        /** @var int|null $cachedDownloads */
        $cachedDownloads = Cache::get($cacheKey);

        // Calculate the new actual download count
        $newDownloads = (int) DB::table('mod_versions')
            ->where('mod_id', $this->id)
            ->sum('downloads');

        // Update the mod's downloads field
        DB::table('mods')
            ->where('id', $this->id)
            ->update(['downloads' => $newDownloads]);

        $this->refresh();

        // Only sync to search if there was a previously cached value and the difference is >= 15
        if ($cachedDownloads !== null && abs($newDownloads - $cachedDownloads) >= 15) {
            $this->searchable();
        }

        Cache::forever($cacheKey, $newDownloads);
    }

    /**
     * Build the URL to download the latest version of this mod.
     */
    public function downloadUrl(bool $absolute = false): ?string
    {
        $this->loadMissing('latestVersion');

        if ($this->latestVersion === null) {
            return null;
        }

        return route('mod.version.download', [
            $this->id,
            $this->slug,
            $this->latestVersion->version,
        ], absolute: $absolute);
    }

    /**
     * The relationship between a mod and its additional authors (Users).
     *
     * @return MorphToMany<User, $this>
     */
    public function additionalAuthors(): MorphToMany
    {
        return $this->morphToMany(User::class, 'authorable', 'additional_authors');
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
     * The relationship between a mod and its addons.
     *
     * @return HasMany<Addon, $this>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class);
    }

    /**
     * The relationship between a mod and the list items that reference it.
     *
     * @return MorphMany<ModListItem, $this>
     */
    public function listItems(): MorphMany
    {
        return $this->morphMany(ModListItem::class, 'listable');
    }

    /**
     * The relationship between a mod and its enabled addons.
     *
     * @return HasMany<Addon, $this>
     */
    public function enabledAddons(): HasMany
    {
        return $this->hasMany(Addon::class)
            ->where('disabled', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * The relationship between a mod and its attached (non-detached) addons.
     *
     * @return HasMany<Addon, $this>
     */
    public function attachedAddons(): HasMany
    {
        return $this->hasMany(Addon::class)
            ->whereNull('detached_at');
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
            ->ofMany('created_at', 'max');
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
     * Versions of this mod that are published and confirmed Fika compatible. The API query builder eager-loads this so
     * the mod-level fika_compatibility flag can be resolved from a single batched query for the whole page instead of
     * one EXISTS query per mod during serialization. It is loaded for existence only; do not read other columns off the
     * resulting models, as the eager-load selects just the keys.
     *
     * @return HasMany<ModVersion, $this>
     */
    public function fikaCompatibleVersions(): HasMany
    {
        return $this->hasMany(ModVersion::class)
            ->where('fika_compatibility', FikaCompatibility::Compatible)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * The data that is searchable by Scout.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing([
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
            'downloads' => $this->downloads,
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
            'published_at' => $this->published_at?->timestamp,
            'latestVersion' => $this->latestVersion?->latestSptVersion?->version_formatted,
            'latestVersionColorClass' => $this->latestVersion?->latestSptVersion?->color_class,
            'latestVersionMajor' => $this->latestVersion?->latestSptVersion ? $this->latestVersion->latestSptVersion->version_major : 0,
            'latestVersionMinor' => $this->latestVersion?->latestSptVersion ? $this->latestVersion->latestSptVersion->version_minor : 0,
            'latestVersionPatch' => $this->latestVersion?->latestSptVersion ? $this->latestVersion->latestSptVersion->version_patch : 0,
            'latestVersionLabel' => $this->latestVersion?->latestSptVersion ? $this->latestVersion->latestSptVersion->version_labels : '',
        ];
    }

    /**
     * Check if the mod is published (has a publish date that is not in the future).
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null && ! $this->published_at->isFuture();
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

        // Ensure the mod is published.
        if (! $this->isPublished()) {
            return false;
        }

        // Check if mod has any versions compatible with active SPT releases
        // All conditions are met; the mod should be searchable.
        return $this->hasActiveSptCompatibility();
    }

    /**
     * Check if the mod is publicly visible to anonymous users.
     * This checks for ANY published SPT version, not just active ones.
     * This is used for general visibility (view policy, ribbons, etc.)
     */
    public function isPubliclyVisible(): bool
    {
        // Ensure the mod is not disabled
        if ($this->disabled) {
            return false;
        }

        // Ensure the mod is published
        if (! $this->isPublished()) {
            return false;
        }

        // Check for modern versions with SPT compatibility
        $hasModernVersion = $this->versions()
            ->publiclyVisible()
            ->whereHas('latestSptVersion')
            ->exists();

        if ($hasModernVersion) {
            return true;
        }

        // Check for legacy versions (no SPT constraint)
        return $this->versions()
            ->legacyPubliclyVisible()
            ->exists();
    }

    /**
     * Check if the mod has only legacy versions (no versions with SPT compatibility).
     */
    public function hasOnlyLegacyVersions(): bool
    {
        // Has at least one legacy version that's publicly visible
        $hasLegacyVersion = $this->versions()
            ->legacyPubliclyVisible()
            ->exists();

        // Has no versions with SPT versions
        $hasModernVersion = $this->versions()
            ->publiclyVisible()
            ->exists();

        return $hasLegacyVersion && ! $hasModernVersion;
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
     * The relationship between a mod and its latest legacy version.
     *
     * @return HasOne<ModVersion, $this>
     */
    public function latestLegacyVersion(): HasOne
    {
        return $this->hasOne(ModVersion::class)
            ->where('disabled', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('spt_version_constraint', '')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels');
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
    public function getCommentTabHash(): string
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
    public function getTrackingContext(): string
    {
        return $this->description;
    }

    /**
     * Check if the given user is an author or the owner of this mod.
     */
    public function isAuthorOrOwner(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->id === $this->owner_id || $this->additionalAuthors()->where('user_id', $user->id)->exists();
    }

    /**
     * Add a new source code link to this mod.
     */
    public function addSourceCodeLink(string $url, string $label = ''): SourceCodeLink
    {
        return $this->sourceCodeLinks()->create([
            'url' => $url,
            'label' => $label,
        ]);
    }

    /**
     * Check if the mod has any published versions that are Fika compatible.
     */
    public function hasFikaCompatibleVersion(): bool
    {
        return $this->versions()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('fika_compatibility', FikaCompatibility::Compatible)
            ->exists();
    }

    /**
     * Get the overall Fika compatibility status for this mod based on all published versions.
     *
     * Returns:
     * - Compatible if ANY published version is compatible
     * - Unknown if ALL published versions are unknown
     * - Incompatible otherwise (at least one is incompatible and none are compatible)
     */
    public function getOverallFikaCompatibility(): FikaCompatibility
    {
        // Use efficient EXISTS queries instead of loading all versions
        $baseQuery = fn () => $this->versions()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        if (! $baseQuery()->exists()) {
            return FikaCompatibility::Unknown;
        }

        // If any version is compatible, return Compatible
        if ($baseQuery()->where('fika_compatibility', FikaCompatibility::Compatible)->exists()) {
            return FikaCompatibility::Compatible;
        }

        // If all versions are unknown, return Unknown (no non-unknown versions exist)
        if (! $baseQuery()->where('fika_compatibility', '!=', FikaCompatibility::Unknown)->exists()) {
            return FikaCompatibility::Unknown;
        }

        // Otherwise, at least one is incompatible and none are compatible
        return FikaCompatibility::Incompatible;
    }

    /**
     * Determine if the mod has any published version that is Fika compatible.
     *
     * When the fikaCompatibleVersions relationship has been eager-loaded (as the API query builder does), the flag is
     * resolved from the loaded collection to avoid an N+1 EXISTS query per mod during serialization. Otherwise it falls
     * back to a scoped existence check that returns the identical result.
     *
     * @return Attribute<bool, never>
     */
    protected function fikaCompatibility(): Attribute
    {
        return Attribute::get(function (): bool {
            if ($this->relationLoaded('fikaCompatibleVersions')) {
                return $this->fikaCompatibleVersions->isNotEmpty();
            }

            return $this->versions()
                ->where('fika_compatibility', FikaCompatibility::Compatible)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->exists();
        })->shouldCache();
    }

    /**
     * Get whether addons are enabled for this mod.
     *
     * @return Attribute<bool, never>
     */
    protected function addonsEnabled(): Attribute
    {
        return Attribute::get(fn (): bool => ! $this->addons_disabled);
    }

    /**
     * Get whether this mod may be added to user-created mod lists. Favourites bypass this check; the guard lives in
     * ModListService::addMod/addAddon.
     *
     * @return Attribute<bool, never>
     */
    protected function listsEnabled(): Attribute
    {
        return Attribute::get(fn (): bool => ! $this->lists_disabled);
    }

    /**
     * Get whether this mod should display a profile binding notice.
     * Returns true if the category enables the notice AND the mod hasn't disabled it.
     *
     * @return Attribute<bool, never>
     */
    protected function showsProfileBindingNotice(): Attribute
    {
        return Attribute::get(function (): bool {
            if ($this->profile_binding_notice_disabled) {
                return false;
            }

            return $this->category !== null && $this->category->shows_profile_binding_notice;
        });
    }

    /**
     * Build the URL to the mod's thumbnail.
     *
     * @return Attribute<string, never>
     */
    protected function thumbnailUrl(): Attribute
    {
        /** @var string $disk */
        $disk = config()->string('filesystems.asset_upload', 'public');

        return Attribute::get(fn (): string => $this->thumbnail
            ? Storage::disk($disk)->url($this->thumbnail)
            : '');
    }

    /**
     * Get the URL to the mod's detail page.
     *
     * @return Attribute<string, never>
     */
    protected function detailUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('mod.show', [$this->id, $this->slug]));
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'category_id' => 'integer',
            'featured' => 'boolean',
            'contains_ai_content' => 'boolean',
            'contains_ai_content_locked' => 'boolean',
            'contains_ads' => 'boolean',
            'disabled' => 'boolean',
            'comments_disabled' => 'boolean',
            'addons_disabled' => 'boolean',
            'lists_disabled' => 'boolean',
            'profile_binding_notice_disabled' => 'boolean',
            'cheat_notice' => 'boolean',
            'discord_notification_sent' => 'boolean',
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
            get: fn (mixed $value): string => is_string($value) ? Str::lower($value) : '',
            set: fn (?string $value): string => $value ? Str::slug($value) : '',
        );
    }

    /**
     * Normalize the GUID to lowercase on read and write, collapsing empty values to null. Collapsing empty values to
     * null lets the unique index treat all "no GUID" rows as distinct.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function guid(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?string => (is_string($value) && $value !== '') ? Str::lower($value) : null,
            set: fn (?string $value): ?string => ($value === null || $value === '') ? null : Str::lower($value),
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
            get: function (): string {
                if (! $this->description) {
                    return '';
                }

                /** @var string $clean */
                $clean = Purify::config('description')->clean(
                    Markdown::convert($this->description)->getContent()
                );

                return $clean;
            }
        )->shouldCache();
    }

    /**
     * Generate the cleaned HTML version of the custom AI disclosure.
     *
     * @return Attribute<string, never>
     */
    protected function customAiDisclosureHtml(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if (! $this->custom_ai_disclosure) {
                    return '';
                }

                /** @var string $clean */
                $clean = Purify::config('description')->clean(
                    Markdown::convert($this->custom_ai_disclosure)->getContent()
                );

                return $clean;
            }
        )->shouldCache();
    }

    /**
     * Check if a mod has publicly visible versions that are compatible with active SPT releases.
     * Used for search indexing to ensure only relevant mods appear in search results.
     */
    private function hasActiveSptCompatibility(): bool
    {
        // Get active SPT version strings (last three minor versions) for search; cache strings, not models.
        /** @var array<int, string> $activeSptVersionIds */
        $activeSptVersionIds = Cache::remember('active_spt_versions_for_search', 60 * 60, fn (): array => SptVersion::getVersionsForLastThreeMinors()->pluck('version')->all());

        // Use the scope to filter and then check for active SPT versions
        return $this->versions()
            ->publiclyVisible()
            ->whereHas('latestSptVersion', function (Builder $query) use ($activeSptVersionIds): void {
                $query->whereIn('version', $activeSptVersionIds);
            })
            ->exists();
    }
}
