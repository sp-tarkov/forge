@placeholder
    <div
        id="wall"
        class="space-y-6"
    >
        {{-- Comment form placeholder --}}
        <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
            <flux:skeleton.group class="space-y-4">
                <flux:skeleton class="h-24 w-full rounded" />
                <div class="flex justify-end">
                    <flux:skeleton class="h-10 w-32 rounded" />
                </div>
            </flux:skeleton.group>
        </div>

        {{-- Comment placeholders --}}
        @for ($i = 0; $i < 3; $i++)
            <div class="rounded-xl bg-gray-950 p-4 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6">
                <flux:skeleton.group class="space-y-3">
                    {{-- Comment header --}}
                    <div class="flex items-center gap-3">
                        <flux:skeleton class="h-10 w-10 rounded-full" />
                        <div class="space-y-1">
                            <flux:skeleton class="h-4 w-24 rounded" />
                            <flux:skeleton class="h-3 w-16 rounded" />
                        </div>
                    </div>

                    {{-- Comment content --}}
                    <div class="pl-13 space-y-2">
                        <flux:skeleton class="h-4 w-full rounded" />
                        <flux:skeleton class="h-4 w-5/6 rounded" />
                        <flux:skeleton class="h-4 w-2/3 rounded" />
                    </div>
                </flux:skeleton.group>
            </div>
        @endfor
    </div>
@endplaceholder

<div id="wall">
    <livewire:comment-component
        wire:key="comment-component-{{ $this->user->id }}"
        :commentable="$this->user"
    />
</div>
