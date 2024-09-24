@props(['name'])

<button
    @click="selectedTab = '{{ Str::lower($name) }}'"
    :aria-selected="selectedTab == '{{ Str::lower($name) }}'"
    :class="{
        'font-extrabold': selectedTab == '{{ Str::lower($name) }}',
        'font-light': selectedTab != '{{ Str::lower($name) }}',
        'tab group relative min-w-0 flex-1 overflow-hidden py-4 px-4 text-center text-sm text-gray-800 dark:text-white bg-cyan-500 dark:bg-cyan-700 hover:bg-cyan-400 dark:hover:bg-cyan-600 focus:z-10 last:rounded-r-xl first:rounded-l-xl flex items-center justify-center gap-1': true
    }"
    {{ $attributes }}
>
    {{ __($name) }}
    <svg x-cloak x-show="selectedTab == '{{ Str::lower($name) }}'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3 h-3">
        <path fill-rule="evenodd" d="M7.47 12.78a.75.75 0 0 0 1.06 0l3.25-3.25a.75.75 0 0 0-1.06-1.06L8 11.19 5.28 8.47a.75.75 0 0 0-1.06 1.06l3.25 3.25ZM4.22 4.53l3.25 3.25a.75.75 0 0 0 1.06 0l3.25-3.25a.75.75 0 0 0-1.06-1.06L8 6.19 5.28 3.47a.75.75 0 0 0-1.06 1.06Z" clip-rule="evenodd" />
    </svg>
</button>
