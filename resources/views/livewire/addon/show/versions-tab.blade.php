<div>
    @forelse($versions as $version)
        <x-addon.version-card
            wire:key="addon-show-version-{{ $addon->id }}-{{ $version->id }}"
            :version="$version"
            :addon="$addon"
        />
    @empty
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <div class="text-center py-8">
                <flux:icon.archive-box class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('No Versions Yet') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('This addon doesn\'t have any versions yet.') }}</p>
                @cachedCan('create', [App\Models\AddonVersion::class, $addon])
                    <div class="mt-6">
                        <flux:button href="{{ route('addon.version.create', ['addon' => $addon->id]) }}">
                            {{ __('Create First Version') }}
                        </flux:button>
                    </div>
                @endcachedCan
            </div>
        </div>
    @endforelse
    {{ $versions->links() }}
</div>
