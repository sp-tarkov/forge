<?php

declare(strict_types=1);

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component {
    /**
     * Return data for the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'banExpiresAt' => session('ban_expires_at'),
        ];
    }
}; 