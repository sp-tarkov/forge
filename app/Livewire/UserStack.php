<?php

namespace App\Livewire;

use Livewire\Component;

class UserStack extends Component
{
    public $users;

    public string $label = 'Users';

    public int $limit = 5;

    public function render()
    {
        return view('livewire.user-stack');
    }
}
