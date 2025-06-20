<?php

declare(strict_types=1);

namespace App\Livewire\Comment;

use App\Models\Comment;
use App\Models\Mod;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    /**
     * The commentable. An instance of the model that can be commented on.
     *
     * @var Mod
     */
    #[Locked]
    public Model $commentable;

    /**
     * The body of the comment.
     *
     * @var string
     */
    #[Validate('required')]
    public $body = '';

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
    public function mount(Model $commentable): void
    {
        $this->commentable = $commentable;
    }

    /**
     * Create a new comment.
     */
    public function create(): void
    {
        $this->authorize('create', Comment::class);

        $this->validate();

        $this->commentable->comments()->create([
            'body' => $this->body,
            'user_id' => Auth::id(),
        ]);

        $this->reset('body');

        // Force re-computation of the comment count
        unset($this->commentCount);

        $this->dispatch('comment-saved')
            ->to(Listing::class);
    }

    /**
     * Update an existing comment.
     */
    public function update(Comment $comment): void
    {
        $this->authorize('update', $comment);

        $this->validate();

        $comment->update(
            $this->pull(['body'])
        );

        $this->dispatch('comment-saved')
            ->to(Listing::class);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.comment.form');
    }
}
