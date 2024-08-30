<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class UserStack extends Component
{
    public $users;

    public string $label = 'Users';

    public int $limit = 5;

    public bool $viewAll = false;

    public function render()
    {
        return view('livewire.user-stack');
    }

    public function toggleViewAll()
    {
        $this->viewAll = ! $this->viewAll;
    }

    public function followUser($user)
    {
        $user->followers->syncWithoutDetaching(Auth::id());
    }
}
