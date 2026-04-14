<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Stringable;

final class Modal extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $maxWidth = null,
    ) {
        //
    }

    /**
     * Resolve the modal ID, falling back to a hash of the wire:model attribute.
     */
    public function resolvedId(): string
    {
        /** @var Stringable|string $wire */
        $wire = $this->attributes->wire('model');

        return $this->id ?? md5((string) $wire);
    }

    /**
     * Get the max-width CSS class for the modal.
     */
    public function maxWidthClass(): string
    {
        return [
            'sm' => 'sm:max-w-sm',
            'md' => 'sm:max-w-md',
            'lg' => 'sm:max-w-lg',
            'xl' => 'sm:max-w-xl',
            '2xl' => 'sm:max-w-2xl',
        ][$this->maxWidth ?? '2xl'];
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.modal');
    }
}
