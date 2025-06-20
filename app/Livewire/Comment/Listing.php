<?php

declare(strict_types=1);

namespace App\Livewire\Comment;

use App\Models\Mod;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Listing extends Component
{
    use WithPagination;

    /**
     * The commentable. An instance of the model that can be commented on.
     *
     * @var Mod
     */
    #[Locked]
    public Model $commentable;

    /**
     * Executed when the component is first loaded.
     */
    public function mount(Model $commentable): void
    {
        $this->commentable = $commentable;
    }

    /**
     * Executed when a comment is saved. This will trigger a re-render of the comment listing.
     */
    #[On('comment-saved')]
    public function commentSaved(): void
    {
        $this->commentable->refresh();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $rootComments = $this->commentable->rootComments;

        return view('livewire.comment.listing', ['rootComments' => $rootComments]);
    }
}
