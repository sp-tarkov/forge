<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as OriginalVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Makes the parent notification queueable and strengthens the verification
 * URL hash from sha1 to sha256.
 */
final class VerifyEmail extends OriginalVerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Get the array representation of the notification.
     *
     * @return array<int, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }

    /**
     * Build the verification URL, hashing the email with sha256 instead of sha1.
     */
    protected function verificationUrl($notifiable): string
    {
        if (self::$createUrlCallback) {
            return call_user_func(self::$createUrlCallback, $notifiable);
        }

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => hash('sha256', (string) $notifiable->getEmailForVerification()),
            ]
        );
    }
}
