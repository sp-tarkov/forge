<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush(); // Prevent rate limiting interference.

    // Disable honeypot spam protection for tests
    config(['honeypot.enabled' => false]);

    // Create a default SPT version that will be used by mod versions
    SptVersion::factory()->create(['version' => '1.0.0']);
});

describe('Creation', function (): void {
    it('creates a comment, clears the form, and enforces validation and rate limiting in one flow', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $commentText = 'This is a test comment with more than minimum length.';
        $secondCommentText = 'This is a second comment that should be rate limited.';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText('Post Comment');

        // Minimum-length validation rejects a too-short body.
        $page->assertSee('Post Comment')
            ->assertPresent('@new-comment-body')
            ->type('@new-comment-body', 'Hi')
            ->press('Post Comment')
            ->assertSee('must be at least');

        // A valid comment is created, appears in the listing, and the form clears.
        $page->type('@new-comment-body', $commentText)
            ->press('Post Comment')
            ->assertSeeIn('#comments', $commentText)
            ->assertValue('@new-comment-body', '');

        // A second comment immediately afterward is rate limited and never appears.
        $page->type('@new-comment-body', $secondCommentText)
            ->press('Post Comment')
            ->assertDontSee($secondCommentText)
            ->assertNoJavaScriptErrors();
    });

    it('allows administrators to bypass rate limiting', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $commentText1 = 'This is the first admin comment.';
        $commentText2 = 'This is the second admin comment.';

        $this->actingAs($admin);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText('Post Comment');

        $page->assertPresent('@new-comment-body')
            ->type('@new-comment-body', $commentText1)
            ->press('Post Comment')
            ->assertSee($commentText1)
            ->type('@new-comment-body', $commentText2)
            ->press('Post Comment')
            ->assertSee($commentText2)
            ->assertNoJavaScriptErrors();
    });

    it('allows moderators to bypass rate limiting', function (): void {
        $moderator = User::factory()->moderator()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);
        $commentText1 = 'This is the first moderator comment.';
        $commentText2 = 'This is the second moderator comment.';

        $this->actingAs($moderator);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText('Post Comment');

        $page->assertPresent('@new-comment-body')
            ->type('@new-comment-body', $commentText1)
            ->press('Post Comment')
            ->assertSee($commentText1)
            ->type('@new-comment-body', $commentText2)
            ->press('Post Comment')
            ->assertSee($commentText2)
            ->assertNoJavaScriptErrors();
    });
});

describe('Replies', function (): void {
    it('opens and cancels the reply form, validates, then posts a reply that clears the form', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a test comment to reply to.',
        ]);
        $replyText = 'This is my reply to the test comment.';

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText('This is a test comment to reply to.');

        // The reply form opens then cancels cleanly.
        $page->click('@reply-button-'.$comment->id)
            ->waitForText('Reply To Comment')
            ->assertPresent('@reply-body-'.$comment->id)
            ->assertSee('Post Reply')
            ->click('@cancel-reply-body-'.$comment->id)
            ->assertDontSee('Reply To Comment');

        // Re-opening the form, a too-short reply is rejected by validation.
        $page->click('@reply-button-'.$comment->id)
            ->waitForText('Reply To Comment')
            ->type('@reply-body-'.$comment->id, 'Hi')
            ->press('Post Reply')
            ->assertSee('must be at least');

        // A valid reply posts, appears in the listing, and the form closes.
        $page->type('@reply-body-'.$comment->id, $replyText)
            ->press('Post Reply')
            ->waitForText($replyText)
            ->assertSee($replyText)
            ->assertDontSee('Reply To Comment')
            ->assertNoJavaScriptErrors();
    });
});

describe('Editing', function (): void {
    it('opens the edit form with existing content, validates, saves, and cancels without saving', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $originalText = 'This is the original comment text that should appear in the edit form.';
        $editedText = 'This is the edited comment text with more content.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $originalText,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText($originalText);

        // The edit form opens prefilled with the existing body.
        $page->click('@edit-button-'.$comment->id)
            ->assertSee('Edit Comment')
            ->assertPresent('@edit-body-'.$comment->id)
            ->assertValue('@edit-body-'.$comment->id, $originalText);

        // A too-short edit is rejected by validation.
        $page->clear('@edit-body-'.$comment->id)
            ->type('@edit-body-'.$comment->id, 'Hi')
            ->press('Update Comment')
            ->assertSee('must be at least');

        // Cancelling discards the edit and keeps the original text.
        $page->clear('@edit-body-'.$comment->id)
            ->type('@edit-body-'.$comment->id, 'This edited text should not be saved when cancelled.')
            ->click('@cancel-edit-body-'.$comment->id)
            ->assertSee($originalText)
            ->assertDontSee('This edited text should not be saved when cancelled.');

        // Re-opening, a valid edit saves and replaces the original in the listing.
        $page->click('@edit-button-'.$comment->id)
            ->clear('@edit-body-'.$comment->id)
            ->type('@edit-body-'.$comment->id, $editedText)
            ->press('Update Comment')
            ->assertSee($editedText)
            ->assertDontSee($originalText)
            ->assertNoJavaScriptErrors();
    });
});

describe('Deletion', function (): void {
    it('requires confirmation, cancels, then deletes the comment after confirming', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $commentText = 'This comment should be deleted after confirmation.';
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => $commentText,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText($commentText);

        // Cancelling the confirmation dialog leaves the comment in place.
        $page->assertSee($commentText)
            ->click('@delete-button-'.$comment->id)
            ->assertSee('Remove Comment')
            ->click('@cancel-delete-comment')
            ->assertSee($commentText);

        // Confirming the dialog removes the comment container from the page.
        $page->click('@delete-button-'.$comment->id)
            ->assertSee('Remove Comment')
            ->click('@confirm-delete-comment')
            ->assertNotPresent('.comment-container-'.$comment->id)
            ->assertNoJavaScriptErrors();
    });
});

describe('Reactions', function (): void {
    it('toggles a reaction on and off updating the like count', function (): void {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $author->id,
            'body' => 'This comment should allow toggling likes.',
        ]);

        $this->actingAs($reactor);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText('0 Likes');

        $page->assertSee('0 Likes')
            ->click('@reaction-button-'.$comment->id)
            ->assertSee('1 Like')
            ->click('@reaction-button-'.$comment->id)
            ->assertSee('0 Likes')
            ->assertNoJavaScriptErrors();
    });
});

describe('Subscription', function (): void {
    it('toggles subscription on and off and persists the state across page loads', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText('Subscribe');

        // Subscribing then unsubscribing flips the label back and forth.
        $page->assertPresent('@subscription-toggle')
            ->assertSee('Subscribe')
            ->click('@subscription-toggle')
            ->assertSee('Subscribed')
            ->click('@subscription-toggle')
            ->assertSee('Subscribe');

        // Subscribing then reloading keeps the subscribed state.
        $page->click('@subscription-toggle')
            ->waitForText('Subscribed')
            ->navigate($mod->detail_url.'#comments')
            ->waitForText('Subscribed')
            ->assertSee('Subscribed')
            ->assertNoJavaScriptErrors();
    });
});

describe('Reply threads', function (): void {
    it('expands and collapses reply threads', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This is a root comment with replies.',
        ]);

        $replyText = 'This is a reply that should be toggleable.';
        Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'parent_id' => $rootComment->id,
            'root_id' => $rootComment->id,
            'body' => $replyText,
        ]);

        $this->actingAs($user);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText($replyText);

        // Replies are shown by default; toggling collapses then re-expands them.
        $page->assertSee($replyText)
            ->click('@toggle-replies-'.$rootComment->id)
            ->assertDontSee($replyText)
            ->click('@toggle-replies-'.$rootComment->id)
            ->assertSee($replyText)
            ->assertNoJavaScriptErrors();
    });
});

describe('Pinning', function (): void {
    it('allows moderators to pin a comment through the moderation dropdown', function (): void {
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);

        // Create several comments and pin one in the middle.
        $comments = [];
        for ($i = 1; $i <= 5; $i++) {
            $comments[] = Comment::factory()->create([
                'commentable_id' => $mod->id,
                'commentable_type' => Mod::class,
                'user_id' => $user->id,
                'body' => sprintf('This is test comment number %d.', $i),
            ]);
        }

        $commentToPin = $comments[2];

        $this->actingAs($moderator);

        $page = visit($mod->detail_url.'#comments')
            ->waitForText($commentToPin->body);

        $page->assertSee($commentToPin->body)
            ->assertDontSeeIn('.comment-container-'.$commentToPin->id, 'Pinned')
            ->click('.comment-container-'.$commentToPin->id.' [data-flux-dropdown] button[data-flux-button]')
            ->assertSeeIn('.comment-container-'.$commentToPin->id, 'Pin Comment')
            ->click('.comment-container-'.$commentToPin->id.' .action-pin')
            ->click('@confirm-pin-comment')
            ->assertPresent('.comment-container-'.$commentToPin->id.' .text-cyan-500:has-text("Pinned")')
            ->assertNoJavaScriptErrors();
    });
});

describe('Deep links', function (): void {
    it('scrolls to and highlights a deep-linked root comment on the first page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        // Target is the oldest among the first-page comments so it sits at the bottom, forcing a scroll.
        $target = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'TARGET ROOT on first page for deep link test.',
            'created_at' => now()->subHour(),
        ]);

        Comment::factory()->count(9)->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(5),
        ]);

        $page = visit($target->getUrl())
            ->on()->desktop()
            ->waitForText('TARGET ROOT on first page for deep link test.');

        $anchorId = $target->getHashId();
        $anchorPresent = $page->script(sprintf(
            'document.getElementById(%s) !== null',
            json_encode($anchorId, JSON_THROW_ON_ERROR),
        ));

        $page->assertSee('TARGET ROOT on first page for deep link test.')
            ->assertNoJavaScriptErrors();

        expect($anchorPresent)->toBeTrue();
    });

    it('navigates to the correct page for a deep-linked root comment on a later page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        // The target must be older than 10 other comments so it lands on commentPage=2.
        $target = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'TARGET ROOT on page two for deep link test.',
            'created_at' => now()->subHours(2),
        ]);

        Comment::factory()->count(10)->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(5),
        ]);

        $page = visit($target->getUrl())
            ->on()->desktop()
            ->waitForText('TARGET ROOT on page two for deep link test.');

        $page->assertSee('TARGET ROOT on page two for deep link test.')
            ->assertNoJavaScriptErrors();
    });

    it('navigates to the correct page and loads replies for a deep-linked reply', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $targetRoot = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'Root comment that holds the target reply.',
            'created_at' => now()->subHours(2),
        ]);

        Comment::factory()->count(10)->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'created_at' => now()->subMinutes(5),
        ]);

        $targetReply = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'parent_id' => $targetRoot->id,
            'root_id' => $targetRoot->id,
            'body' => 'TARGET REPLY on page two for deep link test.',
            'created_at' => now()->subHour(),
        ]);

        $page = visit($targetReply->getUrl())
            ->on()->desktop()
            ->waitForText('TARGET REPLY on page two for deep link test.');

        $page->assertSee('TARGET REPLY on page two for deep link test.')
            ->assertSee('Root comment that holds the target reply.')
            ->assertNoJavaScriptErrors();
    });
});

describe('Rendering', function (): void {
    it('renders comment usernames in the inherited dark canvas colour', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->create(['mod_id' => $mod->id, 'spt_version_constraint' => '1.0.0']);
        Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'user_id' => $user->id,
            'body' => 'This comment checks the username colour.',
        ]);

        $page = visit($mod->detail_url.'#comments')
            ->on()->desktop()
            ->waitForText('This comment checks the username colour.');

        // The username link has no text colour utility; it inherits the canvas text colour, which the
        // color-scheme: dark rule in app.css resolves to white.
        $page->assertScript(
            'getComputedStyle(document.querySelector(\'#comments a[href*="/user/"]\')).color',
            'rgb(255, 255, 255)',
        )->assertNoJavaScriptErrors();
    });
});
