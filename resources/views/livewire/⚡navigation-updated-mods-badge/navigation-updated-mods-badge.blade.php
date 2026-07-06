<div>
    @if ($updatedCount > 0)
        <span
            class="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold text-white"
        >
            {{ $updatedCount > 99 ? '99+' : $updatedCount }}
        </span>
    @endif
</div>
