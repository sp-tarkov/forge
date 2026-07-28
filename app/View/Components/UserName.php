<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;
use Illuminate\View\View;

final class UserName extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public User $user,
        public string $class = '',
    ) {
        //
    }

    /**
     * Get the icon color class based on the user's role.
     */
    public function iconColorClass(): string
    {
        $role = $this->user->role ?? null;

        return match ($role?->color_class) {
            'red' => 'text-red-400',
            'orange' => 'text-orange-400',
            'lime' => 'text-lime-400',
            'green' => 'text-green-400',
            'emerald' => 'text-emerald-400',
            'sky' => 'text-sky-400',
            default => '',
        };
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.user-name');
    }
}
