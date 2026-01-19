<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Override;

class StartChatButton extends Component
{
    public bool $shouldShow;

    /**
     * Create a new component instance.
     */
    public function __construct(
        public User $user,
        public string $size = 'sm',
    ) {
        $this->shouldShow = $this->shouldShowButton();
    }

    /**
     * Determine if the component should be rendered.
     */
    #[Override]
    public function shouldRender(): bool
    {
        return $this->shouldShow;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.start-chat-button');
    }

    /**
     * Determine if the button should be visible.
     */
    private function shouldShowButton(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return Auth::user()->can('initiateChat', $this->user);
    }
}
