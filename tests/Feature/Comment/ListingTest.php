<?php

declare(strict_types=1);

use App\Livewire\Comment\Listing;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Models\UserRole;
use Livewire\Livewire;

it('should not allow a guest to create a comment', function (): void {
    $mod = Mod::factory()->create();

    Livewire::test(Listing::class, ['commentable' => $mod])
        ->set('form.body', 'This is a test comment.')
        ->call('create')
        ->assertForbidden();
});

it('should not allow a guest to reply to a comment', function (): void {
    $mod = Mod::factory()->create();
    $parentComment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
    ]);

    Livewire::test(Listing::class, ['commentable' => $mod])
        ->set('form.body', 'This is a reply.')
        ->call('create', $parentComment->id)
        ->assertForbidden();
});

it('should allow a user to create a comment', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->set('form.body', 'This is a test comment.')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('comments', [
        'body' => 'This is a test comment.',
        'user_id' => $user->id,
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
    ]);
});

it('should allow a user to reply to a comment', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $parentComment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
    ]);

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->set('form.body', 'This is a reply.')
        ->call('create', $parentComment->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('comments', [
        'body' => 'This is a reply.',
        'user_id' => $user->id,
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
        'parent_id' => $parentComment->id,
    ]);
});

it('should allow a user to update their own comment', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
        'created_at' => now(),  // Override factory to ensure comment is fresh
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->set('form.comment', $comment)
        ->set('form.body', 'This is an updated comment.')
        ->call('update')
        ->assertHasNoErrors();

    $comment->refresh();

    $this->assertEquals('This is an updated comment.', $comment->body);
    $this->assertNotNull($comment->edited_at);
});

it('should not allow a user to update a comment that is older than 5 minutes', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
        'created_at' => now()->subMinutes(6),
    ]);

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->set('form.comment', $comment)
        ->set('form.body', 'This is an updated comment.')
        ->call('update')
        ->assertForbidden();
});

it('should show an edited indicator when a comment has been edited', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'user_id' => $user->id,
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
        'edited_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->assertSeeHtml('<span class="text-gray-500 dark:text-gray-400" title="'.$comment->edited_at->format('Y-m-d H:i:s').'">*</span>');
});

it("should not allow a user to update another user's comment", function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create(['user_id' => $otherUser->id, 'commentable_id' => $mod->id, 'commentable_type' => $mod::class]);

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->set('form.comment', $comment)
        ->set('form.body', 'This is an updated comment.')
        ->call('update')
        ->assertForbidden();
});

it('should allow a moderator to update any comment', function (): void {
    $moderatorRole = UserRole::factory()->moderator()->create();
    $moderator = User::factory()->create();
    $moderator->assignRole($moderatorRole);

    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create(['user_id' => $user->id, 'commentable_id' => $mod->id, 'commentable_type' => $mod::class]);

    Livewire::actingAs($moderator)
        ->test(Listing::class, ['commentable' => $mod])
        ->set('form.comment', $comment)
        ->set('form.body', 'This is an updated comment.')
        ->call('update')
        ->assertHasNoErrors();
});

it('should correctly display the comment count', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    Comment::factory()->count(5)->create(['commentable_id' => $mod->id, 'commentable_type' => $mod::class]);

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->assertSeeHtml('<span class="font-normal text-slate-400">(5)</span>');
});

it('should correctly paginate comments', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    Comment::factory()->count(15)->create([
        'commentable_id' => $mod->id,
        'commentable_type' => $mod::class,
    ]);

    $test = Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod]);

    $test->assertViewHas('rootComments', fn($paginator): bool => $paginator->total() === 15);

    $this->assertEquals(2, substr_count((string) $test->html(), '<nav role="navigation" aria-label="Pagination Navigation"'));
});
