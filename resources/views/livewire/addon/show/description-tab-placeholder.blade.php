<div
    class="user-markdown p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
    <flux:skeleton.group class="space-y-4">
        {{-- Title --}}
        <flux:skeleton class="h-8 w-1/3 rounded" />

        {{-- Paragraph lines --}}
        <div class="space-y-2">
            <flux:skeleton class="h-4 w-full rounded" />
            <flux:skeleton class="h-4 w-full rounded" />
            <flux:skeleton class="h-4 w-5/6 rounded" />
        </div>

        {{-- Another paragraph --}}
        <div class="space-y-2 pt-2">
            <flux:skeleton class="h-4 w-full rounded" />
            <flux:skeleton class="h-4 w-4/5 rounded" />
            <flux:skeleton class="h-4 w-full rounded" />
            <flux:skeleton class="h-4 w-2/3 rounded" />
        </div>

        {{-- Code block or image placeholder --}}
        <flux:skeleton class="h-32 w-full rounded" />

        {{-- More text --}}
        <div class="space-y-2 pt-2">
            <flux:skeleton class="h-4 w-full rounded" />
            <flux:skeleton class="h-4 w-3/4 rounded" />
        </div>
    </flux:skeleton.group>
</div>
