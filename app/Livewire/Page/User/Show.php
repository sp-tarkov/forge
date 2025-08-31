<?php

declare(strict_types=1);

namespace App\Livewire\Page\User;

use App\Models\Mod;
use App\Models\User;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
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
     * The OpenGraph image for the mod.
     */
    public string $openGraphImage;

    /**
     * Mount the component.
     */
    public function mount(int $userId, string $slug): void
    {
        $this->user = $this->getUser($userId);

        $this->enforceCanonicalSlug($this->user, $slug);

        $this->openGraphImage = $this->user->profile_photo_path ?? '';

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
            fn (Builder $q): Builder => $q
                ->whereDisabled(false)
                ->whereHas('versions', function (Builder $versionQuery): void {
                    $versionQuery->where('disabled', false)->whereNotNull('published_at');
                })
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
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.user.show', [
            'user' => $this->user,
            'mods' => $this->getUserMods(),
        ]);
    }
}
