<div>
    <div class="absolute top-1.5 right-1.5">
        <div x-data="{ moderationMenu: false, moderationMenuKeyboard: false }" @keydown.esc.window="moderationMenu = false, moderationMenuKeyboard = false" class="relative">
            <button type="button"
                    x-on:click="moderationMenu = ! moderationMenu"
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
                            x-on:click="moderationMenu = false; moderationMenuKeyboard = false; $flux.modal('moderation-mod-delete-{{ $mod->id }}').show();"
                            class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                            role="menuitem">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        {{ __('Delete') }}
                    </button>

                    <button type="button"
                            x-on:click="moderationMenu = false; moderationMenuKeyboard = false; $flux.modal('moderation-mod-disable-{{ $mod->id }}').show();"
                            class="flex items-center gap-2 bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-800/5 hover:text-black focus-visible:bg-slate-800/10 focus-visible:text-black focus-visible:outline-hidden dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-100/5 dark:hover:text-white dark:focus-visible:bg-slate-100/10 dark:focus-visible:text-white"
                            role="menuitem">
                        @if ($mod->disabled)
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            {{ __('Enable') }}
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                            {{ __('Disable') }}
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>

    <flux:modal name="moderation-mod-delete-{{ $mod->id }}" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirm Delete</flux:heading>
                <flux:text class="mt-2">
                    <p class="my-2">Are you sure you want to delete the "{{ $mod->name }}" mod?</p>
                    <p class="my-2">This <strong>is not</strong> a soft-delete; the mod will be permanently deleted, along with all mod versions, mod comments, and any other data tied directly to the mod. If this isn't necessary you should simply disable the mod instead.</p>
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" wire:click="delete">Delete Mod</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="moderation-mod-disable-{{ $mod->id }}" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    @if ($mod->disabled)
                        Confirm Enable
                    @else
                        Confirm Disable
                    @endif
                </flux:heading>
                <flux:text class="mt-2">
                    <p>Are you sure you want to @if ($mod->disabled) enable @else disable @endif the "{{ $mod->name }}" mod?</p>
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" wire:click="toggleDisabled">
                    @if ($mod->disabled)
                        Enable Mod
                    @else
                        Disable Mod
                    @endif
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
