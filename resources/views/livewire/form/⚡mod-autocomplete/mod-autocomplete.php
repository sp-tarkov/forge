<?php

declare(strict_types=1);

use App\Models\Mod;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * @property Collection<int, Mod> $filteredMods
 */
new class extends Component
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
     * Mount the component.
     */
    public function mount(?int $excludeModId = null, string $placeholder = 'Search for a mod...', string $label = 'Select Mod', string $selectedModId = ''): void
    {
        $this->excludeModId = $excludeModId;
        $this->placeholder = $placeholder;
        $this->label = $label;

        if ($selectedModId !== '' && $selectedModId !== '0') {
            $this->selectedModId = $selectedModId;
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
        if ($this->search === '' || $this->search === '0') {
            /** @var Collection<int, Mod> */
            return collect();
        }

        $query = Mod::query()
            ->where('name', 'like', '%'.$this->search.'%')
            ->orderBy('name')
            ->limit(10);

        if ($this->excludeModId !== null) {
            $query->where('id', '!=', $this->excludeModId);
        }

        /** @var Collection<int, Mod> */
        return $query->get();
    }

    /**
     * Handle selection changes.
     */
    public function updatedSelectedModId(): void
    {
        if ($this->selectedModId !== '' && $this->selectedModId !== '0') {
            $mod = Mod::query()->find($this->selectedModId);
            if ($mod) {
                $this->dispatch('mod-selected', modId: $mod->id, modName: $mod->name);
            }
        } else {
            $this->dispatch('mod-cleared');
        }
    }
};
