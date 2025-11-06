<?php

declare(strict_types=1);

namespace App\View\Components\Addon;

use App\Models\Addon as AddonModel;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

class Card extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public AddonModel $addon,
        public ?int $selectedModVersionId = null,
    ) {
        //
    }

    /**
     * Get all compatible mod versions to show.
     *
     * @return Collection<int, ModVersion>
     */
    public function compatibleModVersionsToShow(): Collection
    {
        return $this->addon->getAllCompatibleModVersions($this->selectedModVersionId);
    }

    /**
     * Check if addon has no compatible versions.
     */
    public function hasNoCompatibleVersions(): bool
    {
        return $this->addon->hasNoCompatibleVersions();
    }

    /**
     * Get the latest mod version ID.
     */
    public function latestModVersionId(): ?int
    {
        return $this->addon->mod->latestVersion->id ?? null;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.addon.card');
    }
}
