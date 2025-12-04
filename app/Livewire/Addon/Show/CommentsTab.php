<?php

declare(strict_types=1);

namespace App\Livewire\Addon\Show;

use App\Models\Addon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class CommentsTab extends Component
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
     * Render a placeholder while lazy loading.
     */
    public function placeholder(): View
    {
        return view('livewire.addon.show.comments-tab-placeholder');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $addon = Addon::query()->findOrFail($this->addonId);

        return view('livewire.addon.show.comments-tab', [
            'addon' => $addon,
        ]);
    }
}
