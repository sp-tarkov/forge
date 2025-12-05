<div>
    {{-- Filter bar skeleton --}}
    <div class="mb-4 flex items-center justify-between gap-4">
        <flux:skeleton.group animate="shimmer">
            <flux:skeleton.line class="w-48" />
        </flux:skeleton.group>
        <div class="flex items-center gap-3">
            <flux:skeleton.group animate="shimmer">
                <flux:skeleton.line class="w-32" />
                <flux:skeleton class="h-8 w-36 rounded-md" />
            </flux:skeleton.group>
        </div>
    </div>

    {{-- Addon card skeletons --}}
    <div class="grid gap-4">
        @for ($i = 0; $i < 3; $i++)
            <div
                class="bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl overflow-hidden">
                <div class="p-4 sm:p-6">
                    <flux:skeleton.group animate="shimmer">
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                            {{-- Thumbnail skeleton --}}
                            <flux:skeleton
                                class="w-20 h-20 sm:w-16 sm:h-16 md:w-20 md:h-20 rounded-lg flex-shrink-0 mx-auto sm:mx-0"
                            />

                            {{-- Content skeleton --}}
                            <div class="flex-1 min-w-0">
                                {{-- Title --}}
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2">
                                    <flux:skeleton.line
                                        size="lg"
                                        class="w-48 mb-2 sm:mb-0"
                                    />
                                </div>

                                {{-- Info row --}}
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                                    <div class="flex-1">
                                        <flux:skeleton.line class="w-32 mb-1" />
                                        <flux:skeleton.line class="w-24" />
                                    </div>
                                    {{-- Version badges --}}
                                    <div class="sm:text-right">
                                        <flux:skeleton.line class="w-36 mb-1" />
                                        <div class="flex flex-wrap gap-1 justify-center sm:justify-end">
                                            <flux:skeleton class="h-5 w-14 rounded" />
                                            <flux:skeleton class="h-5 w-14 rounded" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Teaser skeleton --}}
                        <div class="mt-4 pt-3 border-t-2 border-gray-300 dark:border-gray-800">
                            <flux:skeleton.line class="w-full" />
                            <flux:skeleton.line class="w-3/4" />
                        </div>
                    </flux:skeleton.group>
                </div>
            </div>
        @endfor
    </div>
</div>
