<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\User;
use App\Traits\Livewire\ModeratesAddon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component {
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
     * Get the user.
     */
    #[Computed]
    public function user(): User
    {
        return User::query()->findOrFail($this->userId);
    }

    /**
     * Get the addons for the user.
     *
     * @return LengthAwarePaginator<int, Addon>
     */
    #[Computed]
    public function addons(): LengthAwarePaginator
    {
        $user = $this->user;
        $viewer = Auth::user();

        $query = $user
            ->ownedAndAuthoredAddons()
            ->with(['owner', 'additionalAuthors', 'latestVersion', 'mod:id,name,slug', 'mod.latestVersion:id,mod_id,version_major,version_minor,version_patch', 'versions.compatibleModVersions'])
            ->orderBy('addons.downloads', 'desc');

        if (!$viewer?->can('viewDisabledUserAddons', $user)) {
            $query->where('addons.disabled', false)->whereNotNull('addons.published_at')->where('addons.published_at', '<=', now());
        }

        return $query->paginate(perPage: 10, pageName: 'addonPage')->fragment('addons'); // @phpstan-ignore return.type (Livewire computed property caching)
    }
};
