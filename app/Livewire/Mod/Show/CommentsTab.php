<?php

declare(strict_types=1);

namespace App\Livewire\Mod\Show;

use App\Models\Mod;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class CommentsTab extends Component
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
     * Render a placeholder while lazy loading.
     */
    public function placeholder(): View
    {
        return view('livewire.mod.show.comments-tab-placeholder');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $mod = Mod::query()->findOrFail($this->modId);

        return view('livewire.mod.show.comments-tab', [
            'mod' => $mod,
        ]);
    }
}
