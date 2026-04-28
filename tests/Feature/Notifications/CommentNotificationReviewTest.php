<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\ContentGuidelinesUpdatedNotification;
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

it('renders content guidelines notification with title and body in notification-center', function (): void {
    $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => ContentGuidelinesUpdatedNotification::class,
        'data' => [
            'title' => 'Content Guidelines Updated',
            'body' => 'Our Content Guidelines have been updated. The AI-Generated Content Policy now requires the "Contains AI Content" flag for any LLM-assisted content.',
            'url' => route('static.content-guidelines'),
        ],
        'read_at' => null,
    ]);

    Livewire::test('notification-center')
        ->assertSee('Content Guidelines Updated')
        ->assertSee('Contains AI Content')
        ->assertDontSee('Someone commented on');
});

it('redirects notification-center review to guidelines url for content guidelines notifications', function (): void {
    $notificationId = fake()->uuid();
    $guidelinesUrl = route('static.content-guidelines');

    $this->user->notifications()->create([
        'id' => $notificationId,
        'type' => ContentGuidelinesUpdatedNotification::class,
        'data' => [
            'title' => 'Content Guidelines Updated',
            'body' => 'Body text.',
            'url' => $guidelinesUrl,
        ],
        'read_at' => null,
    ]);

    Livewire::test('notification-center')
        ->call('reviewNotification', $notificationId)
        ->assertRedirect($guidelinesUrl);

    expect($this->user->notifications()->find($notificationId)->read_at)->not->toBeNull();
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
