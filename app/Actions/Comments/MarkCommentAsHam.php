<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Contracts\SpamChecker;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Comment;

final readonly class MarkCommentAsHam
{
    public function __construct(
        private SpamChecker $spamChecker,
    ) {}

    public function execute(Comment $comment, ?string $reason = null): void
    {
        $this->spamChecker->markAsHam($comment);

        $comment->markAsHam();

        Track::eventSync(
            TrackingEventType::COMMENT_MARK_CLEAN,
            $comment,
            isModerationAction: true,
            reason: $reason,
        );
    }
}
