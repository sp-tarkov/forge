<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use App\Contracts\Reportable;
use App\Contracts\Trackable;
use App\Models\Scopes\PublishedScope;
use App\Observers\AddonObserver;
use App\Traits\HasComments;
use App\Traits\HasReports;
use Database\Factories\AddonFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Shetabit\Visitor\Traits\Visitable;
use Stevebauman\Purify\Facades\Purify;

/**
 * @property int $id
 * @property int|null $mod_id
 * @property int|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $teaser
 * @property string|null $description
 * @property string|null $thumbnail
 * @property string|null $thumbnail_hash
 * @property int|null $license_id
 * @property int $downloads
 * @property bool $disabled
 * @property bool $contains_ai_content
 * @property bool $contains_ads
 * @property bool $comments_disabled
 * @property Carbon|null $detached_at
 * @property int|null $detached_by_user_id
 * @property bool $discord_notification_sent
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $detail_url
 * @property-read string $description_html
 * @property-read string|null $thumbnailUrl
 * @property-read Mod|null $mod
 * @property-read User|null $owner
 * @property-read User|null $detachedBy
 * @property-read License|null $license
 * @property-read Collection<int, User> $authors
 * @property-read Collection<int, AddonVersion> $versions
 * @property-read Collection<int, SourceCodeLink> $sourceCodeLinks
 * @property-read AddonVersion|null $latestVersion
 *
 * @implements Commentable<self>
 */
#[ScopedBy([PublishedScope::class])]
#[ObservedBy([AddonObserver::class])]
class Addon extends Model implements Commentable, Reportable, Trackable
{
    /** @use HasComments<self> */
    use HasComments;

    /** @use HasFactory<AddonFactory> */
    use HasFactory;

    /** @use HasReports<Addon> */
    use HasReports;

    use Searchable;
    use Visitable;

    protected $appends = [
        'detail_url',
    ];

    /**
     * The relationship between an addon and its parent mod.
     *
     * @return BelongsTo<Mod, $this>
     */
    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    /**
     * The relationship between an addon and its owner (User).
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The relationship between an addon and the user who detached it.
     *
     * @return BelongsTo<User, $this>
     */
    public function detachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detached_by_user_id');
    }

    /**
     * The relationship between an addon and its authors (Users).
     *
     * @return BelongsToMany<User, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'addon_authors')
            ->withTimestamps();
    }

    /**
     * The relationship between an addon and its versions.
     *
     * @return HasMany<AddonVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AddonVersion::class)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_pre_release = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_pre_release');
    }

    /**
     * The relationship between an addon and its latest version.
     *
     * @return HasOne<AddonVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(AddonVersion::class)
            ->where('disabled', false)
            ->whereNotNull('published_at')
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_pre_release = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_pre_release');
    }

    /**
     * The relationship between an addon and its source code links.
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
     * The relationship between an addon and its license.
     *
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * Check if the addon is detached from its parent mod.
     */
    public function isDetached(): bool
    {
        return $this->detached_at !== null;
    }

    /**
     * Check if user is author or owner of this addon.
     */
    public function isAuthorOrOwner(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->owner_id === $user->id ||
               $this->authors->contains('id', $user->id);
    }

    /**
     * Calculate the total number of downloads for the addon.
     */
    public function calculateDownloads(): void
    {
        DB::table('addons')
            ->where('id', $this->id)
            ->update([
                'downloads' => DB::table('addon_versions')
                    ->where('addon_id', $this->id)
                    ->sum('downloads'),
            ]);

        $this->refresh();
    }

    /**
     * Build the URL to download the latest version of this addon.
     */
    public function downloadUrl(bool $absolute = false): string
    {
        $this->load('latestVersion');

        return route('addon.version.download', [
            $this->id,
            $this->slug,
            $this->latestVersion->version,
        ], absolute: $absolute);
    }

    /**
     * The data that is searchable by Scout.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->load(['latestVersion', 'mod']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'teaser' => $this->teaser,
            'thumbnail' => $this->thumbnailUrl,
            'mod_id' => $this->mod_id,
            'mod_name' => $this->mod?->name,
            'is_detached' => $this->isDetached(),
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
            'published_at' => $this->published_at?->timestamp,
            'latestVersion' => $this->latestVersion ? [
                'version' => $this->latestVersion->version,
                'major' => $this->latestVersion->version_major,
                'minor' => $this->latestVersion->version_minor,
                'patch' => $this->latestVersion->version_patch,
                'label' => $this->latestVersion->version_pre_release,
            ] : null,
        ];
    }

    /**
     * Determine if the model instance should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        // Ensure the addon is not disabled.
        if ($this->disabled) {
            return false;
        }

        // Ensure the addon has a publish date.
        if (is_null($this->published_at)) {
            return false;
        }

        // Ensure the addon is published (not scheduled for future).
        if ($this->published_at->isFuture()) {
            return false;
        }

        // Ensure the addon has at least one published version.
        if (! $this->hasPublishedVersion()) {
            return false;
        }

        // All conditions are met; the addon should be searchable.
        return true;
    }

    /**
     * Check if the addon is publicly visible to anonymous users.
     * This is used to determine if warning ribbons should be shown.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->shouldBeSearchable();
    }

    /**
     * Check if the addon has at least one published version.
     */
    public function hasPublishedVersion(): bool
    {
        return $this->versions()
            ->where('disabled', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->exists();
    }

    /**
     * Determine if this addon can receive comments.
     * Only published addons can receive comments.
     */
    public function canReceiveComments(): bool
    {
        return $this->published_at !== null && $this->published_at <= now();
    }

    /**
     * Get the display name for this commentable model.
     */
    public function getCommentableDisplayName(): string
    {
        return 'addon';
    }

    /**
     * Get the URL to view this addon.
     */
    public function getCommentableUrl(): string
    {
        return route('addon.show', [$this->id, $this->slug]);
    }

    /**
     * Get the title of this addon for display in notifications and UI.
     */
    public function getTitle(): string
    {
        return $this->name;
    }

    /**
     * Comments on addons are displayed on the 'comments' tab.
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
        return 'addon';
    }

    /**
     * Get the title of the reportable model.
     */
    public function getReportableTitle(): string
    {
        return $this->name ?? 'addon #'.$this->id;
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
     * Get the tracking URL for this trackable resource.
     */
    public function getTrackingUrl(): string
    {
        return route('addon.show', [$this->id, $this->slug]);
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
            ->latest()
            ->first();

        return [
            'addon_name' => $this->name,
            'addon_description' => $this->description,
            'addon_version' => $latestVersion?->version,
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
     * Get all unique compatible mod versions from all addon versions, sorted descending.
     *
     * @return Collection<int, ModVersion>
     */
    public function getAllCompatibleModVersions(?int $selectedModVersionId = null): Collection
    {
        // Collect all compatible mod versions from all addon versions
        $allCompatibleModVersions = new Collection();
        foreach ($this->versions as $version) {
            $allCompatibleModVersions = $allCompatibleModVersions->merge($version->compatibleModVersions);
        }

        // Remove duplicates and sort by version numbers (descending)
        $sorted = $allCompatibleModVersions
            ->unique('id')
            ->sortByDesc(fn (ModVersion $version): string => sprintf('%05d.%05d.%05d',
                $version->version_major ?? 0,
                $version->version_minor ?? 0,
                $version->version_patch ?? 0
            ));

        // Return as Eloquent Collection
        return new Collection($sorted->values()->all());
    }

    /**
     * Check if addon has any compatible versions defined but none actually match.
     */
    public function hasNoCompatibleVersions(): bool
    {
        $displayVersion = $this->latestVersion;

        return $this->getAllCompatibleModVersions()->isEmpty() &&
               $displayVersion &&
               $displayVersion->mod_version_constraint;
    }

    /**
     * Scope for publicly visible addons.
     *
     * @param  Builder<Addon>  $query
     * @return Builder<Addon>
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): Builder
    {
        return $query->where('disabled', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Get the rendered HTML version of the description.
     *
     * @return Attribute<string, never>
     */
    protected function descriptionHtml(): Attribute
    {
        return Attribute::make(
            get: fn () => Markdown::convert(Purify::clean($this->description ?? ''))->getContent(),
        );
    }

    /**
     * Get the fully qualified URL of the addon.
     *
     * @return Attribute<string, never>
     */
    protected function detailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): string => route('addon.show', [$this->id, $this->slug]),
        );
    }

    /**
     * Get the full URL for the thumbnail image.
     *
     * @return Attribute<string|null, never>
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->thumbnail ? Storage::disk('public')->url($this->thumbnail) : null,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'disabled' => 'boolean',
            'contains_ai_content' => 'boolean',
            'contains_ads' => 'boolean',
            'comments_disabled' => 'boolean',
            'discord_notification_sent' => 'boolean',
            'published_at' => 'datetime',
            'detached_at' => 'datetime',
        ];
    }
}
