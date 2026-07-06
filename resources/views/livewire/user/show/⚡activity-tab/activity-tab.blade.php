@placeholder
    <div
        id="activity"
        class="p-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 text-gray-200 drop-shadow-2xl"
    >
        <flux:skeleton.group class="space-y-4">
            {{-- Activity items --}}
            @for ($i = 0; $i < 5; $i++)
                <div class="flex items-start gap-3">
                    <flux:skeleton class="h-8 w-8 rounded-full shrink-0" />
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
    class="p-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 text-gray-200 drop-shadow-2xl"
>
    <livewire:user-activity :user="$this->user" />
</div>