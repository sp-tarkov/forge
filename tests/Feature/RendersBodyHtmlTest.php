<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->mod = Mod::factory()->create();
});

it('generates body_html when creating a comment with body', function (): void {
    $comment = Comment::factory()->create([
        'user_id' => $this->user->id,
        'commentable_id' => $this->mod->id,
        'commentable_type' => Mod::class,
        'body' => '**Bold text** and *italic*',
    ]);

    expect($comment->body_html)
        ->toContain('<strong>Bold text</strong>')
        ->toContain('<em>italic</em>');
});

it('regenerates body_html when body is updated', function (): void {
    $comment = Comment::factory()->create([
        'user_id' => $this->user->id,
        'commentable_id' => $this->mod->id,
        'commentable_type' => Mod::class,
        'body' => 'Original body',
    ]);

    expect($comment->body_html)->toContain('Original body');

    $comment->update(['body' => 'Updated body']);

    expect($comment->fresh()->body_html)
        ->toContain('Updated body')
        ->not->toContain('Original body');
});

it('handles empty body gracefully', function (): void {
    $comment = Comment::factory()->create([
        'user_id' => $this->user->id,
        'commentable_id' => $this->mod->id,
        'commentable_type' => Mod::class,
        'body' => '',
    ]);

    expect($comment->body_html)->toBe('');
});

it('sanitizes html in comment body to prevent xss', function (): void {
    $comment = Comment::factory()->create([
        'user_id' => $this->user->id,
        'commentable_id' => $this->mod->id,
        'commentable_type' => Mod::class,
        'body' => 'Hello <script>alert("xss")</script> World',
    ]);

    expect($comment->body_html)
        ->not->toContain('<script>')
        ->not->toContain('</script>')
        ->toContain('Hello')
        ->toContain('World');
});

it('regenerates body_html using the regenerateBodyHtml method', function (): void {
    $comment = Comment::factory()->create([
        'user_id' => $this->user->id,
        'commentable_id' => $this->mod->id,
        'commentable_type' => Mod::class,
        'body' => '# Test',
    ]);

    // Manually clear the cached value
    $comment->body_html = null;
    $comment->saveQuietly();

    expect($comment->fresh()->body_html)->toBeNull();

    $comment->regenerateBodyHtml();

    expect($comment->fresh()->body_html)->toContain('<h1>Test</h1>');
});
