@placeholder
    <div
        id="activity"
        class="rounded-xl bg-gray-950 p-4 text-gray-200 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6"
    >
        <flux:skeleton.group class="space-y-4">
            {{-- Activity items --}}
            @for ($i = 0; $i < 5; $i++)
                <div class="flex items-start gap-3">
                    <flux:skeleton class="h-8 w-8 shrink-0 rounded-full" />
                    <div class="flex-1 space-y-2">
                        <flux:skeleton class="h-4 w-3/4 rounded" />
                        <flux:skeleton class="h-3 w-24 rounded" />
                    </div>
                </div>
            @endfor
        </flux:skeleton.group>
    </div>
@endplaceholder

<div
    id="activity"
    class="rounded-xl bg-gray-950 p-4 text-gray-200 shadow-md shadow-gray-950 drop-shadow-2xl sm:p-6"
>
    <livewire:user-activity :user="$this->user" />
</div>
