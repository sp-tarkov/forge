<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

class TabButton extends Component
{
    /**
     * The value of the tab.
     */
    public string $tabValue;

    /**
     * The display label of the tab.
     */
    public string $displayLabel;

    /**
     * Create a new tab button component.
     */
    public function __construct(
        public string $name,
        ?string $value = null,
        ?string $label = null
    ) {
        $this->tabValue = $value ?? Str::lower($name);
        $this->displayLabel = $label ?? $name;
    }

    /**
     * Render the tab button component.
     */
    public function render(): View
    {
        return view('components.tab-button');
    }
}
