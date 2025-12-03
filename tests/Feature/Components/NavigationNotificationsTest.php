<?php

declare(strict_types=1);

use App\Livewire\NavigationNotifications;
use App\Models\User;
use App\Notifications\NewCommentNotification;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('renders the component for authenticated users', function (): void {
    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertStatus(200)
        ->assertSee('Notifications');
});

it('displays unread count when there are unread notifications', function (): void {
    // Create unread notifications for the user
    $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Test User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSet('unreadCount', 1);
});

it('shows empty state when no unread notifications', function (): void {
    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSet('unreadCount', 0)
        ->assertSee('No new notifications');
});

it('can mark a single notification as read', function (): void {
    $notificationId = fake()->uuid();

    $this->user->notifications()->create([
        'id' => $notificationId,
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Test User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSet('unreadCount', 1)
        ->call('markAsRead', $notificationId)
        ->assertSet('unreadCount', 0);

    expect($this->user->notifications()->find($notificationId)->read_at)->not->toBeNull();
});

it('can mark all notifications as read', function (): void {
    // Create multiple unread notifications
    for ($i = 0; $i < 3; $i++) {
        $this->user->notifications()->create([
            'id' => fake()->uuid(),
            'type' => NewCommentNotification::class,
            'data' => [
                'commenter_name' => 'Test User '.$i,
                'commentable_title' => 'Test Mod',
                'comment_body' => 'Test comment',
                'comment_url' => '/test',
            ],
            'read_at' => null,
        ]);
    }

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSet('unreadCount', 3)
        ->call('markAllAsRead')
        ->assertSet('unreadCount', 0);

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('can delete a single notification', function (): void {
    $notificationId = fake()->uuid();

    $this->user->notifications()->create([
        'id' => $notificationId,
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Test User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSet('unreadCount', 1)
        ->call('deleteNotification', $notificationId)
        ->assertSet('unreadCount', 0);

    expect($this->user->notifications()->find($notificationId))->toBeNull();
});

it('can delete all notifications', function (): void {
    // Create multiple notifications
    for ($i = 0; $i < 3; $i++) {
        $this->user->notifications()->create([
            'id' => fake()->uuid(),
            'type' => NewCommentNotification::class,
            'data' => [
                'commenter_name' => 'Test User '.$i,
                'commentable_title' => 'Test Mod',
                'comment_body' => 'Test comment',
                'comment_url' => '/test',
            ],
            'read_at' => null,
        ]);
    }

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSet('unreadCount', 3)
        ->call('deleteAll')
        ->assertSet('unreadCount', 0);

    expect($this->user->notifications()->count())->toBe(0);
});

it('redirects to review url when reviewing a comment notification', function (): void {
    $notificationId = fake()->uuid();
    $commentUrl = '/mod/test-mod#comment-123';

    $this->user->notifications()->create([
        'id' => $notificationId,
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Test User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => $commentUrl,
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->call('reviewNotification', $notificationId)
        ->assertRedirect($commentUrl);

    // Verify the notification was marked as read
    expect($this->user->notifications()->find($notificationId)->read_at)->not->toBeNull();
});

it('redirects to review url when reviewing a report notification', function (): void {
    $notificationId = fake()->uuid();
    $reportableUrl = '/mod/reported-mod';

    $this->user->notifications()->create([
        'id' => $notificationId,
        'type' => ReportSubmittedNotification::class,
        'data' => [
            'reporter_name' => 'Reporter User',
            'reportable_title' => 'Reported Mod',
            'reportable_url' => $reportableUrl,
            'reason_label' => 'Spam',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->call('reviewNotification', $notificationId)
        ->assertRedirect($reportableUrl);
});

it('marks notification as read when reviewing', function (): void {
    $notificationId = fake()->uuid();

    $this->user->notifications()->create([
        'id' => $notificationId,
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Test User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSet('unreadCount', 1)
        ->call('reviewNotification', $notificationId)
        ->assertSet('unreadCount', 0);
});

it('only shows unread notifications in the list', function (): void {
    // Create one read and one unread notification
    $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Read User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => now(),
    ]);

    $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Unread User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSee('Unread User')
        ->assertDontSee('Read User');
});

it('limits displayed notifications to 10', function (): void {
    // Create 15 unread notifications
    for ($i = 0; $i < 15; $i++) {
        $this->user->notifications()->create([
            'id' => fake()->uuid(),
            'type' => NewCommentNotification::class,
            'data' => [
                'commenter_name' => 'User '.$i,
                'commentable_title' => 'Test Mod',
                'comment_body' => 'Test comment',
                'comment_url' => '/test',
            ],
            'read_at' => null,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    $this->actingAs($this->user);

    $component = Livewire::test(NavigationNotifications::class);

    expect($component->viewData('notifications')->count())->toBe(10);
});

it('displays different notification types with correct styling', function (): void {
    // Create comment notification
    $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Comment User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    // Create report notification
    $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => ReportSubmittedNotification::class,
        'data' => [
            'reporter_name' => 'Reporter User',
            'reportable_title' => 'Reported Content',
            'reportable_url' => '/reported',
            'reason_label' => 'Spam',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSee('Comment User')
        ->assertSee('commented on')
        ->assertSee('Reporter User')
        ->assertSee('reported');
});

it('does not show mark all as read button when no unread notifications', function (): void {
    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertDontSee('Mark all read');
});

it('shows mark all as read button when there are unread notifications', function (): void {
    $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Test User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSee('Mark all read');
});

it('shows delete all button when there are notifications', function (): void {
    $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Test User',
            'commentable_title' => 'Test Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSee('Delete all');
});

it('users can only see their own notifications', function (): void {
    $otherUser = User::factory()->create();

    // Create notification for other user
    $otherUser->notifications()->create([
        'id' => fake()->uuid(),
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'Other User Notification',
            'commentable_title' => 'Other Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    // Create notification for current user
    $this->user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => NewCommentNotification::class,
        'data' => [
            'commenter_name' => 'My Notification',
            'commentable_title' => 'My Mod',
            'comment_body' => 'Test comment',
            'comment_url' => '/test',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->assertSee('My Notification')
        ->assertDontSee('Other User Notification');
});

it('handles gracefully when reviewing non-existent notification', function (): void {
    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->call('reviewNotification', 'non-existent-id')
        ->assertStatus(200); // Should not error
});

it('handles gracefully when marking non-existent notification as read', function (): void {
    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->call('markAsRead', 'non-existent-id')
        ->assertStatus(200); // Should not error
});

it('handles gracefully when deleting non-existent notification', function (): void {
    $this->actingAs($this->user);

    Livewire::test(NavigationNotifications::class)
        ->call('deleteNotification', 'non-existent-id')
        ->assertStatus(200); // Should not error
});
