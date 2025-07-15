<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Comment;
use App\Models\Mod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Form;

class CommentReplyForm extends Form
{
    /**
     * The body of the comment.
     */
    #[Validate('required')]
    public string $body = '';

    /**
     * Create a new reply comment.
     */
    public function submit(Mod $commentable, int $parentCommentId): void
    {
        $this->validate();

        $parentComment = Comment::query()->findOrFail($parentCommentId);

        $commentable->comments()->create([
            'body' => $this->body,
            'user_id' => Auth::id(),
            'parent_id' => $parentComment->id,
        ]);
    }
}
