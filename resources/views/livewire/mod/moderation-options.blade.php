<div>
    <x-dropdown alignment="right" contentClasses="py-1 rounded-full bg-gray-200 dark:bg-gray-800">
        <x-slot name="trigger">
            {{-- wire:click.prevent here to stop from following mod card links --}}
            <button class="relative text-blue-400 dark:text-blue-500 hover:text-blue-600 dark:hover:text-blue-700" wire:click.prevent="">
                {{-- Icon (shield with keyhole) --}}
                <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 5.75V11c0 5.001 2.958 8.676 8.725 10.948a.75.75 0 0 0 .55 0C18.042 19.676 21 16 21 11V5.75a.75.75 0 0 0-.75-.75c-2.663 0-5.258-.943-7.8-2.85a.75.75 0 0 0-.9 0C9.008 4.057 6.413 5 3.75 5a.75.75 0 0 0-.75.75ZM13.995 11a2 2 0 0 1-1.245 1.852v2.398a.75.75 0 0 1-1.5 0v-2.394A2 2 0 1 1 13.995 11Z"/>
                </svg>
            </button>
        </x-slot>

        <x-slot name="content">
            <div>
                <button wire:click.prevent="disableMod" wire:confirm="Disable the mod '{{$this->mod->name}}'?" class="p-2 h-full w-full text-blue-500 dark:text-blue-500 bg-gray-200 dark:bg-gray-800 hover:text-blue-400 dark:hover:text-blue-400">
                    <div class="flex">
                        <span class="pr-2">
                            {{-- Icon (circle with dash) --}}
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2.001c5.524 0 10 4.477 10 10s-4.476 10-10 10c-5.522 0-10-4.477-10-10s4.478-10 10-10Zm4.25 9.25h-8.5a.75.75 0 0 0 0 1.5h8.5a.75.75 0 0 0 0-1.5Z" />
                            </svg>
                        </span>
                        {{ __('Disable') }}
                    </div>
                </button>
            </div>
            @if(auth()->user()->isAdmin())
            <div>
                <button wire:click.prevent="deleteMod" wire:confirm="Delete the mod '{{$this->mod->name}}'?" class="p-2 h-full w-full text-red-500 dark:text-red-500 bg-gray-200 dark:bg-gray-800 hover:text-red-400 dark:hover:text-red-400">
                    <div class="flex">
                        <span class="pr-2">
                            {{-- Icon (trash can) --}}
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M21.5 6a1 1 0 0 1-.883.993L20.5 7h-.845l-1.231 12.52A2.75 2.75 0 0 1 15.687 22H8.313a2.75 2.75 0 0 1-2.737-2.48L4.345 7H3.5a1 1 0 0 1 0-2h5a3.5 3.5 0 1 1 7 0h5a1 1 0 0 1 1 1Zm-7.25 3.25a.75.75 0 0 0-.743.648L13.5 10v7l.007.102a.75.75 0 0 0 1.486 0L15 17v-7l-.007-.102a.75.75 0 0 0-.743-.648Zm-4.5 0a.75.75 0 0 0-.743.648L9 10v7l.007.102a.75.75 0 0 0 1.486 0L10.5 17v-7l-.007-.102a.75.75 0 0 0-.743-.648ZM12 3.5A1.5 1.5 0 0 0 10.5 5h3A1.5 1.5 0 0 0 12 3.5Z" />
                            </svg>
                        </span>
                        {{  __('Delete')  }}
                    </div>
                </button>
            </div>
            @endif
        </x-slot>

    </x-dropdown>
</div>
