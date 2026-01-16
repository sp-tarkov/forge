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

        return $query->paginate(perPage: 10, pageName: 'addonPage')->fragment('addons');
    }
};
?>

@placeholder
    <div
        id="addons"
        class="space-y-4"
    >
        {{-- Pagination placeholder --}}
        <div class="flex justify-center">
            <flux:skeleton class="h-10 w-64 rounded" />
        </div>

        {{-- Addon card placeholders --}}
        <div class="grid gap-4">
            @for ($i = 0; $i < 3; $i++)
                <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                    <flux:skeleton.group class="space-y-3">
                        {{-- Header row --}}
                        <div class="flex items-center justify-between">
                            <flux:skeleton class="h-6 w-48 rounded" />
                            <flux:skeleton class="h-5 w-24 rounded" />
                        </div>

                        {{-- Description --}}
                        <div class="space-y-2">
                            <flux:skeleton class="h-4 w-full rounded" />
                            <flux:skeleton class="h-4 w-4/5 rounded" />
                        </div>

                        {{-- Meta info --}}
                        <div class="flex items-center gap-4">
                            <flux:skeleton class="h-4 w-32 rounded" />
                            <flux:skeleton class="h-4 w-24 rounded" />
                            <flux:skeleton class="h-4 w-20 rounded" />
                        </div>
                    </flux:skeleton.group>
                </div>
            @endfor
        </div>
    </div>
@endplaceholder

<div id="addons">
    @if ($this->addons->count())
        <div class="mb-4">
            {{ $this->addons->links() }}
        </div>
        <div class="grid gap-4">
            @foreach ($this->addons as $addon)
                <x-addon.card
                    :addon="$addon"
                    wire:key="user-addon-card-{{ $addon->id }}"
                />
            @endforeach
        </div>
        <div class="mt-5">
            {{ $this->addons->links() }}
        </div>
    @else
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="text-center py-8">
                <flux:icon.puzzle-piece class="mx-auto size-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('No Addons Yet') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('This user has not yet published any addons.') }}
                </p>
            </div>
        </div>
    @endif
</div>
