<?php

declare(strict_types=1);

use App\Models\Mod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component
{
    /**
     * The mod ID.
     */
    public int $modId;

    /**
     * Mount the component.
     */
    public function mount(int $modId): void
    {
        $this->modId = $modId;
    }

    /**
     * Get the mod's description HTML.
     */
    #[Computed]
    public function descriptionHtml(): string
    {
        return Mod::query()->findOrFail($this->modId)->description_html;
    }
};
