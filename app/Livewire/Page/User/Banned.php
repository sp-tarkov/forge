<?php

declare(strict_types=1);

namespace App\Livewire\Page\User;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Banned extends Component
{
    /**
     * Render the banned user page.
     */
    #[Layout('components.layouts.base')]
    public function render(): View
    {
        return view('livewire.page.user.banned', [
            'banExpiresAt' => session('ban_expires_at'),
        ]);
    }
}
