<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;

test('getUrl returns null when commentable is null', function (): void {
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => Mod::class,
    ]);

    // Simulate a scenario where commentable is deleted/null
    $comment->setRelation('commentable', null);

    expect($comment->getUrl())->toBeNull();
});

test('getUrl returns correct url when commentable exists', function (): void {
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => Mod::class,
    ]);

    $url = $comment->getUrl();
    expect($url)
        ->toBeString()
        ->toContain($mod->getCommentableUrl());

    // The hash ID format includes the tab hash from the commentable
    expect($url)->toContain('comment-'.$comment->id);
});

test('getHashId returns correct hash when commentable is null', function (): void {
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => Mod::class,
    ]);

    // Simulate a scenario where commentable is deleted/null
    $comment->setRelation('commentable', null);

    expect($comment->getHashId())->toBe('comment-'.$comment->id);
});

test('getHashId returns correct hash with tab when commentable exists', function (): void {
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => Mod::class,
    ]);

    expect($comment->getHashId())
        ->toBeString()
        ->toContain('comment-'.$comment->id);
});

test('getTrackingUrl returns empty string when commentable is null', function (): void {
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => Mod::class,
    ]);

    // Simulate a scenario where commentable is deleted/null
    $comment->setRelation('commentable', null);

    expect($comment->getTrackingUrl())->toBe('');
});

test('getTrackingTitle returns generic title when commentable is null', function (): void {
    $mod = Mod::factory()->create();
    $comment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => Mod::class,
    ]);

    // Simulate a scenario where commentable is deleted/null
    $comment->setRelation('commentable', null);

    expect($comment->getTrackingTitle())->toBe('Comment');
});

test('getTrackingTitle returns user profile title when commentable is user', function (): void {
    $user = User::factory()->create(['name' => 'John Doe']);
    $comment = Comment::factory()->create([
        'commentable_id' => $user->id,
        'commentable_type' => User::class,
    ]);

    expect($comment->getTrackingTitle())->toBe("Comment on John Doe's profile");
});

test('cannot save comment with body exceeding max length', function (): void {
    $maxLength = config('comments.validation.max_length', 10000);
    $mod = Mod::factory()->create();
    $user = User::factory()->create();

    // Create a comment with body exceeding max length
    $longBody = str_repeat('a', $maxLength + 1);

    expect(fn () => Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => Mod::class,
        'user_id' => $user->id,
        'body' => $longBody,
    ]))->toThrow(InvalidArgumentException::class, sprintf('Comment body cannot exceed %d characters.', $maxLength));
});

test('can save comment at exactly max length', function (): void {
    $maxLength = config('comments.validation.max_length', 10000);
    $mod = Mod::factory()->create();
    $user = User::factory()->create();

    $bodyAtMaxLength = str_repeat('a', $maxLength);

    $comment = Comment::factory()->create([
        'commentable_id' => $mod->id,
        'commentable_type' => Mod::class,
        'user_id' => $user->id,
        'body' => $bodyAtMaxLength,
    ]);

    expect($comment)->toBeInstanceOf(Comment::class)
        ->and(mb_strlen($comment->body))->toBe($maxLength);
});
