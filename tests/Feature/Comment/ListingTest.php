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
        'commentable_type' => get_class($mod),
    ]);
});

it('should allow a user to update their own comment', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create(['user_id' => $user->id, 'commentable_id' => $mod->id, 'commentable_type' => get_class($mod)]);

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->set('form.body', 'This is an updated comment.')
        ->call('update', $comment->id)
        ->assertHasNoErrors();
});

it("should not allow a user to update another user's comment", function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create(['user_id' => $otherUser->id, 'commentable_id' => $mod->id, 'commentable_type' => get_class($mod)]);

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->set('form.body', 'This is an updated comment.')
        ->call('update', $comment->id)
        ->assertForbidden();
});

it('should allow a moderator to update any comment', function (): void {
    $moderatorRole = UserRole::factory()->moderator()->create();
    $moderator = User::factory()->create(['user_role_id' => $moderatorRole->id]);
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create(['user_id' => $user->id, 'commentable_id' => $mod->id, 'commentable_type' => get_class($mod)]);

    Livewire::actingAs($moderator)
        ->test(Listing::class, ['commentable' => $mod])
        ->set('form.body', 'This is an updated comment.')
        ->call('update', $comment->id)
        ->assertHasNoErrors();
});

it('should correctly display the comment count', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    Comment::factory()->count(5)->create(['commentable_id' => $mod->id, 'commentable_type' => get_class($mod)]);

    Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod])
        ->assertSeeHtml('<span class="font-normal text-slate-400">(5)</span>');
});

it('should correctly paginate comments', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    Comment::factory()->count(15)->create([
        'commentable_id' => $mod->id,
        'commentable_type' => get_class($mod),
    ]);

    $test = Livewire::actingAs($user)
        ->test(Listing::class, ['commentable' => $mod]);

    $test->assertViewHas('rootComments', function ($paginator) {
        return $paginator->total() === 15;
    });

    $this->assertEquals(2, substr_count($test->html(), '<nav role="navigation" aria-label="Pagination Navigation"'));
});
