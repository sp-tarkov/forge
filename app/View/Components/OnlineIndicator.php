<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

final class OnlineIndicator extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $size = 'sm',
    ) {
        //
    }

    /**
     * Get the size CSS classes for the indicator.
     */
    public function sizeClass(): string
    {
        return [
            'xs' => 'h-1.5 w-1.5',
            'sm' => 'h-2 w-2',
            'md' => 'h-2.5 w-2.5',
            'lg' => 'h-3 w-3',
        ][$this->size] ?? 'h-2 w-2';
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.online-indicator');
    }
}
