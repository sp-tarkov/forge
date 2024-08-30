<?php

namespace App\Livewire\User;

use App\Models\User;
use Livewire\Component;

class Profile extends Component
{
    public User $user;

    public function render()
    {
        return view('livewire.user.profile');
    }

    public function followUser(User $user)
    {
        auth()->user()->follow($user);
    }

    public function unfollowUser(User $user)
    {
        auth()->user()->unfollow($user);
    }
}
