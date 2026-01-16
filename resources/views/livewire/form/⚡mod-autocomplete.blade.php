<?php

declare(strict_types=1);

use App\Models\Mod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * @property Collection<int, Mod> $filteredMods
 */
new class extends Component {
    /**
     * The search query for filtering mods.
     */
    public string $search = '';

    /**
     * The selected mod ID.
     */
    #[Modelable]
    public string $selectedModId = '';

    /**
     * The selected mod name for display.
     */
    public string $selectedModName = '';

    /**
     * Whether the dropdown is open.
     */
    public bool $showDropdown = false;

    /**
     * The currently highlighted index in the dropdown.
     */
    public int $highlightIndex = -1;

    /**
     * The mod to exclude from results (usually the current mod).
     */
    public ?int $excludeModId = null;

    /**
     * The placeholder text for the input.
     */
    public string $placeholder = 'Search for a mod...';

    /**
     * The label for the input (for accessibility).
     */
    public string $label = 'Select Mod';

    /**
     * A unique ID for this component instance.
     */
    public string $componentId = '';

    /**
     * Mount the component.
     */
    public function mount(?int $excludeModId = null, string $placeholder = 'Search for a mod...', string $label = 'Select Mod', string $selectedModId = ''): void
    {
        $this->excludeModId = $excludeModId;
        $this->placeholder = $placeholder;
        $this->label = $label;
        $this->componentId = 'mod-autocomplete-' . uniqid();

        // If a mod is already selected, load its name
        if (!empty($selectedModId)) {
            $this->selectedModId = $selectedModId;
            $mod = Mod::query()->find($selectedModId);
            if ($mod) {
                $this->selectedModName = $mod->name;
                $this->search = $mod->name;
            }
        }
    }

    /**
     * Update when selectedModId changes from parent.
     */
    public function updatedSelectedModId(): void
    {
        if (!empty($this->selectedModId)) {
            $mod = Mod::query()->find($this->selectedModId);
            if ($mod) {
                $this->selectedModName = $mod->name;
                $this->search = $mod->name;
            }
        } else {
            $this->selectedModName = '';
            $this->search = '';
        }
    }

    /**
     * Get the filtered mods based on search query.
     *
     * @return Collection<int, Mod>
     */
    #[Computed]
    public function filteredMods(): Collection
    {
        if (empty($this->search)) {
            return collect();
        }

        $query = Mod::query()
            ->where('name', 'like', '%' . $this->search . '%')
            ->orderBy('name')
            ->limit(10);

        if ($this->excludeModId !== null) {
            $query->where('id', '!=', $this->excludeModId);
        }

        return $query->get();
    }

    /**
     * Handle search input updates.
     */
    public function updatedSearch(): void
    {
        // Reset selection if search doesn't match selected mod name
        if ($this->search !== $this->selectedModName) {
            $this->selectedModId = '';
            $this->selectedModName = '';
            $this->showDropdown = true;
            $this->highlightIndex = -1;
        } else {
            $this->showDropdown = false;
        }
    }

    /**
     * Select a mod from the dropdown.
     */
    public function selectMod(int $modId, string $modName): void
    {
        $this->selectedModId = (string) $modId;
        $this->selectedModName = $modName;
        $this->search = $modName;
        $this->showDropdown = false;
        $this->highlightIndex = -1;

        // Dispatch event to parent component
        $this->dispatch('mod-selected', modId: $modId, modName: $modName);
    }

    /**
     * Clear the selection.
     */
    public function clearSelection(): void
    {
        $this->selectedModId = '';
        $this->selectedModName = '';
        $this->search = '';
        $this->showDropdown = false;
        $this->highlightIndex = -1;

        // Dispatch event to parent component
        $this->dispatch('mod-cleared');
    }

    /**
     * Navigate dropdown with keyboard.
     */
    public function navigateWithKeyboard(string $direction): void
    {
        if (!$this->showDropdown) {
            $this->showDropdown = true;

            return;
        }

        $modsCount = $this->filteredMods->count();

        if ($modsCount === 0) {
            return;
        }

        if ($direction === 'down') {
            $this->highlightIndex = ($this->highlightIndex + 1) % $modsCount;
        } elseif ($direction === 'up') {
            $this->highlightIndex = $this->highlightIndex <= 0 ? $modsCount - 1 : $this->highlightIndex - 1;
        }
    }

    /**
     * Select the highlighted mod.
     */
    public function selectHighlighted(): void
    {
        if ($this->highlightIndex >= 0 && $this->showDropdown) {
            $mod = $this->filteredMods->values()->get($this->highlightIndex);
            if ($mod) {
                $this->selectMod($mod->id, $mod->name);
            }
        }
    }

    /**
     * Close the dropdown.
     */
    public function closeDropdown(): void
    {
        $this->showDropdown = false;
        $this->highlightIndex = -1;
    }
};
?>

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
        @if ($this->filteredMods->count() > 0)
            {{ $this->filteredMods->count() }} {{ Str::plural('result', $this->filteredMods->count()) }} available.
        @elseif(!empty($search) && $this->filteredMods->count() === 0)
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
    @if ($showDropdown && $this->filteredMods->count() > 0)
        <ul
            id="{{ $componentId }}-dropdown"
            class="absolute z-50 mt-1 w-full shadow-lg max-h-60 rounded-md py-1 text-base border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 overflow-auto focus:outline-none sm:text-sm"
            role="listbox"
            aria-label="{{ $label }} options"
        >
            @foreach ($this->filteredMods as $index => $mod)
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
