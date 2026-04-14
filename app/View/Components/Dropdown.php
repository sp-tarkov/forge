<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

final class Dropdown extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $align = 'right',
        public string $width = '48',
        public string $contentClasses = 'py-1 bg-white',
        public string $dropdownClasses = '',
    ) {
        //
    }

    /**
     * Get the alignment CSS classes based on the align property.
     */
    public function alignmentClasses(): string
    {
        return match ($this->align) {
            'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
            'top' => 'origin-top',
            'none', 'false' => '',
            default => 'ltr:origin-top-right rtl:origin-top-left end-0',
        };
    }

    /**
     * Get the width CSS class.
     */
    public function widthClass(): string
    {
        return match ($this->width) {
            '48' => 'w-48',
            default => $this->width,
        };
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.dropdown');
    }
}
