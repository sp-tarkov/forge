<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use App\Models\Mod;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Modelable;
use Livewire\Component;

class ModAutocomplete extends Component
{
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
    public function mount(
        ?int $excludeModId = null,
        string $placeholder = 'Search for a mod...',
        string $label = 'Select Mod',
        string $selectedModId = ''
    ): void {
        $this->excludeModId = $excludeModId;
        $this->placeholder = $placeholder;
        $this->label = $label;
        $this->componentId = 'mod-autocomplete-'.uniqid();

        // If a mod is already selected, load its name
        if (! empty($selectedModId)) {
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
        if (! empty($this->selectedModId)) {
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
    public function getFilteredModsProperty(): Collection
    {
        if (empty($this->search)) {
            return collect();
        }

        $query = Mod::query()
            ->where('name', 'like', '%'.$this->search.'%')
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
        if (! $this->showDropdown) {
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
            $this->highlightIndex = $this->highlightIndex <= 0
                ? $modsCount - 1
                : $this->highlightIndex - 1;
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

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.components.mod-autocomplete', [
            'filteredMods' => $this->filteredMods,
        ]);
    }
}
