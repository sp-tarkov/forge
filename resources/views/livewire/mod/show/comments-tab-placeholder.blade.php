<div id="comments">
    {{-- Comment form skeleton --}}
    <div class="p-6 mb-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
        <flux:skeleton.group animate="shimmer">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <flux:skeleton.line
                        size="lg"
                        class="w-24"
                    />
                    <flux:skeleton.line class="w-8" />
                </div>
                <flux:skeleton class="h-8 w-24 rounded-md" />
            </div>
            <div class="flex items-start">
                <flux:skeleton class="size-10 rounded-full mr-3 flex-shrink-0" />
                <div class="flex-1">
                    <flux:skeleton class="h-24 w-full rounded-lg mb-3" />
                    <div class="flex justify-end">
                        <flux:skeleton class="h-9 w-28 rounded-md" />
                    </div>
                </div>
            </div>
        </flux:skeleton.group>
    </div>

    {{-- Comment skeletons --}}
    @for ($i = 0; $i < 3; $i++)
        <div class="p-4 mb-4 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <flux:skeleton.group animate="shimmer">
                <div class="flex items-start gap-3">
                    <flux:skeleton class="size-10 rounded-full flex-shrink-0" />
                    <div class="flex-1 min-w-0">
                        {{-- Comment header --}}
                        <div class="flex items-center gap-2 mb-2">
                            <flux:skeleton.line class="w-24" />
                            <flux:skeleton.line class="w-16" />
                        </div>
                        {{-- Comment body --}}
                        <div class="space-y-2 mb-3">
                            <flux:skeleton.line class="w-full" />
                            <flux:skeleton.line class="w-full" />
                            <flux:skeleton.line class="w-2/3" />
                        </div>
                        {{-- Comment actions --}}
                        <div class="flex items-center gap-4">
                            <flux:skeleton class="h-6 w-16 rounded" />
                            <flux:skeleton class="h-6 w-12 rounded" />
                        </div>
                    </div>
                </div>
            </flux:skeleton.group>
        </div>
    @endfor
</div>
