<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

final class NavLink extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public bool $active = false,
    ) {
        //
    }

    /**
     * Get the CSS classes based on the active state.
     */
    public function classes(): string
    {
        return $this->active
            ? 'rounded-md px-3 py-2 text-sm font-medium text-white bg-gray-700/50 backdrop-blur-sm transition duration-150 ease-in-out'
            : 'rounded-md px-3 py-2 text-sm font-medium text-gray-200 hover:bg-gray-700/50 hover:text-white transition duration-150 ease-in-out';
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.nav-link');
    }
}
