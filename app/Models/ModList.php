<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Commentable;
use App\Enums\ListVisibility;
use App\Observers\ModListObserver;
use App\Traits\HasComments;
use Carbon\CarbonImmutable;
use Database\Factories\ModListFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Override;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $description_html
 * @property string|null $thumbnail
 * @property string|null $thumbnail_hash
 * @property ListVisibility $visibility
 * @property int|null $spt_version_id
 * @property string|null $share_token
 * @property bool $is_default
 * @property bool $comments_disabled
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $thumbnailUrl
 * @property-read User|null $owner
 * @property-read SptVersion|null $sptVersion
 * @property-read EloquentCollection<int, ModListItem> $items
 * @property-read int $item_count
 *
 * @implements Commentable<self>
 */
#[ObservedBy([ModListObserver::class])]
final class ModList extends Model implements Commentable
{
    /** @use HasComments<self> */
    use HasComments;

    /** @use HasFactory<ModListFactory> */
    use HasFactory;

    use Searchable;

    /**
     * Generate a URL-safe random share token.
     */
    public static function generateShareToken(): string
    {
        return Str::random(32);
    }

    /**
     * Whether this list is the user's immutable default Favourites list.
     */
    public function isFavourites(): bool
    {
        return $this->is_default;
    }

    /**
     * The relationship between a list and its owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The relationship between a list and its optional target SPT version.
     *
     * @return BelongsTo<SptVersion, $this>
     */
    public function sptVersion(): BelongsTo
    {
        return $this->belongsTo(SptVersion::class);
    }

    /**
     * The relationship between a list and its items.
     *
     * @return HasMany<ModListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ModListItem::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * Check whether the list contains a given mod.
     */
    public function containsMod(Mod|int $mod): bool
    {
        $modId = $mod instanceof Mod ? $mod->id : $mod;

        return $this->items()
            ->where('listable_type', Mod::class)
            ->where('listable_id', $modId)
            ->exists();
    }

    /**
     * Check whether the list contains a given addon.
     */
    public function containsAddon(Addon|int $addon): bool
    {
        $addonId = $addon instanceof Addon ? $addon->id : $addon;

        return $this->items()
            ->where('listable_type', Addon::class)
            ->where('listable_id', $addonId)
            ->exists();
    }

    /**
     * Count all items (mods + addons) in the list.
     */
    public function itemCount(): int
    {
        return $this->items()->count();
    }

    /**
     * Group items for display: top-level mods with nested addons underneath.
     *
     * Each entry is keyed by parent mod id (or "detached-addon-{id}" for orphan addons)
     * and has shape: ['mod' => ?Mod, 'mod_item' => ?ModListItem, 'addons' => Collection<ModListItem>]
     *
     * @return Collection<string, array{mod: ?Mod, mod_item: ?ModListItem, addons: Collection<int, ModListItem>}>
     */
    public function groupedItems(): Collection
    {
        $this->loadMissing(['items.listable']);

        $modItems = $this->items->filter(fn (ModListItem $item): bool => $item->listable_type === Mod::class);
        $addonItems = $this->items->filter(fn (ModListItem $item): bool => $item->listable_type === Addon::class);

        $grouped = new Collection;

        foreach ($modItems as $modItem) {
            /** @var Mod|null $mod */
            $mod = $modItem->listable;
            $grouped->put((string) $modItem->listable_id, [
                'mod' => $mod,
                'mod_item' => $modItem,
                'addons' => new Collection,
            ]);
        }

        foreach ($addonItems as $addonItem) {
            /** @var Addon|null $addon */
            $addon = $addonItem->listable;
            $parentKey = $addon?->mod_id !== null ? (string) $addon->mod_id : 'detached-'.$addonItem->id;

            if (! $grouped->has($parentKey)) {
                $grouped->put($parentKey, [
                    'mod' => $addon?->mod,
                    'mod_item' => null,
                    'addons' => new Collection,
                ]);
            }

            /** @var array{mod: ?Mod, mod_item: ?ModListItem, addons: Collection<int, ModListItem>} $entry */
            $entry = $grouped->get($parentKey);
            $entry['addons']->push($addonItem);
            $grouped->put($parentKey, $entry);
        }

        return $grouped;
    }

    /**
     * Get the URL to view this list.
     */
    public function detailUrl(): string
    {
        return route('list.show', [
            'listId' => $this->id,
            'slug' => $this->slug,
        ]);
    }

    /**
     * Get the URL for sharing hidden lists.
     */
    public function shareUrl(): ?string
    {
        if (! $this->visibility->requiresShareToken() || $this->share_token === null) {
            return null;
        }

        return route('list.show.shared', [
            'listId' => $this->id,
            'slug' => $this->slug,
            'shareToken' => $this->share_token,
        ]);
    }

    /**
     * The data that is searchable by Scout.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['owner', 'sptVersion']);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'owner_id' => $this->owner_id,
            'owner_name' => $this->owner?->name,
            'spt_version' => $this->sptVersion?->version_formatted,
            'item_count' => $this->itemCount(),
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }

    /**
     * Only public, non-default (i.e. non-Favourites) lists are searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->visibility === ListVisibility::Public && ! $this->is_default;
    }

    /**
     * Determine if this list can receive comments.
     *
     * Favourites never accept comments (personal concept, no audience). Private
     * lists never accept them (only the owner would ever see the thread).
     * Otherwise the owner's comments_disabled toggle decides.
     */
    public function canReceiveComments(): bool
    {
        if ($this->is_default) {
            return false;
        }

        if ($this->visibility === ListVisibility::Private) {
            return false;
        }

        return ! $this->comments_disabled;
    }

    /**
     * Get the display name for this commentable model.
     */
    public function getCommentableDisplayName(): string
    {
        return 'list';
    }

    /**
     * Get the title of this list for display in notifications and UI.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the URL to view this list.
     */
    public function getCommentableUrl(): string
    {
        return $this->detailUrl();
    }

    /**
     * Comments are rendered inline under the list; use a plain anchor hash.
     */
    public function getCommentTabHash(): string
    {
        return 'comments';
    }

    /**
     * Scope a query to only public lists (includes public Favourites).
     * Used for visibility checks where Favourites should still count.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', ListVisibility::Public);
    }

    /**
     * Scope a query to lists surfaced in public discovery (index, search, featured).
     * Favourites (is_default) are always excluded from discovery, even when public.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeDiscoverable(Builder $query): Builder
    {
        return $query->where('visibility', ListVisibility::Public)
            ->where('is_default', false);
    }

    /**
     * Scope a query to lists visible to a specific viewer.
     *
     * Public lists are always visible; hidden/private lists only to the owner.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if (! $viewer instanceof User) {
            return $query->where('visibility', ListVisibility::Public);
        }

        return $query->where(function (Builder $q) use ($viewer): void {
            $q->where('visibility', ListVisibility::Public)
                ->orWhere('owner_id', $viewer->id);
        });
    }

    /**
     * Build the URL to the list's thumbnail.
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
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'visibility' => ListVisibility::class,
            'is_default' => 'boolean',
            'comments_disabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
