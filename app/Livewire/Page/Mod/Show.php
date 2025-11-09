<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Traits\Livewire\ModeratesAddon;
use App\Traits\Livewire\ModeratesMod;
use App\Traits\Livewire\ModeratesModVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use ModeratesAddon;
    use ModeratesMod;
    use ModeratesModVersion;
    use WithPagination;

    /**
     * The mod being shown.
     */
    public Mod $mod;

    /**
     * The Open Graph image for social media sharing.
     */
    public ?string $openGraphImage = null;

    /**
     * The selected mod version filter for addons.
     */
    public ?int $selectedModVersionId = null;

    /**
     * Mount the component.
     */
    public function mount(int $modId, string $slug): void
    {
        $this->mod = $this->getMod($modId);

        $this->enforceCanonicalSlug($this->mod, $slug);

        Gate::authorize('view', $this->mod);

        $this->openGraphImage = $this->mod->thumbnail;

        // Handle version filter query parameter
        if (request()->has('versionFilter')) {
            $this->selectedModVersionId = (int) request('versionFilter');
        }
    }

    /**
     * Reset pagination when mod version filter changes.
     */
    public function updatedSelectedModVersionId(): void
    {
        $this->resetPage('addonPage');
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
        return $user->isModOrAdmin() || $this->mod->isAuthorOrOwner($user);
    }

    /**
     * Get the warning messages for this mod.
     *
     * @return array<string, string>
     */
    public function getWarningMessages(): array
    {
        $warnings = [];

        // Check if the mod has no versions at all
        if ($this->mod->versions()->count() === 0) {
            $warnings['no_versions'] = 'This mod has no versions. Users will be unable to view this mod until a version is created.';
        } else {
            // Check if the mod has no published versions
            $publishedVersions = $this->mod->versions()->whereNotNull('published_at')->count();
            $enabledVersions = $this->mod->versions()->where('disabled', false)->count();

            if ($publishedVersions === 0) {
                $warnings['no_published_versions'] = 'This mod has no published versions. Users will be unable to view this mod until a version is published.';
            }

            if ($enabledVersions === 0) {
                $warnings['no_enabled_versions'] = 'This mod has no enabled versions. Users will be unable to view this mod until a version is enabled.';
            }

            // Check if the mod has no publicly visible versions (only if there are versions)
            if (! $this->hasPublicVersions()) {
                $user = auth()->user();
                $isPrivilegedUser = $user && ($this->mod->isAuthorOrOwner($user) || $user->isModOrAdmin());

                if ($isPrivilegedUser) {
                    $warnings['no_valid_spt_versions'] = 'This mod has no valid published SPT versions. Users will be unable to view this mod until a version with valid SPT compatibility is published and enabled.';
                }
            }
        }

        // Check if the mod itself is unpublished
        if (! $this->mod->published_at) {
            $warnings['unpublished'] = 'This mod is unpublished. Users will be unable to view this mod until it is published.';
        }

        // Check if the mod is disabled
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
        return $this->mod->versions()
            ->when(! auth()->user()?->can('viewAny', [ModVersion::class, $this->mod]), function (Builder $query): void {
                $query->publiclyVisible();
            })
            ->count();
    }

    /**
     * Get the total comment count visible to the current user.
     */
    public function getCommentCount(): int
    {
        $user = auth()->user();

        return $this->mod->comments()
            ->visibleToUser($user)
            ->count();
    }

    /**
     * Get the total addon count visible to the current user.
     */
    public function getAddonCount(): int
    {
        $user = auth()->user();

        return $this->mod->addons()
            ->when(! $user?->isModOrAdmin(), function (Builder $query): void {
                $query->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
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
     * Get mod versions for the filter dropdown.
     *
     * @return Collection<int, ModVersion>
     */
    public function getModVersionsForFilter(): Collection
    {
        return $this->mod->versions()
            ->publiclyVisible()
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->get();
    }

    /**
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.mod.show', [
            'mod' => $this->mod,
            'versions' => $this->versions(),
            'shouldShowWarnings' => $this->shouldShowWarnings(),
            'warningMessages' => $this->getWarningMessages(),
            'requiresProfileBindingNotice' => $this->requiresProfileBindingNotice(),
            'versionCount' => $this->getVersionCount(),
            'commentCount' => $this->getCommentCount(),
            'addonCount' => $this->getAddonCount(),
            'addons' => $this->addons(),
            'modVersionsForFilter' => $this->getModVersionsForFilter(),
            'fikaStatus' => $this->mod->getOverallFikaCompatibility(),
        ]);
    }

    /**
     * Get the mod by ID.
     */
    protected function getMod(int $modId): Mod
    {
        return Mod::query()->with([
            'sourceCodeLinks',
            'category',
            'owner',
            'authors',
            'license',
            'latestVersion.latestSptVersion',
            'latestVersion.latestResolvedDependencies.mod:id,name,slug,thumbnail,thumbnail_hash,owner_id',
            'latestVersion.latestResolvedDependencies.mod.owner.role',
        ])->findOrFail($modId);
    }

    /**
     * The mod's versions.
     *
     * @return LengthAwarePaginator<int, ModVersion>
     */
    protected function versions(): LengthAwarePaginator
    {
        $user = auth()->user();

        return $this->mod->versions()
            ->with([
                'latestSptVersion',
                'sptVersions',
                'latestResolvedDependencies.mod:id,name,slug',
            ])
            ->withCount([
                'compatibleAddonVersions as compatible_addons_count' => function (Builder $query) use ($user): void {
                    // Only count published, enabled addons for non-privileged users
                    $query->whereHas('addon', function (Builder $addonQuery) use ($user): void {
                        $addonQuery->whereNull('detached_at');

                        if (! $user?->isModOrAdmin()) {
                            $addonQuery->where('disabled', false)
                                ->whereNotNull('published_at')
                                ->where('published_at', '<=', now());
                        }
                    });
                },
            ])
            ->paginate(perPage: 6, pageName: 'versionPage')
            ->fragment('versions');
    }

    /**
     * The mod's addons.
     *
     * @return LengthAwarePaginator<int, Addon>
     */
    protected function addons(): LengthAwarePaginator
    {
        $user = auth()->user();

        return $this->mod->addons()
            ->with([
                'owner',
                'authors',
                'latestVersion',
                'mod.latestVersion',
                'latestVersion.compatibleModVersions' => fn (Relation $query): mixed => $query->where('mod_id', $this->mod->id)
                    ->orderBy('version_major', 'desc')
                    ->orderBy('version_minor', 'desc')
                    ->orderBy('version_patch', 'desc'),
                // Load ALL compatible mod versions from ALL addon versions
                'versions.compatibleModVersions' => fn (Relation $query): mixed => $query->where('mod_id', $this->mod->id)
                    ->distinct()
                    ->orderBy('version_major', 'desc')
                    ->orderBy('version_minor', 'desc')
                    ->orderBy('version_patch', 'desc'),
            ])
            ->when($this->selectedModVersionId, function (Builder $query): void {
                // Filter addons that have ANY version compatible with the selected mod version
                $query->whereHas('versions', function (Builder $versionQuery): void {
                    $versionQuery->where('disabled', false)
                        ->whereNotNull('published_at')
                        ->whereHas('compatibleModVersions', function (Builder $compatQuery): void {
                            $compatQuery->where('mod_versions.id', $this->selectedModVersionId);
                        });
                });
            })
            ->when(! $user?->isModOrAdmin(), function (Builder $query): void {
                $query->where('disabled', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            })
            ->whereNull('detached_at')
            ->orderBy('downloads', 'desc')
            ->paginate(perPage: 10, pageName: 'addonPage')
            ->fragment('addons');
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
        // Use the scope to check for publicly visible versions
        return $this->mod->versions()->publiclyVisible()->exists();
    }
}
