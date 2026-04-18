<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Comment;

final class SoftDeleteSpamComment
{
    public function execute(Comment $comment, ?string $reason = null): void
    {
        $comment->update(['deleted_at' => now()]);

        Track::eventSync(
            TrackingEventType::COMMENT_SOFT_DELETE,
            $comment,
            isModerationAction: true,
            reason: $reason,
        );
    }
}
