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
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
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
