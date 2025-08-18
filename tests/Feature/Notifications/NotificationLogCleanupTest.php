<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\Comment;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\NewCommentNotification;

it('removes old notification logs', function (): void {
    // Create some old and recent notification logs
    $user = User::factory()->create();
    $user2 = User::factory()->create();
    $comment = Comment::factory()->create();
    $comment2 = Comment::factory()->create();

    // Create old logs (should be deleted) - use different comments/users to avoid unique constraint
    $oldLog1 = NotificationLog::query()->create([
        'notifiable_type' => Comment::class,
        'notifiable_id' => $comment->id,
        'user_id' => $user->id,
        'notification_type' => NotificationType::EMAIL,
        'notification_class' => NewCommentNotification::class,
    ]);
    $oldLog1->update(['created_at' => now()->subDays(35)]);

    $oldLog2 = NotificationLog::query()->create([
        'notifiable_type' => Comment::class,
        'notifiable_id' => $comment2->id,
        'user_id' => $user2->id,
        'notification_type' => NotificationType::DATABASE,
        'notification_class' => NewCommentNotification::class,
    ]);
    $oldLog2->update(['created_at' => now()->subDays(40)]);

    // Create recent log (should NOT be deleted) - use different comment/user combination
    $user3 = User::factory()->create();
    $comment3 = Comment::factory()->create();
    $recentLog = NotificationLog::query()->create([
        'notifiable_type' => Comment::class,
        'notifiable_id' => $comment3->id,
        'user_id' => $user3->id,
        'notification_type' => NotificationType::ALL,
        'notification_class' => NewCommentNotification::class,
    ]);

    // Run the cleanup command with default 30 days retention
    $this->artisan('notifications:cleanup-logs')
        ->expectsOutput('Cleaning up notification logs older than 30 days...')
        ->expectsOutput('Successfully deleted 2 old notification logs.')
        ->assertExitCode(0);

    // Verify old logs were deleted
    expect(NotificationLog::query()->find($oldLog1->id))->toBeNull();
    expect(NotificationLog::query()->find($oldLog2->id))->toBeNull();

    // Verify recent log was NOT deleted
    expect(NotificationLog::query()->find($recentLog->id))->not->toBeNull();
});

it('accepts custom retention days', function (): void {
    $user = User::factory()->create();
    $comment = Comment::factory()->create();

    // Create log that's 15 days old
    $log = NotificationLog::query()->create([
        'notifiable_type' => Comment::class,
        'notifiable_id' => $comment->id,
        'user_id' => $user->id,
        'notification_type' => NotificationType::ALL,
        'notification_class' => NewCommentNotification::class,
    ]);
    $log->update(['created_at' => now()->subDays(15)]);

    // Run with 10 days retention (should delete the 15-day-old log)
    $this->artisan('notifications:cleanup-logs', ['--days' => 10])
        ->expectsOutput('Cleaning up notification logs older than 10 days...')
        ->expectsOutput('Successfully deleted 1 old notification logs.')
        ->assertExitCode(0);

    expect(NotificationLog::query()->find($log->id))->toBeNull();
});

it('handles no old logs gracefully', function (): void {
    $user = User::factory()->create();
    $comment = Comment::factory()->create();

    // Create only recent logs
    NotificationLog::query()->create([
        'notifiable_type' => Comment::class,
        'notifiable_id' => $comment->id,
        'user_id' => $user->id,
        'notification_type' => NotificationType::ALL,
        'notification_class' => NewCommentNotification::class,
    ]);

    // Run the cleanup command
    $this->artisan('notifications:cleanup-logs')
        ->expectsOutput('Cleaning up notification logs older than 30 days...')
        ->expectsOutput('No old notification logs found to delete.')
        ->assertExitCode(0);
});

it('works with different notifiable types', function (): void {
    $user = User::factory()->create();

    // Create an old log for a user (different notifiable type)
    $oldUserLog = NotificationLog::query()->create([
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'user_id' => $user->id,
        'notification_type' => NotificationType::EMAIL,
        'notification_class' => 'App\Notifications\WelcomeNotification',
    ]);
    $oldUserLog->update(['created_at' => now()->subDays(35)]);

    // Run cleanup
    $this->artisan('notifications:cleanup-logs')
        ->expectsOutput('Cleaning up notification logs older than 30 days...')
        ->expectsOutput('Successfully deleted 1 old notification logs.')
        ->assertExitCode(0);

    expect(NotificationLog::query()->find($oldUserLog->id))->toBeNull();
});
