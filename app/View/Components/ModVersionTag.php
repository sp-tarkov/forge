<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ModVersionTag extends Component
{
    public string $tagColor;

    public function __construct($tagColor)
    {
        $this->tagColor = $tagColor;
    }

    public function render(): View
    {
        return view('components.mod-version-tag');
    }

    public function tagClasses($tagColor): string
    {
        return match ($this->tagColor) {
            'red' => 'bg-red-50 text-red-700 ring-red-600/20',
            'green' => 'bg-green-50 text-green-700 ring-green-600/20',
            'yellow' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
            default => 'bg-gray-50 text-gray-700 ring-gray-600/20',
        };
    }
}
