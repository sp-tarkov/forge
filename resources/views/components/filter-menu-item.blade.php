@props(['filterName', 'filter', 'currentFilter'])

<a
    href="#{{ $filter }}"
    x-on:click.prevent="$wire.set('{{ $filterName }}', '{{ $filter }}'); isResultsPerPageOpen = false; isSortOpen = false"
    class="flex items-center gap-2 px-4 py-2 text-sm focus-visible:outline-hidden bg-slate-800 hover:bg-slate-100/5 focus-visible:bg-slate-100/10 focus-visible:text-white {{ strval($filter) === strval($currentFilter) ? 'font-bold text-cyan-500 hover:text-cyan-400' : 'text-slate-300 hover:text-white' }}"
    role="menuitem"
    tabindex="-1"
>
    {{ $slot }}
</a>
