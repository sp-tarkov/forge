<?php

declare(strict_types=1);

namespace App\Livewire\User\Show;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class WallTab extends Component
{
    /**
     * The user ID whose wall is being shown.
     */
    public int $userId;

    /**
     * Mount the component.
     */
    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Render the placeholder while loading.
     */
    public function placeholder(): View
    {
        return view('livewire.user.show.wall-tab-placeholder');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $user = User::query()->findOrFail($this->userId);

        return view('livewire.user.show.wall-tab', [
            'user' => $user,
        ]);
    }
}
