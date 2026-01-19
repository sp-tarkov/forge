<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Traits\Livewire\ModeratesModVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component {
    use ModeratesModVersion;
    use WithPagination;

    /**
     * The mod ID.
     */
    public int $modId;

    /**
     * Mount the component.
     */
    public function mount(int $modId): void
    {
        $this->modId = $modId;
    }

    /**
     * Get the mod.
     */
    #[Computed]
    public function mod(): Mod
    {
        return Mod::query()->findOrFail($this->modId);
    }

    /**
     * The mod's versions.
     *
     * @return LengthAwarePaginator<int, ModVersion>
     */
    #[Computed]
    public function versions(): LengthAwarePaginator
    {
        $user = auth()->user();

        return $this->mod
            ->versions()
            ->with(['latestSptVersion', 'sptVersions', 'latestResolvedDependencies.mod:id,name,slug'])
            ->unless($user?->can('viewAny', [ModVersion::class, $this->mod]), function (Builder $query): void {
                $query->publiclyVisible();
            })
            ->withCount([
                'compatibleAddonVersions as compatible_addons_count' => function (Builder $query) use ($user): void {
                    // Only count published, enabled addons for non-privileged users
                    $query->whereHas('addon', function (Builder $addonQuery) use ($user): void {
                        $addonQuery->whereNull('detached_at');

                        if (!$user?->isModOrAdmin()) {
                            $addonQuery->where('disabled', false)->whereNotNull('published_at')->where('published_at', '<=', now());
                        }
                    });
                },
            ])
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
        @cachedCan('view', $version)
            <div wire:key="mod-show-version-{{ $this->mod->id }}-{{ $version->id }}">
                <x-mod.version-card :version="$version" />
            </div>
        @endcachedCan
    @empty
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="text-center py-8">
                <flux:icon.archive-box class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('No Versions Yet') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('This mod doesn\'t have any versions yet.') }}</p>
                @cachedCan('create', [App\Models\ModVersion::class, $this->mod])
                    <div class="mt-6">
                        <flux:button href="{{ route('mod.version.create', ['mod' => $this->mod->id]) }}">
                            {{ __('Create First Version') }}
                        </flux:button>
                    </div>
                @endcachedCan
            </div>
        </div>
    @endforelse
    {{ $this->versions->links() }}
</div>
