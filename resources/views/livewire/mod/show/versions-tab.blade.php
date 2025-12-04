<div>
    @forelse($versions as $version)
        @cachedCan('view', $version)
            <div wire:key="mod-show-version-{{ $mod->id }}-{{ $version->id }}">
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
                @cachedCan('create', [App\Models\ModVersion::class, $mod])
                    <div class="mt-6">
                        <flux:button href="{{ route('mod.version.create', ['mod' => $mod->id]) }}">
                            {{ __('Create First Version') }}
                        </flux:button>
                    </div>
                @endcachedCan
            </div>
        </div>
    @endforelse
    {{ $versions->links() }}
</div>
