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
                <div class="p-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl">
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
        <div class="p-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl">
            <div class="text-center py-8">
                <flux:icon.puzzle-piece class="mx-auto size-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-100">
                    {{ __('No Addons Yet') }}
                </h3>
                <p class="mt-1 text-sm text-gray-400">
                    {{ __('This user has not yet published any addons.') }}
                </p>
            </div>
        </div>
    @endif
</div>