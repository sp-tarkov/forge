<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Comment;
use Livewire\Attributes\Validate;
use Livewire\Form;

class CommentEditForm extends Form
{
    /**
     * The body of the comment.
     */
    #[Validate('required')]
    public string $body = '';

    /**
     * Edit a comment.
     */
    public function submit(Comment $comment): void
    {
        $this->validate();

        $comment->body = $this->body;
        $comment->edited_at = now();
        $comment->save();
    }
}
