<div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
    <flux:skeleton.group animate="shimmer">
        {{-- Title skeleton --}}
        <flux:skeleton.line
            size="lg"
            class="mb-4 w-1/3"
        />

        {{-- Paragraph skeletons --}}
        <div class="space-y-3 mb-6">
            <flux:skeleton.line class="w-full" />
            <flux:skeleton.line class="w-full" />
            <flux:skeleton.line class="w-5/6" />
            <flux:skeleton.line class="w-4/5" />
        </div>

        {{-- Another paragraph --}}
        <div class="space-y-3 mb-6">
            <flux:skeleton.line class="w-full" />
            <flux:skeleton.line class="w-full" />
            <flux:skeleton.line class="w-3/4" />
        </div>

        {{-- Subheading --}}
        <flux:skeleton.line
            size="lg"
            class="mb-4 w-1/4"
        />

        {{-- List items --}}
        <div class="space-y-2 ml-4">
            <flux:skeleton.line class="w-2/3" />
            <flux:skeleton.line class="w-1/2" />
            <flux:skeleton.line class="w-3/5" />
        </div>
    </flux:skeleton.group>
</div>
