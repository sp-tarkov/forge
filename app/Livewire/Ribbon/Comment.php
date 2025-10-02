<?php

declare(strict_types=1);

namespace App\Livewire\Ribbon;

use App\Enums\SpamStatus;
use App\Facades\CachedGate;
use App\Models\Comment as CommentModel;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read array<string, string>|null $ribbonData
 */
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
    public bool $canSeeRibbon;

    /**
     * Listen for comment updates and refresh the ribbon data.
     */
    #[On('comment-updated')]
    public function refreshRibbon(int $commentId): void
    {
        // Only update if this is for our comment
        if ($commentId !== $this->commentId) {
            return;
        }

        $comment = CommentModel::query()->find($this->commentId);
        if ($comment) {
            $this->spamStatus = $comment->spam_status->value;
            $this->canSeeRibbon = CachedGate::allows('seeRibbon', $comment);
        }
    }

    /**
     * Get the ribbon data with caching.
     *
     * @return array<string, string>|null
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
