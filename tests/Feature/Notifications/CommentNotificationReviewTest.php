<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewCommentNotification;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('redirects notification-center review to comment_url for new-comment notifications', function (): void {
    $notificationId = fake()->uuid();
    $commentUrl = '/mod/1/slug#comments-comment-123';

    $this->user->notifications()->create([
        'id' => $notificationId,
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Commenter',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'A comment',
            'comment_url' => $commentUrl,
        ],
        'read_at' => null,
    ]);

    Livewire::test('notification-center')
        ->call('reviewNotification', $notificationId)
        ->assertRedirect($commentUrl);
});

it('redirects notification-center review to comment_url for reply notifications', function (): void {
    $notificationId = fake()->uuid();
    $commentUrl = '/mod/1/slug#comments-comment-789';

    $this->user->notifications()->create([
        'id' => $notificationId,
        'type' => CommentReplyNotification::class,
        'data' => [
            'commenter_name' => 'Replier',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'A reply',
            'comment_url' => $commentUrl,
            'is_reply' => true,
        ],
        'read_at' => null,
    ]);

    Livewire::test('notification-center')
        ->call('reviewNotification', $notificationId)
        ->assertRedirect($commentUrl);
});
