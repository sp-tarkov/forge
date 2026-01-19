<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

describe('comment display', function (): void {
    it('should correctly display the comment count', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        Comment::factory()->count(5)->create(['commentable_id' => $mod->id, 'commentable_type' => $mod::class]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->assertSeeHtml('<span class="font-normal text-slate-400">(5)</span>');
    });

    it('should correctly paginate comments', function (): void {
        $user = User::factory()->create();
        // Use withoutEvents to skip factory's afterCreating callback (SourceCodeLinks)
        $mod = Mod::withoutEvents(fn () => Mod::factory()->create());
        Comment::factory()->count(15)->create([
            'commentable_id' => $mod->id,
            'commentable_type' => $mod::class,
            'user_id' => $user->id,
        ]);

        $test = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod]);

        $test->assertViewHas('rootComments', fn ($paginator): bool => $paginator->total() === 15);

        $this->assertEquals(2, mb_substr_count((string) $test->html(), '<nav role="navigation" aria-label="Pagination Navigation"'));
    });

    it('should display correct commentable display name for user profiles', function (): void {
        $user = User::factory()->create();

        expect($user->getCommentableDisplayName())->toBe('profile');
    });

    it('should display no comments message before the form when authenticated', function (): void {
        $user = User::factory()->create();
        $commentingUser = User::factory()->create();

        $this->actingAs($commentingUser);

        $response = Livewire::test('comment-component', ['commentable' => $user]);

        // Get the rendered HTML
        $html = $response->html();

        // Find positions of key elements
        $noCommentsPos = mb_strpos($html, 'No comments yet');
        $formPos = mb_strpos($html, 'Post Comment');

        // Assert that "No comments yet" appears before the form
        expect($noCommentsPos)->toBeLessThan($formPos);
    });
});
