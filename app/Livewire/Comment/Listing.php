<?php

declare(strict_types=1);

namespace App\Livewire\Comment;

use App\Livewire\Forms\CommentForm;
use App\Models\Comment;
use App\Models\Mod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Listing extends Component
{
    use WithPagination;

    /**
     * The commentable model.
     *
     * @var Mod
     */
    #[Locked]
    public Mod $commentable; // Type-hinting to Mod for stronger type safety

    /**
     * The comment form object.
     *
     * @var CommentForm
     */
    public CommentForm $form;

    /**
     * A count of all comments on the commentable.
     */
    #[Computed]
    public function commentCount(): int
    {
        return $this->commentable->comments()->count();
    }

    /**
     * Executed when the component is first loaded.
     */
    public function create(?Comment $parentComment): void
    {
        $this->authorize('create', Comment::class);

        $this->form->store($this->commentable, $parentComment);

        unset($this->commentCount);

        $this->dispatch('comment-saved')
            ->self();
    }

    /**
     * Update an existing comment.
     */
    public function update(Comment $comment): void
    {
        $this->authorize('update', $comment);

        $this->form->comment = $comment;
        $this->form->update();

        $this->dispatch('comment-saved')
            ->self();
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
        $rootComments = $this->commentable->rootComments()->paginate(10);

        return view('livewire.comment.listing', ['rootComments' => $rootComments]);
    }
}
