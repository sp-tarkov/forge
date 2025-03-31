<?php

declare(strict_types=1);

namespace App\Livewire\Page\User;

use App\Models\Mod;
use App\Models\User;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use ModeratesMod;

    /**
     * The user being viewed.
     */
    public User $user;

    /**
     * Mount the component.
     */
    public function mount(int $userId, string $slug): ?RedirectResponse
    {
        $this->user = User::with(['following', 'followers'])->findOrFail($userId);

        if ($response = $this->enforceCanonicalSlug($slug)) {
            return $response;
        }

        Gate::authorize('view', $this->user);

        return null;
    }

    /**
     * Get the mods for the user.
     *
     * @return LengthAwarePaginator<Mod>
     */
    protected function userMods(): LengthAwarePaginator
    {
        $query = $this->user->mods()
            ->with([
                'users',
                'latestVersion',
                'latestVersion.latestSptVersion',
            ])
            ->orderByDesc('created_at');

        $query->unless(request()->user()?->can('view-disabled-user-mods', $this->user), fn ($q) => $q
            ->whereDisabled(false))
            ->whereHas('latestVersion');

        return $query->paginate(10)
            ->fragment('mods');
    }

    /**
     * Redirect to the canonical slug route if the given slug is incorrect.
     */
    protected function enforceCanonicalSlug(string $slug): ?RedirectResponse
    {
        if ($this->user->slug !== $slug) {
            $this->redirectRoute('user.show', [
                'userId' => $this->user->id,
                'slug' => $this->user->slug,
            ]);
        }

        return null;
    }

    /**
     * Render the user profile view.
     */
    public function render(): View
    {
        return view('livewire.page.user.show', [
            'user' => $this->user,
            'mods' => $this->userMods(),
        ]);
    }
}
