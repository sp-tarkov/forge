<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Mod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Form;

class CommentCreateForm extends Form
{
    /**
     * The body of the comment.
     */
    #[Validate('required')]
    public string $body = '';

    /**
     * Create a new comment.
     */
    public function submit(Mod $commentable): void
    {
        $this->validate();

        $commentable->comments()->create([
            'body' => $this->body,
            'user_id' => Auth::id(),
        ]);
    }
}
