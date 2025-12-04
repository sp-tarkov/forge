<?php

declare(strict_types=1);

namespace App\Livewire\Page\Mod;

use App\Models\Mod;
use App\Models\ModVersion;
use App\Traits\Livewire\ModeratesAddon;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
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
     * Render the component.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.mod.show', [
            'mod' => $this->mod,
            'shouldShowWarnings' => $this->shouldShowWarnings(),
            'warningMessages' => $this->getWarningMessages(),
            'requiresProfileBindingNotice' => $this->requiresProfileBindingNotice(),
            'versionCount' => $this->getVersionCount(),
            'commentCount' => $this->getCommentCount(),
            'addonCount' => $this->getAddonCount(),
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
            'additionalAuthors',
            'license',
            'latestVersion.latestSptVersion',
            'latestVersion.latestResolvedDependencies.mod:id,name,slug,thumbnail,thumbnail_hash,owner_id',
            'latestVersion.latestResolvedDependencies.mod.owner.role',
        ])->findOrFail($modId);
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
