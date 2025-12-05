<div
    x-data="{
        open: $wire.entangle('showDropdown').live,
        search: $wire.entangle('search').live,
        highlightIndex: $wire.entangle('highlightIndex').live,
        selectedModId: $wire.entangle('selectedModId').live,
    }"
    x-on:click.away="$wire.closeDropdown()"
    class="relative"
>
    {{-- Screen reader announcement region --}}
    <div
        class="sr-only"
        aria-live="polite"
        aria-atomic="true"
    >
        @if ($filteredMods->count() > 0)
            {{ $filteredMods->count() }} {{ Str::plural('result', $filteredMods->count()) }} available.
        @elseif(!empty($search) && $filteredMods->count() === 0)
            No results found.
        @endif
    </div>

    {{-- Input field with ARIA attributes --}}
    <div class="relative">
        <label
            for="{{ $componentId }}"
            class="sr-only"
        >{{ $label }}</label>
        <input
            type="text"
            id="{{ $componentId }}"
            wire:model.live.debounce.300ms="search"
            x-on:focus="$wire.set('showDropdown', true)"
            x-on:keydown.arrow-down.prevent="$wire.navigateWithKeyboard('down')"
            x-on:keydown.arrow-up.prevent="$wire.navigateWithKeyboard('up')"
            x-on:keydown.enter.prevent="$wire.selectHighlighted()"
            x-on:keydown.escape="$wire.closeDropdown()"
            placeholder="{{ $placeholder }}"
            class="w-full border rounded-lg block disabled:shadow-none dark:shadow-none appearance-none text-base sm:text-sm py-2 h-10 leading-[1.375rem] ps-3 pe-3 bg-white dark:bg-white/10 dark:disabled:bg-white/[7%] text-zinc-700 disabled:text-zinc-500 placeholder-zinc-400 disabled:placeholder-zinc-400/70 dark:text-zinc-300 dark:disabled:text-zinc-400 dark:placeholder-zinc-400 dark:disabled:placeholder-zinc-500 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 dark:border-white/10 dark:disabled:border-white/5"
            autocomplete="off"
            aria-autocomplete="list"
            aria-controls="{{ $componentId }}-dropdown"
            aria-expanded="{{ $showDropdown ? 'true' : 'false' }}"
            aria-haspopup="listbox"
            aria-describedby="{{ $componentId }}-description"
            role="combobox"
        />

        {{-- Clear button --}}
        @if (!empty($selectedModId))
            <button
                type="button"
                wire:click="clearSelection"
                class="absolute inset-y-0 right-0 flex items-center pr-3"
                aria-label="Clear selection"
            >
                <flux:icon
                    name="x-circle"
                    class="h-5 w-5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                />
            </button>
        @endif
    </div>

    {{-- Helper text for screen readers --}}
    <span
        id="{{ $componentId }}-description"
        class="sr-only"
    >
        Type to search for mods. Use arrow keys to navigate results and Enter to select.
    </span>

    {{-- Dropdown results --}}
    @if ($showDropdown && $filteredMods->count() > 0)
        <ul
            id="{{ $componentId }}-dropdown"
            class="absolute z-50 mt-1 w-full shadow-lg max-h-60 rounded-md py-1 text-base border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 overflow-auto focus:outline-none sm:text-sm"
            role="listbox"
            aria-label="{{ $label }} options"
        >
            @foreach ($filteredMods as $index => $mod)
                <li
                    wire:click="selectMod({{ $mod->id }}, '{{ addslashes($mod->name) }}')"
                    x-on:mouseenter="highlightIndex = {{ $index }}"
                    x-on:mouseleave="highlightIndex = -1"
                    class="cursor-pointer select-none relative py-2 pl-3 pr-9 {{ $highlightIndex === $index ? 'bg-cyan-600 text-white' : 'text-gray-900 dark:text-gray-100' }} hover:bg-cyan-600 hover:text-white transition-colors"
                    role="option"
                    aria-selected="{{ $selectedModId == $mod->id ? 'true' : 'false' }}"
                    id="{{ $componentId }}-option-{{ $index }}"
                >
                    <div class="flex items-center">
                        <span class="font-normal block truncate">
                            {{ $mod->name }}
                        </span>
                    </div>

                    @if ($selectedModId == $mod->id)
                        <span
                            class="absolute inset-y-0 right-0 flex items-center pr-3 {{ $highlightIndex === $index ? 'text-white' : 'text-cyan-600' }}"
                        >
                            <flux:icon
                                name="check"
                                class="h-5 w-5"
                            />
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    @elseif($showDropdown && !empty($search))
        <div
            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-950 shadow-lg rounded-md py-2 px-3 text-sm text-gray-500 dark:text-gray-400 border border-gray-300 dark:border-gray-800"
            role="status"
        >
            No mods found matching "{{ $search }}"
        </div>
    @endif
</div>
