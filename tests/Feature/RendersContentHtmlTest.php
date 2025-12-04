<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
    $this->conversation = Conversation::factory()->create([
        'user1_id' => $this->user1->id,
        'user2_id' => $this->user2->id,
    ]);
});

it('generates content_html when creating a message with content', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => '**Bold text** and *italic*',
    ]);

    expect($message->content_html)
        ->toContain('<strong>Bold text</strong>')
        ->toContain('<em>italic</em>');
});

it('regenerates content_html when content is updated', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => 'Original content',
    ]);

    expect($message->content_html)->toContain('Original content');

    $message->update(['content' => 'Updated content']);

    expect($message->fresh()->content_html)
        ->toContain('Updated content')
        ->not->toContain('Original content');
});

it('handles empty content gracefully', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => '',
    ]);

    expect($message->content_html)->toBe('');
});

it('sanitizes html in message content to prevent xss', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => 'Hello <script>alert("xss")</script> World',
    ]);

    expect($message->content_html)
        ->not->toContain('<script>')
        ->not->toContain('</script>')
        ->toContain('Hello')
        ->toContain('World');
});

it('regenerates content_html using the regenerateContentHtml method', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => '**Bold Test**',
    ]);

    // Manually clear the cached value
    $message->content_html = null;
    $message->saveQuietly();

    expect($message->fresh()->content_html)->toBeNull();

    $message->regenerateContentHtml();

    expect($message->fresh()->content_html)->toContain('<strong>Bold Test</strong>');
});
