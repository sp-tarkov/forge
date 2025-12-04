<div>
    {{-- Version card skeleton --}}
    @for ($i = 0; $i < 3; $i++)
        <div
            class="relative p-4 mb-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <flux:skeleton.group animate="shimmer">
                <div class="pb-6 border-b-2 border-gray-200 dark:border-gray-800">
                    <div class="flex flex-col items-start sm:flex-row sm:justify-between">
                        <div class="flex flex-col flex-1">
                            {{-- Version title --}}
                            <flux:skeleton.line
                                size="lg"
                                class="mb-3 w-48"
                            />
                            {{-- Version badges row --}}
                            <div class="mt-3 flex flex-row justify-start items-center gap-2.5">
                                <flux:skeleton class="h-5 w-16 rounded" />
                                <flux:skeleton class="h-4 w-12 rounded" />
                                <flux:skeleton class="h-4 w-24 rounded" />
                            </div>
                        </div>
                        <div class="flex flex-col items-start sm:items-end mt-4 sm:mt-0">
                            {{-- Release date --}}
                            <flux:skeleton.line class="w-32" />
                            {{-- Fika status --}}
                            <div class="mt-2 flex items-center gap-1">
                                <flux:skeleton class="size-4 rounded" />
                                <flux:skeleton.line class="w-24" />
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Description skeleton --}}
                <div class="pt-3 space-y-2">
                    <flux:skeleton.line class="w-full" />
                    <flux:skeleton.line class="w-full" />
                    <flux:skeleton.line class="w-3/4" />
                </div>
            </flux:skeleton.group>
        </div>
    @endfor
</div>
