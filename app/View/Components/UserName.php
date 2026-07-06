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

        if ($role && $role->color_class) {
            return sprintf('text-%s-400', $role->color_class);
        }

        return '';
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.user-name');
    }
}
