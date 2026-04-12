<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Traits\Livewire\ModeratesAddon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component {
    use ModeratesAddon;

    /**
     * The addon being shown.
     */
    public Addon $addon;

    /**
     * The Open Graph image for social media sharing.
     */
    public ?string $openGraphImage = null;

    /**
     * Mount the component.
     */
    public function mount(int $addonId, string $slug): void
    {
        $this->addon = $this->getAddon($addonId);

        $this->enforceCanonicalSlug($this->addon, $slug);

        Gate::authorize('view', $this->addon);

        $this->openGraphImage = $this->addon->thumbnail;
    }

    /**
     * Check if the current user should see warnings about this addon.
     */
    public function shouldShowWarnings(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }
        // Only show warnings to privileged users (owners, authors, mods, admins)
        if ($user->isModOrAdmin()) {
            return true;
        }
        return $this->addon->isAuthorOrOwner($user);
    }

    /**
     * Get the warning messages for this addon.
     *
     * @return array<string, string>
     */
    public function getWarningMessages(): array
    {
        $warnings = [];

        // Single query for all version counts instead of 3 separate count queries
        $versionCounts = $this->addon->versions()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN published_at IS NOT NULL AND published_at <= NOW() THEN 1 ELSE 0 END) as published')
            ->selectRaw('SUM(CASE WHEN disabled = false THEN 1 ELSE 0 END) as enabled')
            ->first();

        $total = (int) ($versionCounts->total ?? 0); // @phpstan-ignore cast.int
        $published = (int) ($versionCounts->published ?? 0); // @phpstan-ignore cast.int
        $enabled = (int) ($versionCounts->enabled ?? 0); // @phpstan-ignore cast.int

        if ($total === 0) {
            $warnings['no_versions'] = 'This addon has no versions. Users will be unable to view this addon until a version is created.';
        } else {
            if ($published === 0) {
                $warnings['no_published_versions'] = 'This addon has no published versions. Users will be unable to view this addon until a version is published.';
            }

            if ($enabled === 0) {
                $warnings['no_enabled_versions'] = 'This addon has no enabled versions. Users will be unable to view this addon until a version is enabled.';
            }
        }

        // Check if the addon itself is unpublished
        if (!$this->addon->published_at) {
            $warnings['unpublished'] = 'This addon is unpublished. Users will be unable to view this addon until it is published.';
        }

        // Check if the addon is disabled
        if ($this->addon->disabled) {
            $warnings['disabled'] = 'This addon is disabled. Users will be unable to view this addon until it is enabled.';
        }

        // Check if parent mod is publicly visible
        $mod = $this->addon->mod;
        if ($mod && !$mod->isPubliclyVisible()) {
            $warnings['parent_mod_not_visible'] = 'Users will be unable to view this addon until the parent mod is publicly available.';
        }

        return $warnings;
    }

    /**
     * Get the total version count visible to the current user.
     */
    public function getVersionCount(): int
    {
        return $this->addon
            ->versions()
            ->when(
                !auth()
                    ->user()
                    ?->can('viewAny', [AddonVersion::class, $this->addon]),
                function (Builder $query): void {
                    $query->where('disabled', false)->whereNotNull('published_at')->where('published_at', '<=', now());
                },
            )
            ->count();
    }

    /**
     * Get the total comment count visible to the current user.
     */
    public function getCommentCount(): int
    {
        $user = auth()->user();

        return $this->addon->comments()->visibleToUser($user)->count();
    }

    /**
     * Get the addon by ID.
     */
    protected function getAddon(int $addonId): Addon
    {
        return Addon::query()
            ->withoutGlobalScopes()
            ->with(['sourceCodeLinks', 'mod:id,name,slug,disabled,published_at', 'owner', 'additionalAuthors', 'license', 'latestVersion'])
            ->findOrFail($addonId);
    }

    /**
     * Enforce that the slug matches the canonical slug.
     */
    protected function enforceCanonicalSlug(Addon $addon, string $slug): void
    {
        if ($addon->slug !== $slug) {
            $this->redirectRoute('addon.show', [$addon->id, $addon->slug], navigate: true);
        }
    }

    /**
     * Get view data.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'addon' => $this->addon,
            'shouldShowWarnings' => $this->shouldShowWarnings(),
            'warningMessages' => $this->getWarningMessages(),
            'versionCount' => $this->getVersionCount(),
            'commentCount' => $this->getCommentCount(),
        ];
    }
};
