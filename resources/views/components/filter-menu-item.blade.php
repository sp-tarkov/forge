@props(['filterName', 'filter', 'currentFilter'])

<a href="#{{ $filter }}"
   @click.prevent="$wire.set('{{ $filterName }}', '{{ $filter }}')"
   class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm hover:bg-slate-800/5 focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:hover:bg-slate-100/5 dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white {{ strval($filter) === strval($currentFilter) ? "font-bold text-cyan-500 dark:text-cyan-500 hover:text-cyan-400 dark:hover:text-cyan-400" : "text-slate-700 dark:text-slate-300 hover:text-black dark:hover:text-white" }}"
   role="menuitem" tabindex="-1">
    {{ $slot }}
</a>
