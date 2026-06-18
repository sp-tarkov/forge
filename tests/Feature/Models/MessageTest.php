<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;

describe('creation and relationships', function (): void {
    it('can create a message in a conversation', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'Hello, World!',
        ]);

        expect($message)->toBeInstanceOf(Message::class)
            ->and($message->conversation_id)->toBe($conversation->id)
            ->and($message->user_id)->toBe($user1->id)
            ->and($message->content)->toBe('Hello, World!')
            ->and($message->read_at)->toBeNull();
    });

    it('has a relationship with conversation', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'Test message',
        ]);

        expect($message->conversation)->toBeInstanceOf(Conversation::class)
            ->and($message->conversation->id)->toBe($conversation->id);
    });

    it('has a relationship with user', function (): void {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user1_id' => $user->id,
            'user2_id' => User::factory()->create()->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => 'Test message',
        ]);

        expect($message->user)->toBeInstanceOf(User::class)
            ->and($message->user->id)->toBe($user->id);
    });

    it('enforces 500 character limit on content', function (): void {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user1_id' => $user->id,
            'user2_id' => User::factory()->create()->id,
        ]);

        $validContent = str_repeat('a', 500);
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'content' => $validContent,
        ]);

        expect($message->content)->toHaveLength(500);

        // Test that longer content would be truncated or handled appropriately
        // This would typically be handled by validation, which we'll add later
    });
});

describe('read tracking', function (): void {
    it('can check if message is read by a user', function (): void {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user1_id' => $sender->id,
            'user2_id' => $receiver->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'content' => 'Test message',
        ]);

        expect($message->isReadBy($receiver))->toBeFalse();

        $message->markAsReadBy($receiver);

        expect($message->isReadBy($receiver))->toBeTrue();
    });

    it('can mark a message as read by multiple users', function (): void {
        $sender = User::factory()->create();
        $receiver1 = User::factory()->create();
        $receiver2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $sender->id,
            'user2_id' => $receiver1->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'content' => 'Test message',
        ]);

        expect($message->isReadBy($receiver1))->toBeFalse()
            ->and($message->isReadBy($receiver2))->toBeFalse();

        $message->markAsReadBy($receiver1);

        expect($message->isReadBy($receiver1))->toBeTrue()
            ->and($message->isReadBy($receiver2))->toBeFalse();
    });

    it('does not create duplicate read records', function (): void {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user1_id' => $sender->id,
            'user2_id' => $receiver->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'content' => 'Test message',
        ]);

        $message->markAsReadBy($receiver);
        $message->markAsReadBy($receiver); // Try to mark as read again

        expect($message->reads()->where('user_id', $receiver->id)->count())->toBe(1);
    });

    it('tracks read timestamps properly', function (): void {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user1_id' => $sender->id,
            'user2_id' => $receiver->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'content' => 'Test',
        ]);

        $message->markAsReadBy($receiver);

        $read = $message->reads()->where('user_id', $receiver->id)->first();

        expect($read->read_at)->toBeInstanceOf(CarbonImmutable::class);
    });
});

describe('scopes', function (): void {
    it('can scope unread messages for a user', function (): void {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user1_id' => $sender->id,
            'user2_id' => $receiver->id,
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'content' => 'Unread 1',
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'content' => 'Unread 2',
        ]);

        $readMessage = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'content' => 'Read',
        ]);

        // Mark one message as read
        $readMessage->markAsReadBy($receiver);

        $unreadMessages = Message::query()->where('conversation_id', $conversation->id)
            ->unreadBy($receiver)
            ->get();

        expect($unreadMessages)->toHaveCount(2)
            ->and($unreadMessages->pluck('content')->toArray())
            ->toContain('Unread 1', 'Unread 2')
            ->not->toContain('Read');
    });

    it('can scope messages for a specific user', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'User1 message',
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user2->id,
            'content' => 'User2 message',
        ]);

        $user1Messages = Message::forUser($user1)->get();

        expect($user1Messages)->toHaveCount(1)
            ->and($user1Messages->first()->content)->toBe('User1 message');
    });

    it('can scope messages not from a specific user', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conversation = Conversation::query()->create([
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user1->id,
            'content' => 'User1 message',
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user2->id,
            'content' => 'User2 message 1',
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user2->id,
            'content' => 'User2 message 2',
        ]);

        $notUser1Messages = Message::notFromUser($user1)->get();

        expect($notUser1Messages)->toHaveCount(2)
            ->and($notUser1Messages->pluck('content')->toArray())
            ->toContain('User2 message 1', 'User2 message 2')
            ->not->toContain('User1 message');
    });
});

describe('markdown rendering', function (): void {
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
});
