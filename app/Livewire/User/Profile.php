<?php

namespace App\Livewire\User;

use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Profile extends Component
{
    use WithPagination;

    public User $user;

    #[Url]
    public string $section = 'wall';

    public $followers;

    public $following;

    protected $listeners = ['refreshNeeded' => 'render'];

    public function render()
    {
        $this->followers = $this->user->followers;
        $this->following = $this->user->following;

        $mods = $this->user->mods()->withWhereHas('latestVersion')->paginate(6);

        return view('livewire.user.profile', compact('mods'));
    }

    public function setSection(string $name)
    {
        $this->section = $name;
    }

    public function message()
    {
        // todo: not implemented yet
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
