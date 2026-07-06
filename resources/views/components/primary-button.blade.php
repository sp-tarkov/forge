<button
    {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-cyan-700 border border-transparent rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest hover:bg-cyan-600 focus:bg-cyan-100 active:bg-cyan-100 active:text-gray-900 focus:outline-hidden focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}
>
    {{ $slot }}
</button>
