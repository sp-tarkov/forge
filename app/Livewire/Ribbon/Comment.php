<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Models\Comment as CommentModel;
use Illuminate\View\View;
use Livewire\Component;

class Comment extends Component
{
    /**
     * The comment model.
     */
    public CommentModel $comment;

    /**
     * Render the component.
     */
    public function render(): View
    {
        $ribbonData = null;
        if (auth()->user()?->can('seeRibbon', $this->comment)) {
            $ribbonData = [
                'color' => $this->comment->spam_status->color(),
                'label' => $this->comment->spam_status->label(),
            ];
        }

        return view('livewire.ribbon.comment', compact('ribbonData'));
    }
}
