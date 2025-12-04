<div id="mods">
    @if ($mods->count())
        <div class="mb-4">
            {{ $mods->links() }}
        </div>
        <div class="my-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($mods as $mod)
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
            {{ $mods->links() }}
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
