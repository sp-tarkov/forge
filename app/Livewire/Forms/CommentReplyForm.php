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

class CommentReplyForm extends Form
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
     * Create a new reply comment.
     *
     * @param  Commentable<Mod|User>  $commentable
     */
    public function submit(Commentable $commentable, int $parentCommentId): void
    {
        $this->validate();

        $parentComment = Comment::query()->findOrFail($parentCommentId);

        DB::transaction(function () use ($commentable, $parentComment): void {
            $comment = $commentable->comments()->create([
                'user_id' => Auth::id(),
                'parent_id' => $parentComment->id,
            ]);

            // Create initial version with the body content
            $comment->versions()->create([
                'body' => mb_trim($this->body),
                'version_number' => 1,
                'created_at' => now(),
            ]);
        });
    }
}
