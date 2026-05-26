<?php

declare(strict_types=1);

use App\Actions\Comments\HardDeleteSpamComment;
use App\Enums\SpamStatus;
use App\Enums\TrackingEventType;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('akismet.enabled', false);

    $this->action = resolve(HardDeleteSpamComment::class);
    $this->mod = Mod::factory()->create();
    $this->author = User::factory()->create();
});

/**
 * Build a comment at the given position in a thread, with root_id wired up.
 */
function buildComment(User $author, Mod $mod, ?Comment $parent = null): Comment
{
    return Comment::factory()->create([
        'commentable_type' => Mod::class,
        'commentable_id' => $mod->id,
        'user_id' => $author->id,
        'parent_id' => $parent?->id,
        'root_id' => $parent?->root_id ?? $parent?->id,
    ]);
}

describe('descendant sweep', function (): void {
    it('deletes a non-root target and its own sub-thread while leaving the root intact', function (): void {
        $root = buildComment($this->author, $this->mod);
        $reply = buildComment($this->author, $this->mod, $root);
        $grandchild = buildComment($this->author, $this->mod, $reply);
        $reply->update(['spam_status' => SpamStatus::SPAM]);

        $this->action->execute($reply->fresh());

        expect(Comment::query()->whereKey($root->id)->exists())->toBeTrue();
        expect(Comment::query()->whereKey($reply->id)->exists())->toBeFalse();
        expect(Comment::query()->whereKey($grandchild->id)->exists())->toBeFalse();
    });

    it('walks multiple generations from the target', function (): void {
        $root = buildComment($this->author, $this->mod);
        $reply = buildComment($this->author, $this->mod, $root);
        $grandchild = buildComment($this->author, $this->mod, $reply);
        $greatGrandchild = buildComment($this->author, $this->mod, $grandchild);

        $this->action->execute($root->fresh());

        expect(Comment::query()->whereKey($root->id)->exists())->toBeFalse();
        expect(Comment::query()->whereKey($reply->id)->exists())->toBeFalse();
        expect(Comment::query()->whereKey($grandchild->id)->exists())->toBeFalse();
        expect(Comment::query()->whereKey($greatGrandchild->id)->exists())->toBeFalse();
    });

    it('leaves sibling branches untouched when deleting one reply', function (): void {
        $root = buildComment($this->author, $this->mod);
        $targetReply = buildComment($this->author, $this->mod, $root);
        $targetChild = buildComment($this->author, $this->mod, $targetReply);
        $siblingReply = buildComment($this->author, $this->mod, $root);
        $siblingChild = buildComment($this->author, $this->mod, $siblingReply);

        $this->action->execute($targetReply->fresh());

        expect(Comment::query()->whereKey($root->id)->exists())->toBeTrue();
        expect(Comment::query()->whereKey($siblingReply->id)->exists())->toBeTrue();
        expect(Comment::query()->whereKey($siblingChild->id)->exists())->toBeTrue();
        expect(Comment::query()->whereKey($targetReply->id)->exists())->toBeFalse();
        expect(Comment::query()->whereKey($targetChild->id)->exists())->toBeFalse();
    });

    it('leaves unrelated threads on the same mod untouched', function (): void {
        $targetRoot = buildComment($this->author, $this->mod);
        $targetReply = buildComment($this->author, $this->mod, $targetRoot);

        $unrelatedRoot = buildComment($this->author, $this->mod);
        $unrelatedReply = buildComment($this->author, $this->mod, $unrelatedRoot);

        $this->action->execute($targetRoot->fresh());

        expect(Comment::query()->whereKey($targetRoot->id)->exists())->toBeFalse();
        expect(Comment::query()->whereKey($targetReply->id)->exists())->toBeFalse();
        expect(Comment::query()->whereKey($unrelatedRoot->id)->exists())->toBeTrue();
        expect(Comment::query()->whereKey($unrelatedReply->id)->exists())->toBeTrue();
    });
});

describe('transaction safety', function (): void {
    it('rolls back every delete and the tracking write if anything in the transaction throws', function (): void {
        $root = buildComment($this->author, $this->mod);
        $reply = buildComment($this->author, $this->mod, $root);

        // Fire a pre-delete listener on the final $comment->delete() so the transaction throws after descendants have
        // already been deleted and tracking has been written.
        Comment::deleting(function (Comment $deleting) use ($root): void {
            throw_if($deleting->id === $root->id, RuntimeException::class, 'simulated failure');
        });

        expect(fn () => $this->action->execute($root->fresh()))
            ->toThrow(RuntimeException::class, 'simulated failure');

        expect(Comment::query()->whereKey($root->id)->exists())->toBeTrue();
        expect(Comment::query()->whereKey($reply->id)->exists())->toBeTrue();
        expect(TrackingEvent::query()
            ->where('event_name', TrackingEventType::COMMENT_HARD_DELETE->value)
            ->where('visitable_id', $root->id)
            ->exists())->toBeFalse();
    });
});
