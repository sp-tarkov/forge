<div id="addons">
    @if ($addons->count())
        <div class="mb-4">
            {{ $addons->links() }}
        </div>
        <div class="grid gap-4">
            @foreach ($addons as $addon)
                <x-addon.card
                    :addon="$addon"
                    wire:key="user-addon-card-{{ $addon->id }}"
                />
            @endforeach
        </div>
        <div class="mt-5">
            {{ $addons->links() }}
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
