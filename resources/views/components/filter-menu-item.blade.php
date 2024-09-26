@props(['filterName', 'filter', 'currentFilter'])

<a href="#{{ $filter }}"
   @click.prevent="$wire.set('{{ $filterName }}', '{{ $filter }}')"
   class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-none dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white {{ strval($filter) === strval($currentFilter) ? "font-bold text-cyan-500 dark:text-cyan-500" : "" }}"
   role="menuitem" tabindex="-1">
    {{ $slot }}
</a>
