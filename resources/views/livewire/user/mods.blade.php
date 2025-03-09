<div>
    @if ($this->mods->count())
        <div class="mb-4">
            {{ $this->mods->links() }}
        </div>
        <div class="my-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($this->mods as $mod)
                <div wire:key="mod-card-{{ $mod->id }}">
                    <x-mod-card :mod="$mod" :version="$mod->latestVersion" />
                </div>
            @endforeach
        </div>
        <div class="mt-5">
            {{ $this->mods->links() }}
        </div>
    @else
        <p class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 text-gray-800 dark:text-gray-200 drop-shadow-2xl">
            {{ __('This user has not yet published any mods.') }}
        </p>
    @endif
</div>
