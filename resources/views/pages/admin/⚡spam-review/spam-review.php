<?php

declare(strict_types=1);

use App\Actions\Comments\ConfirmCommentAsSpam;
use App\Actions\Comments\HardDeleteSpamComment;
use App\Actions\Comments\MarkCommentAsHam;
use App\Actions\Comments\SoftDeleteSpamComment;
use App\Models\Addon;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator as BaseLengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::base')] class extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    /** Filter by commentable type. */
    #[Session(key: 'spam_review_filter_type')]
    public string $filterType = '';

    /** Filter by author username. */
    public string $filterAuthor = '';

    /** The ID of the comment being acted upon. */
    public int $activeCommentId = 0;

    /** The action type being taken. */
    public string $selectedAction = '';

    /** Optional moderator note for the action. */
    #[Validate('nullable|string|max:1000')]
    public string $actionNote = '';

    /** Whether the action modal is visible. */
    public bool $showActionModal = false;

    /**
     * Handle the component's mounting process.
     */
    public function mount(): void
    {
        $this->authorize('reviewSpam', Comment::class);
    }

    /**
     * Retrieve a paginated list of spam comments awaiting review.
     *
     * @return BaseLengthAwarePaginator<int, Comment>
     */
    #[Computed]
    public function comments(): BaseLengthAwarePaginator
    {
        return Comment::query()
            ->pendingSpamReview()
            ->with(['user', 'commentable', 'latestVersion'])
            ->when($this->filterType !== '', fn (Builder $query) => $query->where('commentable_type', $this->resolveCommentableType()))
            ->when($this->filterAuthor !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $q) => $q->where('name', 'like', '%'.$this->filterAuthor.'%')))
            ->latest()
            ->paginate(10, pageName: 'spam-page');
    }

    /**
     * Get the count of spam comments awaiting review.
     */
    #[Computed]
    public function pendingSpamCount(): int
    {
        return Comment::query()->pendingSpamReview()->count();
    }

    /**
     * Reset pagination when the filter changes.
     */
    public function updatedFilterType(): void
    {
        $this->resetPage(pageName: 'spam-page');
    }

    public function updatedFilterAuthor(): void
    {
        $this->resetPage(pageName: 'spam-page');
    }

    /**
     * Clear all search filters.
     */
    public function clearFilters(): void
    {
        $this->filterType = '';
        $this->filterAuthor = '';
        $this->resetPage(pageName: 'spam-page');
    }

    /**
     * Open the action modal for a specific comment and action type.
     */
    public function openActionModal(int $commentId, string $action): void
    {
        $this->activeCommentId = $commentId;
        $this->selectedAction = $action;
        $this->actionNote = '';
        $this->showActionModal = true;
    }

    /**
     * Execute the selected moderation action.
     */
    public function executeAction(): void
    {
        $comment = Comment::query()->findOrFail($this->activeCommentId);

        $this->validate();

        $reason = $this->actionNote !== '' ? $this->actionNote : null;

        match ($this->selectedAction) {
            'confirm_spam' => $this->executeConfirmSpam($comment, $reason),
            'mark_as_ham' => $this->executeMarkAsHam($comment, $reason),
            'soft_delete' => $this->executeSoftDelete($comment, $reason),
            'hard_delete' => $this->executeHardDelete($comment, $reason),
            default => throw new InvalidArgumentException('Unknown spam review action: '.$this->selectedAction),
        };

        $this->showActionModal = false;
        $this->reset(['activeCommentId', 'actionNote', 'selectedAction']);
    }

    /**
     * Confirm that a comment was correctly flagged as spam.
     */
    private function executeConfirmSpam(Comment $comment, ?string $reason): void
    {
        $this->authorize('confirmSpam', $comment);

        resolve(ConfirmCommentAsSpam::class)->execute($comment, (int) auth()->id(), $reason);

        Flux::toast(heading: 'Confirmed Spam', text: 'Akismet has been trained with this example.', variant: 'success');
    }

    /**
     * Approve a comment that was incorrectly flagged as spam.
     */
    private function executeMarkAsHam(Comment $comment, ?string $reason): void
    {
        $this->authorize('markAsHam', $comment);

        resolve(MarkCommentAsHam::class)->execute($comment, $reason);

        Flux::toast(heading: 'Marked as Ham', text: 'The comment has been approved and is now visible.', variant: 'success');
    }

    /**
     * Soft-delete the comment without any spam feedback.
     */
    private function executeSoftDelete(Comment $comment, ?string $reason): void
    {
        $this->authorize('softDelete', $comment);

        resolve(SoftDeleteSpamComment::class)->execute($comment, $reason);

        Flux::toast(heading: 'Comment Soft-deleted', text: 'The comment has been hidden and can be restored.', variant: 'success');
    }

    /**
     * Permanently delete the comment and any descendants.
     */
    private function executeHardDelete(Comment $comment, ?string $reason): void
    {
        $this->authorize('hardDelete', $comment);

        resolve(HardDeleteSpamComment::class)->execute($comment, $reason);

        Flux::toast(heading: 'Comment Hard-deleted', text: 'The comment has been permanently removed.', variant: 'success');
    }

    /**
     * Resolve the commentable type from the filter key.
     */
    private function resolveCommentableType(): string
    {
        return match ($this->filterType) {
            'mod' => Mod::class,
            'addon' => Addon::class,
            'user' => User::class,
            default => '',
        };
    }
};
