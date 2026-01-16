<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('creating comments with log content', function (): void {
    it('should reject creating a comment with Message log pattern', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'Here is my log: [Message: Something went wrong]')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);

        $this->assertDatabaseMissing('comments', [
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
        ]);
    });

    it('should reject creating a comment with Info log pattern', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'Check this log: [Info: Application started]')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);
    });

    it('should reject creating a comment with Warning log pattern', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'Warning log: [Warning: Memory usage high]')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);
    });

    it('should reject creating a comment with Error log pattern', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'Error in logs: [Error: Database connection failed]')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);
    });

    it('should reject creating a comment with timestamped log pattern', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'Log entry: [2024-01-15 10:30:45.123][Info][')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);
    });

    it('should reject creating a comment with timezone and IP log pattern', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'Server log: 2024-01-15 10:30:45.123 +00:00|192.168.1.1.8080|')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);
    });

    it('should reject creating a comment with MongoDB-like log pattern', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'MongoDB log: "_id": "507f1f77bcf86cd799439011"')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);
    });

    it('should reject creating a comment when log content is mixed with normal text', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', "I'm having an issue. Here's the log:\n\n[Error: Connection timeout]\n\nCan someone help?")
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);
    });

    it('should allow creating a comment with normal text', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', 'This is a normal comment without log content.')
            ->call('createComment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'body' => 'This is a normal comment without log content.',
            'user_id' => $user->id,
            'commentable_id' => $mod->id,
        ]);
    });

    it('should allow creating a comment with markdown content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $markdownContent = "# Heading\n\n## Subheading\n\nSome **bold** and *italic* text with a [link](https://example.com).";

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', $markdownContent)
            ->call('createComment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'body' => $markdownContent,
            'user_id' => $user->id,
        ]);
    });
});

describe('replying to comments with log content', function (): void {
    it('should reject replying with log content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_type' => $mod::class,
            'commentable_id' => $mod->id,
            'body' => 'Parent comment',
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set(sprintf('formStates.reply-%d.body', $parentComment->id), '[Error: Something went wrong]')
            ->call('createReply', $parentComment->id)
            ->assertHasErrors([sprintf('formStates.reply-%d.body', $parentComment->id)]);

        expect(Comment::query()->where('parent_id', $parentComment->id)->count())->toBe(0);
    });

    it('should allow replying with normal content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_type' => $mod::class,
            'commentable_id' => $mod->id,
            'body' => 'Parent comment',
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set(sprintf('formStates.reply-%d.body', $parentComment->id), 'This is a valid reply')
            ->call('createReply', $parentComment->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'body' => 'This is a valid reply',
            'parent_id' => $parentComment->id,
            'user_id' => $user->id,
        ]);
    });
});

describe('editing comments with log content', function (): void {
    it('should reject editing a comment to add log content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => $mod::class,
            'commentable_id' => $mod->id,
            'body' => 'Original comment',
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set(sprintf('formStates.edit-%d.body', $comment->id), '[Error: Log content added]')
            ->call('updateComment', $comment->id)
            ->assertHasErrors([sprintf('formStates.edit-%d.body', $comment->id)]);

        $comment->refresh();
        expect($comment->body)->toBe('Original comment');
    });

    it('should allow editing a comment with valid content', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => $mod::class,
            'commentable_id' => $mod->id,
            'body' => 'Original comment',
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set(sprintf('formStates.edit-%d.body', $comment->id), 'Updated comment without logs')
            ->call('updateComment', $comment->id)
            ->assertHasNoErrors();

        $comment->refresh();
        expect($comment->body)->toBe('Updated comment without logs');
    });
});

describe('log detection error message', function (): void {
    it('should display the correct error message with code paste link', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', '[Error: Test log]')
            ->call('createComment');

        $errors = $component->instance()->getErrorBag();
        $errorMessage = $errors->first('newCommentBody');

        expect($errorMessage)->toContain('Log files detected')
            ->and($errorMessage)->toContain('https://codepaste.sp-tarkov.com');
    });
});

describe('moderators and admins', function (): void {
    it('should reject creating a comment with log content for moderators', function (): void {
        $moderator = User::factory()->moderator()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($moderator)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', '[Error: Log content from moderator]')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);
    });

    it('should reject creating a comment with log content for admins', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();

        Livewire::actingAs($admin)
            ->test('comment-component', ['commentable' => $mod])
            ->set('newCommentBody', '[Error: Log content from admin]')
            ->call('createComment')
            ->assertHasErrors(['newCommentBody']);
    });
});
