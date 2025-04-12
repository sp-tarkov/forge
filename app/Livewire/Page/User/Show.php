<?php

declare(strict_types=1);

namespace App\Livewire\Page\User;

use App\Models\Mod;
use App\Models\User;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
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
     * Get the mods for the user.
     *
     * @return LengthAwarePaginator<int, Mod>
     */
    protected function getUserMods(): LengthAwarePaginator
    {
        $query = $this->user->mods()
            ->with([
                'owner:id,name',
                'authors:id,name',
                'latestVersion',
                'latestVersion.latestSptVersion',
            ])
            ->orderByDesc('created_at');

        $query->unless(
            request()->user()?->can('view-disabled-user-mods', $this->user),
            fn ($q) => $q
                ->whereDisabled(false)
                ->whereHas('latestVersion')
        );

        return $query->paginate(10)
            ->fragment('mods');
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

    /**
     * Render the user profile view.
     */
    public function render(): View
    {
        return view('livewire.page.user.show', [
            'user' => $this->user,
            'mods' => $this->getUserMods(),
        ]);
    }
}
