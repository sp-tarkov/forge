<button
    {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-cyan-500 dark:bg-cyan-600 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-100 uppercase tracking-widest hover:bg-cyan-400 dark:hover:bg-cyan-100 focus:bg-cyan-700 dark:focus:bg-cyan-100 active:bg-cyan-900 dark:active:bg-cyan-100 dark:active:text-gray-900 focus:outline-hidden focus:ring-2 focus:ring-cyan-500 dark:focus:ring-cyan-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150 hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700']) }}
>
    {{ $slot }}
</button>
