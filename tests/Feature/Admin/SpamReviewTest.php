<?php

declare(strict_types=1);

use App\Contracts\SpamChecker;
use App\Enums\SpamStatus;
use App\Enums\TrackingEventType;
use App\Models\Addon;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function (): void {
    Config::set('akismet.enabled', false);
});

/**
 * Persist a spam-flagged comment without triggering the observer's spam check.
 */
function createSpamComment(User $user, Mod $mod, array $overrides = []): Comment
{
    $comment = Comment::factory()->create(array_merge([
        'commentable_type' => Mod::class,
        'commentable_id' => $mod->id,
        'user_id' => $user->id,
    ], $overrides));

    $comment->update(['spam_status' => SpamStatus::SPAM]);
    $comment->refresh();

    return $comment;
}

describe('access control', function (): void {
    it('blocks guests', function (): void {
        $this->get(route('spam-review'))->assertRedirect(route('login'));
    });

    it('blocks regular users', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('pages::admin.spam-review')->assertStatus(403);
    });

    it('allows moderators', function (): void {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator);

        Livewire::test('pages::admin.spam-review')->assertStatus(200);
    });

    it('allows admins', function (): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test('pages::admin.spam-review')->assertStatus(200);
    });
});

describe('queue listing', function (): void {
    it('lists undeleted spam comments that have not been reviewed', function (): void {
        $moderator = User::factory()->moderator()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();

        $pending = createSpamComment($author, $mod, ['body' => 'Pending review spam']);

        // Deleted spam should not appear.
        $deleted = createSpamComment($author, $mod, ['body' => 'Deleted spam']);
        $deleted->update(['deleted_at' => now()]);

        // Already reviewed spam should not appear.
        $reviewed = createSpamComment($author, $mod, ['body' => 'Reviewed spam']);
        $reviewed->confirmSpamByModerator($moderator->id);

        // Clean comments should not appear.
        Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $author->id,
            'spam_status' => SpamStatus::CLEAN,
            'body' => 'Clean comment',
        ]);

        $this->actingAs($moderator);

        Livewire::test('pages::admin.spam-review')
            ->assertSee('Pending review spam')
            ->assertDontSee('Deleted spam')
            ->assertDontSee('Reviewed spam')
            ->assertDontSee('Clean comment');
    });

    it('filters by commentable type', function (): void {
        $moderator = User::factory()->moderator()->create();
        $author = User::factory()->create();

        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create();

        createSpamComment($author, $mod, ['body' => 'Spam on mod']);

        $addonComment = Comment::factory()->create([
            'commentable_type' => Addon::class,
            'commentable_id' => $addon->id,
            'user_id' => $author->id,
            'body' => 'Spam on addon',
        ]);
        $addonComment->update(['spam_status' => SpamStatus::SPAM]);

        $this->actingAs($moderator);

        Livewire::test('pages::admin.spam-review')
            ->set('filterType', 'mod')
            ->assertSee('Spam on mod')
            ->set('filterType', 'addon')
            ->assertDontSee('Spam on mod');
    });

    it('filters by author username', function (): void {
        $moderator = User::factory()->moderator()->create();
        $alice = User::factory()->create(['name' => 'alice-spammer']);
        $bob = User::factory()->create(['name' => 'bob-spammer']);
        $mod = Mod::factory()->create();

        createSpamComment($alice, $mod, ['body' => 'Alice spam body']);
        createSpamComment($bob, $mod, ['body' => 'Bob spam body']);

        $this->actingAs($moderator);

        Livewire::test('pages::admin.spam-review')
            ->set('filterAuthor', 'alice')
            ->assertSee('Alice spam body')
            ->assertDontSee('Bob spam body');
    });
});

describe('actions', function (): void {
    it('confirms spam, calls Akismet submit-spam, records metadata, and tracks the action', function (): void {
        $moderator = User::factory()->moderator()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = createSpamComment($author, $mod);

        $this->mock(SpamChecker::class, function (MockInterface $mock) use ($comment): void {
            $mock->shouldReceive('markAsSpam')
                ->once()
                ->with(Mockery::on(fn (Comment $argument): bool => $argument->id === $comment->id));
        });

        $this->actingAs($moderator);

        Livewire::test('pages::admin.spam-review')
            ->call('openActionModal', $comment->id, 'confirm_spam')
            ->set('actionNote', 'Clear spam')
            ->call('executeAction')
            ->assertSet('showActionModal', false);

        $comment->refresh();

        expect($comment->spam_reviewed_at)->not->toBeNull();
        expect($comment->spam_reviewed_by)->toBe($moderator->id);
        expect($comment->isSpam())->toBeTrue();
        expect($comment->deleted_at)->toBeNull();

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::COMMENT_MARK_SPAM->value)
            ->where('visitable_id', $comment->id)
            ->where('visitable_type', Comment::class)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
        expect($event->reason)->toBe('Clear spam');
    });

    it('marks a comment as ham, calls Akismet submit-ham, changes status to clean, and tracks the action', function (): void {
        $moderator = User::factory()->moderator()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = createSpamComment($author, $mod);

        $this->mock(SpamChecker::class, function (MockInterface $mock) use ($comment): void {
            $mock->shouldReceive('markAsHam')
                ->once()
                ->with(Mockery::on(fn (Comment $argument): bool => $argument->id === $comment->id));
        });

        $this->actingAs($moderator);

        Livewire::test('pages::admin.spam-review')
            ->call('openActionModal', $comment->id, 'mark_as_ham')
            ->set('actionNote', 'False positive')
            ->call('executeAction')
            ->assertSet('showActionModal', false);

        $comment->refresh();

        expect($comment->isSpam())->toBeFalse();
        expect($comment->spam_status)->toBe(SpamStatus::CLEAN);

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::COMMENT_MARK_CLEAN->value)
            ->where('visitable_id', $comment->id)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
        expect($event->reason)->toBe('False positive');
    });

    it('soft-deletes a comment without calling Akismet and tracks the action', function (): void {
        $moderator = User::factory()->moderator()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = createSpamComment($author, $mod);

        $this->mock(SpamChecker::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('markAsSpam');
            $mock->shouldNotReceive('markAsHam');
        });

        $this->actingAs($moderator);

        Livewire::test('pages::admin.spam-review')
            ->call('openActionModal', $comment->id, 'soft_delete')
            ->call('executeAction');

        $comment->refresh();

        expect($comment->deleted_at)->not->toBeNull();
        expect($comment->isSpam())->toBeTrue();

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::COMMENT_SOFT_DELETE->value)
            ->where('visitable_id', $comment->id)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
    });

    it('hard-deletes a comment and descendants without calling Akismet', function (): void {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $root = createSpamComment($author, $mod);
        $reply = Comment::factory()->create([
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id,
            'user_id' => $author->id,
            'parent_id' => $root->id,
            'root_id' => $root->id,
        ]);

        $this->mock(SpamChecker::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('markAsSpam');
            $mock->shouldNotReceive('markAsHam');
        });

        $this->actingAs($admin);

        Livewire::test('pages::admin.spam-review')
            ->call('openActionModal', $root->id, 'hard_delete')
            ->call('executeAction');

        expect(Comment::query()->whereKey($root->id)->exists())->toBeFalse();
        expect(Comment::query()->whereKey($reply->id)->exists())->toBeFalse();

        $event = TrackingEvent::query()
            ->where('event_name', TrackingEventType::COMMENT_HARD_DELETE->value)
            ->where('visitable_id', $root->id)
            ->first();

        expect($event)->not->toBeNull();
        expect($event->is_moderation_action)->toBeTrue();
    });

    it('blocks moderators from hard-deleting', function (): void {
        $moderator = User::factory()->moderator()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = createSpamComment($author, $mod);

        $this->actingAs($moderator);

        Livewire::test('pages::admin.spam-review')
            ->call('openActionModal', $comment->id, 'hard_delete')
            ->call('executeAction')
            ->assertStatus(403);

        expect(Comment::query()->whereKey($comment->id)->exists())->toBeTrue();
    });
});

describe('context link', function (): void {
    it('renders a link back to the comment in context', function (): void {
        $moderator = User::factory()->moderator()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = createSpamComment($author, $mod);

        $this->actingAs($moderator);

        Livewire::test('pages::admin.spam-review')
            ->assertSeeHtml('href="'.e($comment->getUrl()).'"');
    });
});
