<?php

declare(strict_types=1);

use App\Livewire\CommentComponent;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

it('should correctly display the comment count', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create();
    Comment::factory()->count(5)->create(['commentable_id' => $mod->id, 'commentable_type' => $mod::class]);

    Livewire::actingAs($user)
        ->test(CommentComponent::class, ['commentable' => $mod])
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
        ->test(CommentComponent::class, ['commentable' => $mod]);

    $test->assertViewHas('rootComments', fn ($paginator): bool => $paginator->total() === 15);

    $this->assertEquals(2, substr_count((string) $test->html(), '<nav role="navigation" aria-label="Pagination Navigation"'));
});
