<div class="space-y-4">
    {{-- Version card placeholders --}}
    @for ($i = 0; $i < 3; $i++)
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
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
