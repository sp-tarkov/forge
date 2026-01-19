<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Comment;
use App\Rules\DoesNotContainLogFile;
use Illuminate\Support\Facades\DB;
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
     * Edit a comment by creating a new version.
     */
    public function submit(Comment $comment): void
    {
        $this->validate();

        DB::transaction(function () use ($comment): void {
            // Create new version with updated content
            $nextVersionNumber = ($comment->versions()->max('version_number') ?? 0) + 1;
            $comment->versions()->create([
                'body' => mb_trim($this->body),
                'version_number' => $nextVersionNumber,
                'created_at' => now(),
            ]);

            $comment->edited_at = now();
            $comment->save();
        });
    }
}
