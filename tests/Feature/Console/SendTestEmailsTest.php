<?php

declare(strict_types=1);

use App\Console\Commands\SendTestEmails;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\ContentGuidelinesUpdatedNotification;
use App\Notifications\NewChatMessageNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\ReportSubmittedNotification;
use App\Notifications\ResetPassword;
use App\Notifications\UserBannedNotification;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('refuses to run in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan(SendTestEmails::class, ['email' => 'someone@example.test'])
        ->assertFailed();
});

it('fails when the user does not exist', function (): void {
    $this->artisan(SendTestEmails::class, ['email' => 'nobody@example.test'])
        ->assertFailed();
});

it('dispatches every notification via the mail channel only', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->artisan(SendTestEmails::class, ['email' => $user->email])
        ->assertSuccessful();

    foreach ([
        ContentGuidelinesUpdatedNotification::class,
        NewChatMessageNotification::class,
        NewCommentNotification::class,
        CommentReplyNotification::class,
        ReportSubmittedNotification::class,
        UserBannedNotification::class,
        ResetPassword::class,
        VerifyEmail::class,
    ] as $notificationClass) {
        Notification::assertSentTo($user, $notificationClass, fn ($notification, array $channels): bool => $channels === ['mail']);
    }
});
