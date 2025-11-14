<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Comment;
use App\Rules\DoesNotContainLogFile;
use Livewire\Form;

class CommentEditForm extends Form
{
    /**
     * The body of the comment.
     */
    public string $body = '';

    /**
     * Get the validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $minLength = config('comments.validation.min_length', 3);
        $maxLength = config('comments.validation.max_length', 10000);

        return [
            'body' => [
                'required',
                'string',
                sprintf('min:%s', $minLength),
                sprintf('max:%s', $maxLength),
                new DoesNotContainLogFile,
            ],
        ];
    }

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
