<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class UserStack extends Component
{
    #[Reactive]
    public $users;

    public string $parentUserName;

    public $authFollowingIds = [];

    public string $label = 'Users';

    public int $limit = 5;

    public bool $viewAll = false;

    public bool $refreshNeeded = false;

    public function render()
    {
        if (Auth::check()) {
            $this->authFollowingIds = Auth::user()->following()->pluck('following_id')->toArray();
        }

        return view('livewire.user-stack');
    }

    public function toggleViewAll()
    {
        $this->viewAll = ! $this->viewAll;
    }

    public function closeDialog()
    {
        if ($this->refreshNeeded)
        {
            $this->dispatch('refreshNeeded');
        }

        $this->toggleViewAll();
    }

    public function followUser(User $user)
    {
        Auth::user()->follow($user);
        $this->refreshNeeded = true;
    }

    public function unfollowUser(User $user)
    {
        Auth::user()->unfollow($user);
        $this->refreshNeeded = true;
    }
}
