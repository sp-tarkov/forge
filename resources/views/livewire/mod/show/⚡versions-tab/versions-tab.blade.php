@placeholder
    <div class="space-y-4">
        {{-- Version card placeholders --}}
        @for ($i = 0; $i < 3; $i++)
            <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
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
                <x-mod.version-card
                    :version="$version"
                    :latest-version-id="$this->latestVersionId"
                />
            </div>
        @endcachedCan
    @empty
        <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
            <div class="py-8 text-center">
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
