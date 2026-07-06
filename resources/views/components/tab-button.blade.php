<button
    x-on:click="selectedTab = '{{ $tabValue }}'"
    :aria-selected="selectedTab == '{{ $tabValue }}'"
    :class="{
        'font-extrabold': selectedTab == '{{ $tabValue }}',
        'font-light': selectedTab != '{{ $tabValue }}'
    }"
    class="tab group relative flex min-w-0 flex-1 items-center justify-center gap-1 overflow-hidden bg-cyan-700 px-4 py-4 text-center text-sm text-white first:rounded-l-xl last:rounded-r-xl hover:bg-cyan-600 focus:z-10"
    {{ $attributes }}
>
    {{ __($displayLabel) }}
    <svg
        x-cloak
        x-show="selectedTab == '{{ $tabValue }}'"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 16 16"
        fill="currentColor"
        class="h-3 h-3"
    >
        <path
            fill-rule="evenodd"
            d="M7.47 12.78a.75.75 0 0 0 1.06 0l3.25-3.25a.75.75 0 0 0-1.06-1.06L8 11.19 5.28 8.47a.75.75 0 0 0-1.06 1.06l3.25 3.25ZM4.22 4.53l3.25 3.25a.75.75 0 0 0 1.06 0l3.25-3.25a.75.75 0 0 0-1.06-1.06L8 6.19 5.28 3.47a.75.75 0 0 0-1.06 1.06Z"
            clip-rule="evenodd"
        />
    </svg>
</button>
