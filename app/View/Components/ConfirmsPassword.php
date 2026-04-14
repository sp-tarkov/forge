<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Stringable;

final class ConfirmsPassword extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $title = 'Confirm Password',
        public string $content = 'For your security, please confirm your password to continue.',
        public string $button = 'Confirm',
    ) {
        //
    }

    /**
     * Generate a unique confirmable ID from the wire:then attribute.
     */
    public function confirmableId(): string
    {
        /** @var Stringable|string $wire */
        $wire = $this->attributes->wire('then');

        return md5((string) $wire);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.confirms-password');
    }
}
