<?php

declare(strict_types=1);

use App\Enums\ListPopularityTier;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Traits\Livewire\ModeratesAddon;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component
{
    use ModeratesAddon;
    use ModeratesMod;

    /**
     * The mod being shown.
     */
    public Mod $mod;

    /**
     * The Open Graph image for social media sharing.
     */
    public ?string $openGraphImage = null;

    /**
     * Mount the component.
     */
    public function mount(int $modId, string $slug): void
    {
        $this->mod = $this->getMod($modId);

        $this->enforceCanonicalSlug($this->mod, $slug);

        Gate::authorize('view', $this->mod);

        $this->openGraphImage = $this->mod->thumbnail;
    }

    /**
     * Check if the current user should see warnings about this mod.
     */
    public function shouldShowWarnings(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Only show warnings to privileged users (owners, authors, mods, admins)
        if ($user->isModOrAdmin()) {
            return true;
        }

        return $this->mod->isAuthorOrOwner($user);
    }

    /**
     * Get the warning messages for this mod.
     *
     * @return array<string, string>
     */
    public function getWarningMessages(): array
    {
        $warnings = [];

        // Single query for all version counts instead of 3 separate count queries
        $versionCounts = $this->mod->versions()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN published_at IS NOT NULL THEN 1 ELSE 0 END) as published')
            ->selectRaw('SUM(CASE WHEN disabled = false THEN 1 ELSE 0 END) as enabled')
            ->first();

        $total = (int) ($versionCounts->total ?? 0); // @phpstan-ignore cast.int
        $published = (int) ($versionCounts->published ?? 0); // @phpstan-ignore cast.int
        $enabled = (int) ($versionCounts->enabled ?? 0); // @phpstan-ignore cast.int

        if ($total === 0) {
            $warnings['no_versions'] = 'This mod has no versions. Users will be unable to view this mod until a version is created.';
        } else {
            if ($published === 0) {
                $warnings['no_published_versions'] = 'This mod has no published versions. Users will be unable to view this mod until a version is published.';
            }

            if ($enabled === 0) {
                $warnings['no_enabled_versions'] = 'This mod has no enabled versions. Users will be unable to view this mod until a version is enabled.';
            }

            if (! $this->hasPublicVersions()) {
                $user = auth()->user();
                $isPrivilegedUser = $user && ($this->mod->isAuthorOrOwner($user) || $user->isModOrAdmin());

                if ($isPrivilegedUser) {
                    $warnings['no_valid_spt_versions'] = 'This mod has no valid published SPT versions. Users will be unable to view this mod until a version with valid SPT compatibility is published and enabled.';
                }
            }
        }

        if (! $this->mod->published_at) {
            $warnings['unpublished'] = 'This mod is unpublished. Users will be unable to view this mod until it is published.';
        }

        if ($this->mod->disabled) {
            $warnings['disabled'] = 'This mod is disabled. Users will be unable to view this mod until it is enabled.';
        }

        return $warnings;
    }

    /**
     * Get the total version count visible to the current user.
     */
    public function getVersionCount(): int
    {
        $canViewAny =
            auth()
                ->user()
                ?->can('viewAny', [ModVersion::class, $this->mod]) ?? false;

        if ($canViewAny) {
            return $this->mod->versions()->count();
        }

        // Count both modern and legacy publicly visible versions
        $modernCount = $this->mod->versions()->publiclyVisible()->count();
        $legacyCount = $this->mod->versions()->legacyPubliclyVisible()->count();

        return $modernCount + $legacyCount;
    }

    /**
     * Get the total comment count visible to the current user.
     */
    public function getCommentCount(): int
    {
        $user = auth()->user();

        return $this->mod->comments()->visibleToUser($user)->count();
    }

    /**
     * Get the total addon count visible to the current user.
     */
    public function getAddonCount(): int
    {
        $user = auth()->user();

        return $this->mod
            ->addons()
            ->when(! $user?->isModOrAdmin(), function (Builder $query): void {
                $query->where('disabled', false)->whereNotNull('published_at')->where('published_at', '<=', now());
            })
            ->whereNull('detached_at')
            ->count();
    }

    /**
     * Check if the mod should display a profile binding notice.
     */
    public function requiresProfileBindingNotice(): bool
    {
        // If the notice is explicitly disabled, don't show it
        if ($this->mod->profile_binding_notice_disabled) {
            return false;
        }

        // Otherwise, check if the category shows profile binding notice
        return $this->mod->category && $this->mod->category->shows_profile_binding_notice;
    }

    /**
     * Check if the mod should display the cheat notice.
     */
    public function requiresCheatNotice(): bool
    {
        return $this->mod->cheat_notice;
    }

    /**
     * Get the display version - prefers modern version but falls back to legacy.
     */
    public function getDisplayVersion(): ?ModVersion
    {
        if ($this->mod->latestVersion) {
            return $this->mod->latestVersion;
        }

        return $this->mod->latestLegacyVersion;
    }

    /**
     * Get view data.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $displayVersion = $this->getDisplayVersion();

        return [
            'mod' => $this->mod,
            'displayVersion' => $displayVersion,
            'shouldShowWarnings' => $this->shouldShowWarnings(),
            'warningMessages' => $this->getWarningMessages(),
            'requiresProfileBindingNotice' => $this->requiresProfileBindingNotice(),
            'requiresCheatNotice' => $this->requiresCheatNotice(),
            'versionCount' => $this->getVersionCount(),
            'commentCount' => $this->getCommentCount(),
            'addonCount' => $this->getAddonCount(),
            'fikaStatus' => $this->mod->getOverallFikaCompatibility(),
            'presenceSummary' => $this->getPresenceSummary(),
        ];
    }

    /**
     * Build the list-presence blurb shown at the bottom of the mod detail sidebar.
     *
     * The wording adapts to which count is non-zero, and a tiered remark, chosen at random on every render, is appended
     * based on the combined total of lists and favourites. Returns null when the mod is in no lists and has no
     * favourites, so the sidebar can omit the container entirely rather than render an empty-state line.
     *
     * @return array{sentence: string, flavour: string}|null
     */
    protected function getPresenceSummary(): ?array
    {
        ['lists' => $lists, 'favourites' => $favourites] = $this->getListPresenceCounts();

        if ($lists === 0 && $favourites === 0) {
            return null;
        }

        return [
            'sentence' => $this->buildPresenceSentence($lists, $favourites),
            'flavour' => ListPopularityTier::fromTotal($lists + $favourites)->randomSaying(),
        ];
    }

    /**
     * Get the count of non-favourite lists and favourites the mod actively appears in.
     *
     * Lists of every visibility count toward the list total, excluding only the per-user Favourites lists, which are
     * counted separately, and moderator-disabled lists. Tombstoned items are ignored via the active scope. The counts
     * feed a light-hearted blurb that tolerates mild staleness, so they are cached for fifteen minutes rather than
     * recomputed on every request; only the two integers are cached, while the sentence and remark rebuild per render.
     *
     * @return array{lists: int, favourites: int}
     */
    protected function getListPresenceCounts(): array
    {
        return Cache::remember(
            sprintf('mod:%d:list-presence-counts', $this->mod->id),
            now()->addMinutes(15),
            fn (): array => [
                'lists' => $this->mod->listItems()
                    ->active()
                    ->whereHas('modList', fn (Builder $query): Builder => $query
                        ->where('is_default', false)
                        ->where('disabled', false))
                    ->count(),
                'favourites' => $this->mod->listItems()
                    ->active()
                    ->whereHas('modList', fn (Builder $query): Builder => $query
                        ->where('is_default', true)
                        ->where('disabled', false))
                    ->count(),
            ],
        );
    }

    /**
     * Compose the count-driven sentence, pluralizing each clause and dropping any clause whose count is zero. At least
     * one of the two counts is always non-zero here; the empty case is handled earlier by omitting the container.
     */
    protected function buildPresenceSentence(int $lists, int $favourites): string
    {
        if ($favourites === 0) {
            return __('This mod is featured in :lists.', [
                'lists' => trans_choice(':count list|:count lists', $lists, ['count' => $lists]),
            ]);
        }

        if ($lists === 0) {
            return __('This mod has been favourited :favourites.', [
                'favourites' => trans_choice(':count time|:count times', $favourites, ['count' => $favourites]),
            ]);
        }

        return __('This mod is featured in :lists and favourited :favourites.', [
            'lists' => trans_choice(':count list|:count lists', $lists, ['count' => $lists]),
            'favourites' => trans_choice(':count time|:count times', $favourites, ['count' => $favourites]),
        ]);
    }

    /**
     * Get the mod by ID.
     */
    protected function getMod(int $modId): Mod
    {
        return Mod::query()
            ->with(['sourceCodeLinks', 'category', 'owner', 'additionalAuthors', 'license', 'latestVersion.latestSptVersion', 'latestVersion.latestDependenciesResolved.mod:id,name,slug,thumbnail,thumbnail_hash,owner_id', 'latestVersion.latestDependenciesResolved.mod.owner.role', 'latestLegacyVersion'])
            ->findOrFail($modId);
    }

    /**
     * Redirect to the canonical slug route if the given slug is incorrect.
     */
    protected function enforceCanonicalSlug(Mod $mod, string $slug): void
    {
        if ($mod->slug !== $slug) {
            $this->redirectRoute('mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ]);
        }
    }

    /**
     * Check if a mod has versions which are publicly visible versions. This determines if the mod should show warnings
     * to privileged users about regular user visibility.
     */
    private function hasPublicVersions(): bool
    {
        // Check for modern versions with SPT compatibility
        $hasModernVersion = $this->mod->versions()->publiclyVisible()->exists();

        if ($hasModernVersion) {
            return true;
        }

        // Check for legacy versions (no SPT constraint)
        return $this->mod->versions()->legacyPubliclyVisible()->exists();
    }
};
