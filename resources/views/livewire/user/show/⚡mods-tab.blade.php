<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\User;
use App\Traits\Livewire\ModeratesMod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component {
    use ModeratesMod;
    use WithPagination;

    /**
     * The user ID whose mods are being shown.
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
     * Get the mods for the user.
     *
     * @return LengthAwarePaginator<int, Mod>
     */
    #[Computed]
    public function mods(): LengthAwarePaginator
    {
        $user = $this->user;
        $viewer = Auth::user();

        $query = $user
            ->ownedAndAuthoredMods()
            ->with(['owner:id,name', 'additionalAuthors:id,name', 'latestVersion', 'latestVersion.latestSptVersion'])
            ->latest();

        if (!$viewer?->can('viewDisabledUserMods', $user)) {
            $query->whereDisabled(false)->whereHas('versions', function (Builder $versionQuery): void {
                $versionQuery->where('disabled', false)->whereNotNull('published_at');
            });
        }

        return $query->paginate(10)->fragment('mods');
    }
};
?>

@placeholder
    <div
        id="mods"
        class="space-y-4"
    >
        {{-- Pagination placeholder --}}
        <div class="flex justify-center">
            <flux:skeleton class="h-10 w-64 rounded" />
        </div>

        {{-- Mod card placeholders --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @for ($i = 0; $i < 4; $i++)
                <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                    <flux:skeleton.group class="space-y-4">
                        {{-- Thumbnail --}}
                        <flux:skeleton class="h-40 w-full rounded" />

                        {{-- Title and meta --}}
                        <div class="space-y-2">
                            <flux:skeleton class="h-6 w-3/4 rounded" />
                            <flux:skeleton class="h-4 w-1/2 rounded" />
                        </div>

                        {{-- Description --}}
                        <div class="space-y-2">
                            <flux:skeleton class="h-4 w-full rounded" />
                            <flux:skeleton class="h-4 w-5/6 rounded" />
                        </div>

                        {{-- Stats --}}
                        <div class="flex items-center gap-4">
                            <flux:skeleton class="h-4 w-20 rounded" />
                            <flux:skeleton class="h-4 w-16 rounded" />
                        </div>
                    </flux:skeleton.group>
                </div>
            @endfor
        </div>
    </div>
@endplaceholder

<div id="mods">
    @if ($this->mods->count())
        <div class="mb-4">
            {{ $this->mods->links() }}
        </div>
        <div class="my-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($this->mods as $mod)
                <div wire:key="user-show-mod-card-{{ $mod->id }}">
                    <x-mod.card
                        :mod="$mod"
                        :version="$mod->latestVersion"
                        placeholder-bg="bg-gray-200 dark:bg-gray-900"
                    />
                </div>
            @endforeach
        </div>
        <div class="mt-5">
            {{ $this->mods->links() }}
        </div>
    @else
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="text-center py-8">
                <flux:icon.cube-transparent class="mx-auto size-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('No Mods Yet') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('This user has not yet published any mods.') }}
                </p>
            </div>
        </div>
    @endif
</div>
