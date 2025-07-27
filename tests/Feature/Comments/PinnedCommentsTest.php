<?php

declare(strict_types=1);

namespace Tests\Feature\Comments;

use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinnedCommentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that mod owners can pin comments.
     */
    public function test_mod_owner_can_pin_comment(): void
    {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($owner);

        $this->assertTrue($owner->can('pin', $comment));

        // Test pinning
        $comment->update(['pinned_at' => now()]);
        $this->assertNotNull($comment->fresh()->pinned_at);
        $this->assertTrue($comment->fresh()->isPinned());
    }

    /**
     * Test that mod authors can pin comments.
     */
    public function test_mod_author_can_pin_comment(): void
    {
        $author = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();
        $mod->authors()->attach($author);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($author);

        $this->assertTrue($author->can('pin', $comment));
    }

    /**
     * Test that moderators can pin comments.
     */
    public function test_moderator_can_pin_comment(): void
    {
        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($moderator);

        $this->assertTrue($moderator->can('pin', $comment));
    }

    /**
     * Test that administrators can pin comments.
     */
    public function test_administrator_can_pin_comment(): void
    {
        $adminRole = UserRole::factory()->administrator()->create();
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($admin);

        $this->assertTrue($admin->can('pin', $comment));
    }

    /**
     * Test that regular users cannot pin comments.
     */
    public function test_regular_user_cannot_pin_comment(): void
    {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $this->actingAs($user);

        $this->assertFalse($user->can('pin', $comment));
    }

    /**
     * Test that pinned comments appear first in ordering.
     */
    public function test_pinned_comments_appear_first(): void
    {
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        // Create comments with different timestamps
        $oldComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'created_at' => now()->subDays(3),
        ]);

        $newComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'created_at' => now()->subDay(),
        ]);

        $pinnedComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'created_at' => now()->subDays(2),
            'pinned_at' => now()->subHour(),
        ]);

        // Get root comments with ordering (rootComments() now includes pinned ordering)
        $comments = $mod->rootComments()->get();

        // Pinned comment should be first (non-null pinned_at should come first)
        $this->assertEquals($pinnedComment->id, $comments->first()->id);

        // Then newest unpinned comment
        $this->assertEquals($newComment->id, $comments->get(1)->id);

        // Then oldest unpinned comment
        $this->assertEquals($oldComment->id, $comments->get(2)->id);
    }

    /**
     * Test multiple pinned comments ordering by pin time.
     */
    public function test_multiple_pinned_comments_ordered_by_pin_time(): void
    {
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        // Create pinned comments with different pin times
        $firstPinned = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now()->subHours(3),
        ]);

        $secondPinned = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now()->subHours(2),
        ]);

        $latestPinned = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now()->subHour(),
        ]);

        // Get root comments with ordering (rootComments() now includes pinned ordering)
        $comments = $mod->rootComments()->get();

        // Latest pinned should be first
        $this->assertEquals($latestPinned->id, $comments->get(0)->id);
        $this->assertEquals($secondPinned->id, $comments->get(1)->id);
        $this->assertEquals($firstPinned->id, $comments->get(2)->id);
    }

    /**
     * Test unpinning a comment.
     */
    public function test_unpinning_comment(): void
    {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now(),
        ]);

        $this->actingAs($owner);

        // Verify comment is pinned
        $this->assertTrue($comment->isPinned());

        // Unpin the comment
        $comment->update(['pinned_at' => null]);

        // Verify comment is no longer pinned
        $this->assertFalse($comment->fresh()->isPinned());
        $this->assertNull($comment->fresh()->pinned_at);
    }

    /**
     * Test that mod owners see the owner pin action but moderators don't.
     */
    public function test_owner_pin_action_visibility(): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $regularUser = User::factory()->create();

        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();
        $mod->authors()->attach($author);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        // Mod owner should see the owner pin action
        $this->assertTrue($owner->can('showOwnerPinAction', $comment));

        // Mod author should see the owner pin action
        $this->assertTrue($author->can('showOwnerPinAction', $comment));

        // Regular user should not see the owner pin action
        $this->assertFalse($regularUser->can('showOwnerPinAction', $comment));

        // Moderator should not see the owner pin action (they use the moderation dropdown)
        $this->assertFalse($moderator->can('showOwnerPinAction', $comment));
    }

    /**
     * Test that reply comments cannot be pinned.
     */
    public function test_reply_comments_cannot_be_pinned(): void
    {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();

        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $replyComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'parent_id' => $rootComment->id,
            'root_id' => $rootComment->id,
        ]);

        $moderatorRole = UserRole::factory()->moderator()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole($moderatorRole);

        // Root comment should be pinnable
        $this->assertTrue($owner->can('pin', $rootComment));
        $this->assertTrue($moderator->can('pin', $rootComment));

        // Reply comment should not be pinnable
        $this->assertFalse($owner->can('pin', $replyComment));
        $this->assertFalse($moderator->can('pin', $replyComment));

        // Reply comment should not show the owner pin action
        $this->assertFalse($owner->can('showOwnerPinAction', $replyComment));
    }
}
