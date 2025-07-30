<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Enums\SpamStatus;
use App\Models\Comment as CommentModel;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Comment extends Component
{
    /**
     * The comment ID.
     */
    #[Locked]
    public int $commentId;

    /**
     * The comment spam status.
     */
    #[Locked]
    public string $spamStatus;

    /**
     * Whether the current user can see the ribbon.
     */
    #[Locked]
    public bool $canSeeRibbon;

    /**
     * Listen for comment updates and refresh the ribbon data.
     */
    #[On('comment-updated.{commentId}')]
    public function refreshRibbon(): void
    {
        $comment = CommentModel::select('spam_status')->find($this->commentId);
        if ($comment && $comment->spam_status->value !== $this->spamStatus) {
            $this->spamStatus = $comment->spam_status->value;
        } else {
            $this->skipRender();
        }
    }

    /**
     * Get the ribbon data with caching.
     */
    #[Computed]
    public function ribbonData(): ?array
    {
        if (! $this->canSeeRibbon) {
            return null;
        }

        $status = SpamStatus::from($this->spamStatus);

        return [
            'color' => $status->color(),
            'label' => $status->label(),
        ];
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.ribbon.comment', [
            'ribbonData' => $this->ribbonData,
        ]);
    }
}
