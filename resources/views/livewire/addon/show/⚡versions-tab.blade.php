<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Traits\Livewire\ModeratesAddon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component {
    use ModeratesAddon;
    use WithPagination;

    /**
     * The addon ID.
     */
    public int $addonId;

    /**
     * Mount the component.
     */
    public function mount(int $addonId): void
    {
        $this->addonId = $addonId;
    }

    /**
     * Get the addon.
     */
    #[Computed]
    public function addon(): Addon
    {
        return Addon::query()->findOrFail($this->addonId);
    }

    /**
     * The addon's versions.
     *
     * @return LengthAwarePaginator<int, AddonVersion>
     */
    #[Computed]
    public function versions(): LengthAwarePaginator
    {
        return $this->addon
            ->versions()
            ->with(['compatibleModVersions'])
            ->unless(
                auth()
                    ->user()
                    ?->can('viewAny', [AddonVersion::class, $this->addon]),
                function (Builder $query): void {
                    $query->where('disabled', false)->whereNotNull('published_at')->where('published_at', '<=', now());
                },
            )
            ->paginate(perPage: 6, pageName: 'versionPage')
            ->fragment('versions');
    }
};
?>

@placeholder
    <div class="space-y-4">
        {{-- Version card placeholders --}}
        @for ($i = 0; $i < 3; $i++)
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <flux:skeleton.group class="space-y-4">
                    {{-- Version header --}}
                    <div class="flex items-center justify-between">
                        <flux:skeleton class="h-6 w-24 rounded" />
                        <flux:skeleton class="h-5 w-20 rounded" />
                    </div>

                    {{-- Description lines --}}
                    <div class="space-y-2">
                        <flux:skeleton class="h-4 w-full rounded" />
                        <flux:skeleton class="h-4 w-3/4 rounded" />
                    </div>

                    {{-- Meta info --}}
                    <div class="flex items-center gap-4">
                        <flux:skeleton class="h-4 w-32 rounded" />
                        <flux:skeleton class="h-4 w-24 rounded" />
                    </div>
                </flux:skeleton.group>
            </div>
        @endfor
    </div>
@endplaceholder

<div>
    @forelse($this->versions as $version)
        <x-addon.version-card
            wire:key="addon-show-version-{{ $this->addon->id }}-{{ $version->id }}"
            :version="$version"
            :addon="$this->addon"
        />
    @empty
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="text-center py-8">
                <flux:icon.archive-box class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('No Versions Yet') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('This addon doesn\'t have any versions yet.') }}</p>
                @cachedCan('create', [App\Models\AddonVersion::class, $this->addon])
                    <div class="mt-6">
                        <flux:button href="{{ route('addon.version.create', ['addon' => $this->addon->id]) }}">
                            {{ __('Create First Version') }}
                        </flux:button>
                    </div>
                @endcachedCan
            </div>
        </div>
    @endforelse
    {{ $this->versions->links() }}
</div>
