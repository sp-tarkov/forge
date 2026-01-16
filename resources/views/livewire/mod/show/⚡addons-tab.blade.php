<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component {
    use WithPagination;

    /**
     * The mod ID.
     */
    public int $modId;

    /**
     * The selected mod version filter for addons.
     */
    #[Url(as: 'versionFilter')]
    public ?int $selectedModVersionId = null;

    /**
     * Mount the component.
     */
    public function mount(int $modId, ?int $selectedModVersionId = null): void
    {
        $this->modId = $modId;

        // If URL parameter is set, use it (via #[Url] attribute)
        // Otherwise check for initial value passed from parent
        if ($this->selectedModVersionId === null && $selectedModVersionId !== null) {
            $this->selectedModVersionId = $selectedModVersionId;
        }
    }

    /**
     * Reset pagination when mod version filter changes.
     */
    public function updatedSelectedModVersionId(): void
    {
        $this->resetPage('addonPage');
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
     * Get the addon count.
     */
    #[Computed]
    public function addonCount(): int
    {
        $user = auth()->user();

        return $this->mod
            ->addons()
            ->when(!$user?->isModOrAdmin(), function (Builder $query): void {
                $query->where('disabled', false)->whereNotNull('published_at')->where('published_at', '<=', now());
            })
            ->whereNull('detached_at')
            ->count();
    }

    /**
     * Get mod versions for the filter dropdown.
     *
     * Limited to versions from the last two minor releases to reduce query size.
     *
     * @return Collection<int, ModVersion>
     */
    #[Computed]
    public function modVersionsForFilter(): Collection
    {
        $mod = $this->mod;

        // Get the last 2 distinct minor versions (major.minor combinations)
        $recentMinorVersions = $mod
            ->versions()
            ->select(['version_major', 'version_minor'])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('disabled', false)
            ->whereHas('latestSptVersion')
            ->reorder() // Clear default ordering to avoid GROUP BY conflicts
            ->groupBy(['version_major', 'version_minor'])
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->limit(2)
            ->get();

        if ($recentMinorVersions->isEmpty()) {
            return collect();
        }

        // Get all versions from those minor releases
        return $mod
            ->versions()
            ->select(['id', 'mod_id', 'version', 'version_major', 'version_minor', 'version_patch', 'version_labels'])
            ->with(['latestSptVersion:spt_versions.id,spt_versions.version'])
            ->publiclyVisible()
            ->where(function (Builder $query) use ($recentMinorVersions): void {
                foreach ($recentMinorVersions as $minorVersion) {
                    $query->orWhere(function (Builder $q) use ($minorVersion): void {
                        $q->where('version_major', $minorVersion->version_major)->where('version_minor', $minorVersion->version_minor);
                    });
                }
            })
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderByRaw('CASE WHEN version_labels = ? THEN 0 ELSE 1 END', [''])
            ->orderBy('version_labels')
            ->get();
    }

    /**
     * The mod's addons.
     *
     * @return LengthAwarePaginator<int, Addon>
     */
    #[Computed]
    public function addons(): LengthAwarePaginator
    {
        $mod = $this->mod;
        $user = auth()->user();

        return $mod
            ->addons()
            ->with([
                'owner',
                'additionalAuthors',
                'latestVersion',
                'mod.latestVersion:id,mod_id,version,version_major,version_minor,version_patch',
                'latestVersion.compatibleModVersions' => fn(Relation $query): mixed => $query
                    ->select(['mod_versions.id', 'mod_versions.mod_id', 'mod_versions.version', 'mod_versions.version_major', 'mod_versions.version_minor', 'mod_versions.version_patch'])
                    ->where('mod_id', $mod->id)
                    ->orderBy('version_major', 'desc')
                    ->orderBy('version_minor', 'desc')
                    ->orderBy('version_patch', 'desc'),
            ])
            ->when($this->selectedModVersionId, function (Builder $query): void {
                // Filter addons that have ANY version compatible with the selected mod version
                $query->whereHas('versions', function (Builder $versionQuery): void {
                    $versionQuery
                        ->where('disabled', false)
                        ->whereNotNull('published_at')
                        ->whereHas('compatibleModVersions', function (Builder $compatQuery): void {
                            $compatQuery->where('mod_versions.id', $this->selectedModVersionId);
                        });
                });
            })
            ->when(!$user?->isModOrAdmin(), function (Builder $query): void {
                $query->where('disabled', false)->whereNotNull('published_at')->where('published_at', '<=', now());
            })
            ->whereNull('detached_at')
            ->orderBy('downloads', 'desc')
            ->paginate(perPage: 10, pageName: 'addonPage')
            ->fragment('addons');
    }
};
?>

@placeholder
    <div>
        {{-- Filter bar skeleton --}}
        <div class="mb-4 flex items-center justify-between gap-4">
            <flux:skeleton.group animate="shimmer">
                <flux:skeleton.line class="w-48" />
            </flux:skeleton.group>
            <div class="flex items-center gap-3">
                <flux:skeleton.group animate="shimmer">
                    <flux:skeleton.line class="w-32" />
                    <flux:skeleton class="h-8 w-36 rounded-md" />
                </flux:skeleton.group>
            </div>
        </div>

        {{-- Addon card skeletons --}}
        <div class="grid gap-4">
            @for ($i = 0; $i < 3; $i++)
                <div
                    class="bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden">
                    <div class="p-4 sm:p-6">
                        <flux:skeleton.group animate="shimmer">
                            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                                {{-- Thumbnail skeleton --}}
                                <flux:skeleton
                                    class="w-20 h-20 sm:w-16 sm:h-16 md:w-20 md:h-20 rounded-lg flex-shrink-0 mx-auto sm:mx-0"
                                />

                                {{-- Content skeleton --}}
                                <div class="flex-1 min-w-0">
                                    {{-- Title --}}
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2">
                                        <flux:skeleton.line
                                            size="lg"
                                            class="w-48 mb-2 sm:mb-0"
                                        />
                                    </div>

                                    {{-- Info row --}}
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                                        <div class="flex-1">
                                            <flux:skeleton.line class="w-32 mb-1" />
                                            <flux:skeleton.line class="w-24" />
                                        </div>
                                        {{-- Version badges --}}
                                        <div class="sm:text-right">
                                            <flux:skeleton.line class="w-36 mb-1" />
                                            <div class="flex flex-wrap gap-1 justify-center sm:justify-end">
                                                <flux:skeleton class="h-5 w-14 rounded" />
                                                <flux:skeleton class="h-5 w-14 rounded" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Teaser skeleton --}}
                            <div class="mt-4 pt-3 border-t-2 border-gray-300 dark:border-gray-800">
                                <flux:skeleton.line class="w-full" />
                                <flux:skeleton.line class="w-3/4" />
                            </div>
                        </flux:skeleton.group>
                    </div>
                </div>
            @endfor
        </div>
    </div>
@endplaceholder

<div>
    @if ($this->mod->addons_enabled)
        @if ($this->addonCount > 0)
            {{-- Version Filter --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <span x-show="!$wire.selectedModVersionId">
                        Select a mod version to filter by on the right.
                    </span>
                    <span
                        x-show="$wire.selectedModVersionId"
                        x-cloak
                    >
                        Showing addons compatible with selected version.
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <label
                        for="mod-version-filter"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap"
                    >
                        Filter by mod version:
                    </label>
                    <flux:select
                        wire:model.live="selectedModVersionId"
                        id="mod-version-filter"
                        size="sm"
                    >
                        <flux:select.option value="">All versions</flux:select.option>
                        @foreach ($this->modVersionsForFilter as $version)
                            <flux:select.option value="{{ $version->id }}">
                                v{{ $version->version }}
                                @if ($version->latestSptVersion)
                                    ({{ $version->latestSptVersion->version_formatted }})
                                @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="grid gap-4">
                @foreach ($this->addons as $addon)
                    <x-addon.card
                        :addon="$addon"
                        :selected-mod-version-id="$selectedModVersionId"
                        wire:key="addon-card-{{ $addon->id }}"
                    />
                @endforeach
            </div>
            {{ $this->addons->links() }}
        @else
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <div class="text-center py-8">
                    <flux:icon.puzzle-piece class="mx-auto size-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('No Addons Yet') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('This mod doesn\'t have any addons yet.') }}</p>
                    @cachedCan('create', [App\Models\Addon::class, $this->mod])
                        <div class="mt-6">
                            <flux:button href="{{ route('addon.guidelines', ['mod' => $this->mod->id]) }}">
                                {{ __('Create First Addon') }}
                            </flux:button>
                        </div>
                    @endcachedCan
                </div>
            </div>
        @endif
    @else
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <flux:callout
                icon="information-circle"
                color="zinc"
            >
                <flux:callout.heading>{{ __('Addons Disabled') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('The mod owner has disabled addons for this mod.') }}
                </flux:callout.text>
            </flux:callout>
        </div>
    @endif
</div>
