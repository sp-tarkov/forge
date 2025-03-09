<div class="absolute top-1.5 right-1.5">
    <div x-data="{ moderationMenu: false, moderationMenuKeyboard: false }" @keydown.esc.window="moderationMenu = false, moderationMenuKeyboard = false" class="relative">
        <button type="button"
                @click="moderationMenu = ! moderationMenu"
                @keydown.space.prevent="moderationMenuKeyboard = true"
                @keydown.enter.prevent="moderationMenuKeyboard = true"
                @keydown.down.prevent="moderationMenuKeyboard = true"
                class="relative flex rounded-full p-1.5 bg-gray-100 dark:bg-gray-950 hover:bg-gray-200 dark:hover:bg-gray-900 focus:bg-gray-200 dark:focus:bg-gray-900 text-sm focus:outline-hidden focus:ring-2 focus:ring-black dark:focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-300 dark:focus:ring-offset-gray-800"
                :class="moderationMenu || moderationMenuKeyboard ? 'text-black dark:text-white' : 'text-slate-700 dark:text-slate-300'"
                :aria-expanded="moderationMenu || moderationMenuKeyboard"
                aria-haspopup="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-black dark:text-white">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z" />
            </svg>
        </button>
        <div x-cloak
             x-show="moderationMenu || moderationMenuKeyboard"
             x-transition
             x-trap="moderationMenuKeyboard"
             @click.outside="moderationMenu = false, moderationMenuKeyboard = false"
             @keydown.down.prevent="$focus.wrap().next()"
             @keydown.up.prevent="$focus.wrap().previous()"
             class="absolute top-11 right-0 z-10 flex w-full min-w-[12rem] flex-col divide-y divide-slate-300 overflow-hidden rounded-xl border border-gray-300 bg-gray-100 dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800"
             role="menu">
            <div class="flex flex-col py-1.5">
                <button type="button"
                        wire:click="delete"
                        class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                        role="menuitem">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                    {{ __('Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
