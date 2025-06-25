<?php

namespace App\Livewire\Forms;

use App\Models\Comment;
use App\Models\Mod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Form;

class CommentForm extends Form
{
    /**
     * The body of the comment.
     *
     * @var string
     */
    #[Validate('required')]
    public string $body = '';

    /**
     * The comment model being edited, or null if creating a new comment.
     *
     * @var Comment|null
     */
    public ?Comment $comment = null;

    /**
     * Set the comment for editing.
     */
    public function setComment(Comment $comment): void
    {
        $this->comment = $comment;
        $this->body = $comment->body;
    }

    /**
     * Store a new comment.
     */
    public function store(Mod $commentable): void
    {
        $this->validate();

        $commentable->comments()->create([
            'body' => $this->body,
            'user_id' => Auth::id(),
        ]);

        $this->reset();
    }

    /**
     * Update an existing comment.
     */
    public function update(): void
    {
        $this->validate();

        if ($this->comment) {
            $this->comment->update([
                'body' => $this->body,
            ]);
        }

        $this->reset();
    }
}
