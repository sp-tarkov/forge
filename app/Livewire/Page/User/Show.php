<?php

declare(strict_types=1);

namespace App\Livewire\Page\User;

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use App\Traits\Livewire\ModeratesAddon;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use ModeratesAddon;
    use ModeratesMod;
    use WithPagination;

    /**
     * The user being viewed.
     */
    public User $user;

    /**
     * Mount the component.
     */
    public function mount(int $userId, string $slug): void
    {
        $this->user = $this->getUser($userId);

        $this->enforceCanonicalSlug($this->user, $slug);

        Gate::authorize('view', $this->user);
    }

    /**
     * Mount the component.
     */
    public function getUser(int $userId): User
    {
        return User::with(['following', 'followers'])->findOrFail($userId);
    }

    /**
     * Get the total mod count visible to the current user.
     */
    public function getModCount(): int
    {
        $viewer = Auth::user();

        $query = $this->user->ownedAndAuthoredMods();

        if (! $viewer?->can('viewDisabledUserMods', $this->user)) {
            $query->whereDisabled(false)
                ->whereHas('versions', function (Builder $versionQuery): void {
                    $versionQuery->where('disabled', false)->whereNotNull('published_at');
                });
        }

        return $query->count();
    }

    /**
     * Get the total addon count visible to the current user.
     */
    public function getAddonCount(): int
    {
        $viewer = Auth::user();

        $query = $this->user->ownedAndAuthoredAddons();

        if (! $viewer?->can('viewDisabledUserAddons', $this->user)) {
            $query->where('addons.disabled', false)
                ->whereNotNull('addons.published_at')
                ->where('addons.published_at', '<=', now());
        }

        return $query->count();
    }

    /**
     * Render the user profile view.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.user.show', [
            'user' => $this->user,
            'mods' => $this->getUserMods(),
            'addons' => $this->getUserAddons(),
            'openGraphImage' => $this->user->profile_photo_path,
            'modCount' => $this->getModCount(),
            'addonCount' => $this->getAddonCount(),
        ]);
    }

    /**
     * Get the mods for the user.
     *
     * @return LengthAwarePaginator<int, Mod>
     */
    protected function getUserMods(): LengthAwarePaginator
    {
        $viewer = Auth::user();

        $query = $this->user->ownedAndAuthoredMods()
            ->with([
                'owner:id,name',
                'authors:id,name',
                'latestVersion',
                'latestVersion.latestSptVersion',
            ])
            ->latest();

        if (! $viewer?->can('viewDisabledUserMods', $this->user)) {
            $query->whereDisabled(false)
                ->whereHas('versions', function (Builder $versionQuery): void {
                    $versionQuery->where('disabled', false)->whereNotNull('published_at');
                });
        }

        return $query->paginate(10)
            ->fragment('mods');
    }

    /**
     * Get the addons for the user.
     *
     * @return LengthAwarePaginator<int, Addon>
     */
    protected function getUserAddons(): LengthAwarePaginator
    {
        $viewer = Auth::user();

        $query = $this->user->ownedAndAuthoredAddons()
            ->with([
                'owner',
                'authors',
                'latestVersion',
                'mod:id,name,slug',
                'mod.latestVersion:id,mod_id,version_major,version_minor,version_patch',
                'versions.compatibleModVersions',
            ])
            ->orderBy('addons.downloads', 'desc');

        if (! $viewer?->can('viewDisabledUserAddons', $this->user)) {
            $query->where('addons.disabled', false)
                ->whereNotNull('addons.published_at')
                ->where('addons.published_at', '<=', now());
        }

        return $query->paginate(perPage: 10, pageName: 'addonPage')
            ->fragment('addons');
    }

    /**
     * Redirect to the canonical slug route if the given slug is incorrect.
     */
    protected function enforceCanonicalSlug(User $user, string $slug): void
    {
        if ($user->slug !== $slug) {
            $this->redirectRoute('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]);
        }
    }
}
