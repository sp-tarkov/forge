<?php

declare(strict_types=1);

namespace App\Actions\Comments;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;

final class HardDeleteSpamComment
{
    public function execute(Comment $comment, ?string $reason = null): void
    {
        DB::transaction(function () use ($comment, $reason): void {
            $descendantIds = $this->collectDescendantIds($comment);

            if ($descendantIds !== []) {
                Comment::query()->whereIn('id', $descendantIds)->delete();
            }

            Track::eventSync(
                TrackingEventType::COMMENT_HARD_DELETE,
                $comment,
                isModerationAction: true,
                reason: $reason,
            );

            $comment->delete();
        });
    }

    /**
     * Walk the parent_id chain one generation at a time and return every descendant id.
     *
     * Cannot use the descendants() relation: it is keyed on root_id, which points at the thread top, not at the
     * target. For a non-root target that would pull in siblings and cousins.
     *
     * @return list<int>
     */
    private function collectDescendantIds(Comment $comment): array
    {
        $allIds = [];
        $frontier = [$comment->id];

        while (true) {
            /** @var list<int> $childIds */
            $childIds = Comment::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            if ($childIds === []) {
                break;
            }

            array_push($allIds, ...$childIds);
            $frontier = $childIds;
        }

        return $allIds;
    }
}
