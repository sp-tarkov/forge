<?php

declare(strict_types=1);

namespace App\Livewire\User\Show;

use App\Models\Addon;
use App\Models\User;
use App\Traits\Livewire\ModeratesAddon;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class AddonsTab extends Component
{
    use ModeratesAddon;
    use WithPagination;

    /**
     * The user ID whose addons are being shown.
     */
    public int $userId;

    /**
     * Mount the component.
     */
    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Render the placeholder while loading.
     */
    public function placeholder(): View
    {
        return view('livewire.user.show.addons-tab-placeholder');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $user = User::query()->findOrFail($this->userId);

        return view('livewire.user.show.addons-tab', [
            'user' => $user,
            'addons' => $this->getUserAddons($user),
        ]);
    }

    /**
     * Get the addons for the user.
     *
     * @return LengthAwarePaginator<int, Addon>
     */
    protected function getUserAddons(User $user): LengthAwarePaginator
    {
        $viewer = Auth::user();

        $query = $user->ownedAndAuthoredAddons()
            ->with([
                'owner',
                'additionalAuthors',
                'latestVersion',
                'mod:id,name,slug',
                'mod.latestVersion:id,mod_id,version_major,version_minor,version_patch',
                'versions.compatibleModVersions',
            ])
            ->orderBy('addons.downloads', 'desc');

        if (! $viewer?->can('viewDisabledUserAddons', $user)) {
            $query->where('addons.disabled', false)
                ->whereNotNull('addons.published_at')
                ->where('addons.published_at', '<=', now());
        }

        return $query->paginate(perPage: 10, pageName: 'addonPage')
            ->fragment('addons');
    }
}
