@placeholder
    <div class="space-y-4">
        {{-- Version card placeholders --}}
        @for ($i = 0; $i < 3; $i++)
            <div class="p-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl">
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
                <x-mod.version-card :version="$version" :latest-version-id="$this->latestVersionId" />
            </div>
        @endcachedCan
    @empty
        <div class="p-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl">
            <div class="text-center py-8">
                <flux:icon.archive-box class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-100">
                    {{ __('No Versions Yet') }}</h3>
                <p class="mt-1 text-sm text-gray-400">
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