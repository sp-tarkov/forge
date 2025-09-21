<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

beforeEach(function (): void {
    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();
    $this->conversation = Conversation::factory()->create([
        'user1_id' => $this->user1->id,
        'user2_id' => $this->user2->id,
    ]);
});

it('converts markdown to HTML for message content', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => '**Bold text** and *italic text*',
    ]);

    expect($message->content_html)
        ->toContain('<strong>Bold text</strong>')
        ->toContain('<em>italic text</em>');
});

it('properly handles lists in markdown', function (): void {
    $content = "Here's a list:\n- Item 1\n- Item 2\n- Item 3";

    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => $content,
    ]);

    expect($message->content_html)
        ->toContain('<ul>')
        ->toContain('<li>Item 1</li>')
        ->toContain('<li>Item 2</li>')
        ->toContain('<li>Item 3</li>');
});

it('converts links in markdown', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => 'Check out [this link](https://example.com)',
    ]);

    expect($message->content_html)
        ->toContain('href="https://example.com"')
        ->toContain('target="_blank"')
        ->toContain('>this link</a>');
});

it('handles code blocks in markdown', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => 'Here is some `inline code` example',
    ]);

    expect($message->content_html)
        ->toContain('<code>inline code</code>');
});

it('handles fenced code blocks with pre tags', function (): void {
    $content = "```json\n{\n  \"test\": true\n}\n```";

    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => $content,
    ]);

    expect($message->content_html)
        ->toContain('<pre>')
        ->toContain('<code class="language-json">')
        ->toContain('"test": true');
});

it('sanitizes dangerous HTML in markdown', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => '**Bold text** with some <script>malicious()</script> content',
    ]);

    expect($message->content_html)
        ->not->toContain('<script>')
        ->not->toContain('</script>')
        ->toContain('<strong>Bold text</strong>')
        ->toContain('malicious()'); // The text remains but without script tags
});

it('preserves line breaks in markdown', function (): void {
    $content = "Line 1  \nLine 2";

    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => $content,
    ]);

    expect($message->content_html)
        ->toContain('<br />');
});

it('handles blockquotes in markdown', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => '> This is a quote',
    ]);

    expect($message->content_html)
        ->toContain('<blockquote>')
        ->toContain('This is a quote');
});

it('handles strikethrough text', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => '~~strikethrough text~~',
    ]);

    expect($message->content_html)
        ->toContain('<del>strikethrough text</del>');
});

it('removes forbidden HTML elements', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => 'Normal text with forbidden elements',
    ]);

    expect($message->content_html)
        ->not->toContain('<iframe')
        ->not->toContain('<img')
        ->toContain('Normal text');

    // Test that actual forbidden elements are removed
    $message2 = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => 'Text before <iframe src="evil.com"></iframe> text after',
    ]);

    expect($message2->content_html)
        ->not->toContain('<iframe')
        ->not->toContain('evil.com');
});

it('caches the content_html attribute', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user1->id,
        'content' => '**Bold text**',
    ]);

    // First access
    $html1 = $message->content_html;

    // Second access should use cached value
    $html2 = $message->content_html;

    expect($html1)->toBe($html2)
        ->toContain('<strong>Bold text</strong>');
});
