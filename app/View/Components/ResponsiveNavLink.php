<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

final class ResponsiveNavLink extends Component
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
            ? 'block rounded-md px-3 py-2 text-base font-medium text-gray-100 bg-gray-900 transition duration-150 ease-in-out'
            : 'block rounded-md px-3 py-2 text-base font-medium text-gray-400 hover:bg-gray-700 hover:text-white transition duration-150 ease-in-out';
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.responsive-nav-link');
    }
}
