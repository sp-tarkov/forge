<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidVersionNumberException;
use App\Models\Scopes\PublishedScope;
use App\Observers\AddonVersionObserver;
use App\Support\Version;
use Carbon\Carbon;
use Database\Factories\AddonVersionFactory;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Override;
use Stevebauman\Purify\Facades\Purify;

/**
 * @property int $id
 * @property int $addon_id
 * @property string $version
 * @property int $version_major
 * @property int $version_minor
 * @property int $version_patch
 * @property string $version_pre_release
 * @property string|null $description
 * @property string $link
 * @property int|null $content_length
 * @property string $mod_version_constraint
 * @property int $downloads
 * @property bool $disabled
 * @property bool $discord_notification_sent
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $description_html
 * @property-read string|null $formatted_file_size
 * @property-read Addon $addon
 * @property-read Collection<int, ModVersion> $compatibleModVersions
 * @property-read Collection<int, VirusTotalLink> $virusTotalLinks
 */
#[ScopedBy([PublishedScope::class])]
#[ObservedBy([AddonVersionObserver::class])]
class AddonVersion extends Model
{
    /** @use HasFactory<AddonVersionFactory> */
    use HasFactory;

    /**
     * Update the parent addon's updated_at timestamp when the addon version is updated.
     *
     * @var string[]
     */
    protected $touches = ['addon'];

    /**
     * The relationship between an addon version and addon.
     *
     * @return BelongsTo<Addon, $this>
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    /**
     * The relationship between an addon version and its compatible mod versions.
     *
     * @return BelongsToMany<ModVersion, $this>
     */
    public function compatibleModVersions(): BelongsToMany
    {
        return $this->belongsToMany(ModVersion::class, 'addon_resolved_mod_versions')
            ->withTimestamps();
    }

    /**
     * The relationship between an addon version and its VirusTotal links.
     *
     * @return MorphMany<VirusTotalLink, $this>
     */
    public function virusTotalLinks(): MorphMany
    {
        return $this->morphMany(VirusTotalLink::class, 'linkable')
            ->orderByRaw("COALESCE(NULLIF(label, ''), url)");
    }

    /**
     * Build the download URL for this addon version.
     */
    public function downloadUrl(bool $absolute = false): string
    {
        return route('addon.version.download', [$this->addon->id, $this->addon->slug, $this->version], absolute: $absolute);
    }

    /**
     * Increment the download count for this addon version.
     */
    public function incrementDownloads(): int
    {
        DB::table('addon_versions')
            ->where('id', $this->id)
            ->increment('downloads');

        $this->refresh();

        $this->addon->calculateDownloads();

        return $this->downloads;
    }

    /**
     * Get compatible mod versions sorted by version number (descending).
     *
     * @return Collection<int, ModVersion>
     */
    public function getSortedCompatibleModVersions(): Collection
    {
        return $this->compatibleModVersions->sortByDesc(fn (ModVersion $modVersion): string => sprintf('%05d.%05d.%05d',
            $modVersion->version_major ?? 0,
            $modVersion->version_minor ?? 0,
            $modVersion->version_patch ?? 0
        ))->values();
    }

    /**
     * Boot the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::saving(function (AddonVersion $addonVersion): void {
            // Extract the version sections from the version string.
            try {
                $version = new Version($addonVersion->version);

                $addonVersion->version_major = $version->getMajor();
                $addonVersion->version_minor = $version->getMinor();
                $addonVersion->version_patch = $version->getPatch();
                $addonVersion->version_pre_release = $version->getLabels();
            } catch (InvalidVersionNumberException) {
                $addonVersion->version_major = 0;
                $addonVersion->version_minor = 0;
                $addonVersion->version_patch = 0;
                $addonVersion->version_pre_release = '';
            }
        });
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
     * Get the formatted file size with dynamic units (B, KB, MB, GB, TB).
     *
     * @return Attribute<string|null, never>
     */
    protected function formattedFileSize(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->content_length !== null
                ? Number::fileSize($this->content_length, precision: 2)
                : null
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
            'version_major' => 'integer',
            'version_minor' => 'integer',
            'version_patch' => 'integer',
            'content_length' => 'integer',
            'downloads' => 'integer',
            'disabled' => 'boolean',
            'discord_notification_sent' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
