<?php

declare(strict_types=1);

use App\Models\Addon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component
{
    /**
     * The addon ID.
     */
    public int $addonId;

    /**
     * Mount the component.
     */
    public function mount(int $addonId): void
    {
        $this->addonId = $addonId;
    }

    /**
     * Get the addon.
     */
    #[Computed]
    public function addon(): Addon
    {
        return Addon::query()->findOrFail($this->addonId);
    }
};
