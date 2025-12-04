<div
    id="mods"
    class="space-y-4"
>
    {{-- Pagination placeholder --}}
    <div class="flex justify-center">
        <flux:skeleton class="h-10 w-64 rounded" />
    </div>

    {{-- Mod card placeholders --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @for ($i = 0; $i < 4; $i++)
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <flux:skeleton.group class="space-y-4">
                    {{-- Thumbnail --}}
                    <flux:skeleton class="h-40 w-full rounded" />

                    {{-- Title and meta --}}
                    <div class="space-y-2">
                        <flux:skeleton class="h-6 w-3/4 rounded" />
                        <flux:skeleton class="h-4 w-1/2 rounded" />
                    </div>

                    {{-- Description --}}
                    <div class="space-y-2">
                        <flux:skeleton class="h-4 w-full rounded" />
                        <flux:skeleton class="h-4 w-5/6 rounded" />
                    </div>

                    {{-- Stats --}}
                    <div class="flex items-center gap-4">
                        <flux:skeleton class="h-4 w-20 rounded" />
                        <flux:skeleton class="h-4 w-16 rounded" />
                    </div>
                </flux:skeleton.group>
            </div>
        @endfor
    </div>
</div>
