<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ContentGuidelinesUpdatedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Queue;

describe('Mail Channel Throttling', function (): void {
    it('rate limits the mail channel to respect the SES sending quota', function (): void {
        $middleware = (new ContentGuidelinesUpdatedNotification)->middleware(User::factory()->make(), 'mail');

        expect($middleware)->toHaveCount(1)
            ->and($middleware[0])->toBeInstanceOf(RateLimited::class);
    });

    it('does not rate limit non-mail channels', function (): void {
        $middleware = (new ContentGuidelinesUpdatedNotification)->middleware(User::factory()->make(), 'database');

        expect($middleware)->toBe([]);
    });

    it('retries throttled mail jobs against a deadline rather than a try count', function (): void {
        $notification = new ContentGuidelinesUpdatedNotification;

        expect($notification->retryUntil())->toBeInstanceOf(CarbonImmutable::class)
            ->and($notification->retryUntil()->isFuture())->toBeTrue()
            ->and($notification->maxExceptions)->toBe(3);
    });

    it('attaches the rate limiter only to the queued mail job', function (): void {
        Queue::fake();

        $user = User::factory()->create(['email_announcement_notifications_enabled' => true]);

        $user->notify(new ContentGuidelinesUpdatedNotification);

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->channels === ['mail']
            && count($job->middleware) === 1
            && $job->middleware[0] instanceof RateLimited);

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->channels === ['database']
            && $job->middleware === []);
    });
});
