<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Contracts\SpamChecker;
use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Comment;

final readonly class ConfirmCommentAsSpam
{
    public function __construct(
        private SpamChecker $spamChecker,
    ) {}

    public function execute(Comment $comment, int $moderatorId, ?string $reason = null): void
    {
        $this->spamChecker->markAsSpam($comment);

        $comment->confirmSpamByModerator($moderatorId);

        Track::eventSync(
            TrackingEventType::COMMENT_MARK_SPAM,
            $comment,
            isModerationAction: true,
            reason: $reason,
        );
    }
}
