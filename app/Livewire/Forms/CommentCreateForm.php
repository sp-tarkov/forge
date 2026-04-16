<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Contracts\Commentable;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Rules\DoesNotContainLogFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Form;

final class CommentCreateForm extends Form
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
        $minLength = config()->integer('comments.validation.min_length', 3);
        $maxLength = config()->integer('comments.validation.max_length', 10000);

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

        if (! $commentable instanceof Model) {
            throw new InvalidArgumentException('Commentable must be an Eloquent Model instance.');
        }

        return DB::transaction(function () use ($commentable): Comment {
            $comment = new Comment;
            $comment->user_id = (int) Auth::id();
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
