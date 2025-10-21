<button
    {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-400 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-900 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-100 focus:bg-gray-700 dark:focus:bg-gray-100 active:bg-gray-900 dark:active:bg-gray-100 focus:outline-hidden focus:ring-2 focus:ring-grey-500 dark:focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}
>
    {{ $slot }}
</button>
