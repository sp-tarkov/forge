<div>
    @if ($createdCount > 0)
        <span
            class="flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold text-white"
        >
            {{ $createdCount > 99 ? '99+' : $createdCount }}
        </span>
    @endif
</div>
