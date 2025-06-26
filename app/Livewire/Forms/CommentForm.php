<?php

declare(strict_types=1);

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
     */
    #[Validate('required')]
    public string $body = '';

    /**
     * The comment model being edited or null if creating a new comment.
     */
    public ?Comment $comment = null;

    /**
     * Store a new comment.
     */
    public function store(Mod $commentable, ?Comment $parentComment = null): void
    {
        $this->validate();

        $newComment = [
            'body' => $this->body,
            'user_id' => Auth::id(),
        ];

        if ($parentComment) {
            $newComment['parent_id'] = $parentComment->id;
        }

        $commentable->comments()->create($newComment);
    }

    /**
     * Update an existing comment.
     */
    public function update(Comment $comment): void
    {
        $this->validate();

        $comment->update([
            'body' => $this->body,
            'edited_at' => now(),
        ]);
    }
}
