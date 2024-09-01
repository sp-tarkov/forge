<?php

namespace App\Livewire\User;

use App\Models\User;
use Livewire\Component;

class Profile extends Component
{
    public User $user;

    public $followers;

    public $following;

    protected $listeners = ['refreshNeeded' => 'render'];

    public function render()
    {
        $this->followers = $this->user->followers;
        $this->following = $this->user->following;

        return view('livewire.user.profile');
    }

    public function message()
    {
        $this->render();
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
