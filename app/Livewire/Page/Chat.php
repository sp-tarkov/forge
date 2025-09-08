<?php

declare(strict_types=1);

namespace App\Livewire\Page;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Chat extends Component
{
    public bool $showNewConversation = false;

    #[Layout('components.layouts.base')]
    public function render(): View|Factory
    {
        return view('livewire.page.chat');
    }
}