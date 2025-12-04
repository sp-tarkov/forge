<?php

declare(strict_types=1);

namespace App\Livewire\Addon;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\AddonVersion;
use App\Traits\Livewire\ModerationActionMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read AddonVersion $version
 */
class VersionAction extends Component
{
    use ModerationActionMenu;

    /**
     * The version ID.
     */
    #[Locked]
    public int $versionId;

    /**
     * The addon ID.
     */
    #[Locked]
    public int $addonId;

    /**
     * Cached version properties for quick access.
     */
    #[Locked]
    public string $versionNumber;

    /**
     * Whether the version is disabled.
     */
    #[Locked]
    public bool $versionDisabled;

    /**
     * Whether the version is published.
     */
    #[Locked]
    public bool $versionPublished;

    /**
     * The publish date for the version.
     */
    public ?string $publishedAt = null;

    /**
     * The reason for moderation actions.
     */
    public string $moderationReason = '';

    /**
     * Initialize the component with optimized data.
     */
    public function mount(int $versionId, int $addonId, string $versionNumber, bool $versionDisabled, bool $versionPublished): void
    {
        $this->versionId = $versionId;
        $this->addonId = $addonId;
        $this->versionNumber = $versionNumber;
        $this->versionDisabled = $versionDisabled;
        $this->versionPublished = $versionPublished;
    }

    /**
     * Get the version model instance.
     */
    #[Computed(persist: true)]
    public function version(): AddonVersion
    {
        return AddonVersion::query()->select(['id', 'version', 'disabled', 'published_at', 'addon_id'])
            ->with(['addon:id,name,owner_id', 'addon.owner:id', 'addon.additionalAuthors:id'])
            ->findOrFail($this->versionId);
    }

    /**
     * Determine if the moderation reason field should be shown.
     * Only show for mod/admin users who are NOT an owner or additional author.
     */
    #[Computed]
    public function showModerationReason(): bool
    {
        $user = Auth::user();

        return $user
            && $user->isModOrAdmin()
            && ! $this->version->addon->isAuthorOrOwner($user);
    }

    /**
     * Get cached permissions for the current user.
     *
     * @return array<string, bool>
     */
    #[Computed(persist: true)]
    public function permissions(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return Cache::remember(
            sprintf('addon_version.%d.permissions.%s', $this->versionId, $user->id),
            60, // Seconds
            fn (): array => [
                'viewActions' => Gate::allows('viewActions', [$this->version->addon, $this->version->addon]),
                'update' => Gate::allows('update', $this->version),
                'delete' => Gate::allows('delete', $this->version),
                'disable' => Gate::allows('disable', $this->version),
                'enable' => Gate::allows('enable', $this->version),
                'publish' => Gate::allows('publish', $this->version),
                'unpublish' => Gate::allows('unpublish', $this->version),
                'isModOrAdmin' => $user->isModOrAdmin(),
            ]
        );
    }

    /**
     * Disables the version.
     */
    public function disable(): void
    {
        $version = $this->version;

        $this->authorize('disable', $version);

        $version->disabled = true;
        $version->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $version->addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_VERSION_DISABLE,
            $version,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->versionDisabled = true;
        $this->clearPermissionCache(sprintf('addon_version.%d.permissions.%s', $this->versionId, (string) Auth::id()));

        $this->dispatch('addon-version-updated.'.$this->versionId, disabled: true);

        flash()->success('Addon version successfully disabled!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Enables the version.
     */
    public function enable(): void
    {
        $version = $this->version;

        $this->authorize('enable', $version);

        $version->disabled = false;
        $version->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $version->addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_VERSION_ENABLE,
            $version,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->versionDisabled = false;
        $this->clearPermissionCache(sprintf('addon_version.%d.permissions.%s', $this->versionId, (string) Auth::id()));

        $this->dispatch('addon-version-updated.'.$this->versionId, disabled: false);

        flash()->success('Addon version successfully enabled!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Publishes the version with a specified date.
     */
    public function publish(): void
    {
        $publishedDate = $this->publishedAt ? Date::parse($this->publishedAt) : now();
        $version = $this->version;

        $this->authorize('publish', $version);

        $version->published_at = $publishedDate;
        $version->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $version->addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_VERSION_PUBLISH,
            $version,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->versionPublished = true;
        $this->clearPermissionCache(sprintf('addon_version.%d.permissions.%s', $this->versionId, (string) Auth::id()));

        $this->dispatch('addon-version-updated.'.$this->versionId, published: true);

        flash()->success('Addon version successfully published!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Unpublishes the version.
     */
    public function unpublish(): void
    {
        $version = $this->version;

        $this->authorize('unpublish', $version);

        $version->published_at = null;
        $version->save();

        // Only flag as moderation action if the current user is a mod/admin acting on someone else's content
        $user = Auth::user();
        $isModerationAction = $user && ! $version->addon->isAuthorOrOwner($user) && $user->isModOrAdmin();

        Track::eventSync(
            TrackingEventType::ADDON_VERSION_UNPUBLISH,
            $version,
            isModerationAction: $isModerationAction,
            reason: $isModerationAction ? ($this->moderationReason ?: null) : null
        );

        $this->versionPublished = false;
        $this->clearPermissionCache(sprintf('addon_version.%d.permissions.%s', $this->versionId, (string) Auth::id()));

        $this->dispatch('addon-version-updated.'.$this->versionId, published: false);

        flash()->success('Addon version successfully unpublished!');

        $this->moderationReason = '';
        $this->menuOpen = false;
    }

    /**
     * Render the component.
     */
    public function render(): string|View
    {
        return view('livewire.addon.version-action');
    }
}
