<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Contracts\Commentable;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Rules\DoesNotContainLogFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Form;

class CommentCreateForm extends Form
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
     * Create a new comment with initial version.
     *
     * @param  Commentable<Mod|User>  $commentable
     */
    public function submit(Commentable $commentable): Comment
    {
        $this->validate();

        return DB::transaction(function () use ($commentable): Comment {
            $comment = new Comment;
            $comment->user_id = Auth::id();
            $comment->commentable()->associate($commentable);
            $comment->save();

            // Create initial version with the body content
            $comment->versions()->create([
                'body' => mb_trim($this->body),
                'version_number' => 1,
                'created_at' => now(),
            ]);

            return $comment;
        });
    }
}
