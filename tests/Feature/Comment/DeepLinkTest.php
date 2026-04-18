<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
    config(['honeypot.enabled' => false]);

    SptVersion::factory()->create(['version' => '1.0.0']);
});

describe('Comment Deep Link Tests', function (): void {
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
