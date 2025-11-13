<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Contracts\Commentable;
use App\Models\Mod;
use App\Models\User;
use App\Rules\DoesNotContainLogFile;
use Illuminate\Support\Facades\Auth;
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
     * Create a new comment.
     *
     * @param  Commentable<Mod|User>  $commentable
     */
    public function submit(Commentable $commentable): void
    {
        $this->validate();

        $commentable->comments()->create([
            'body' => $this->body,
            'user_id' => Auth::id(),
        ]);
    }
}
