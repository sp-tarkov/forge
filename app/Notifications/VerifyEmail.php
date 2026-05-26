<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Messages\NotificationMailMessage;
use App\Traits\ThrottlesOutboundEmail;
use Illuminate\Auth\Notifications\VerifyEmail as OriginalVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;

/**
 * Makes the parent notification queueable and strengthens the verification
 * URL hash from sha1 to sha256.
 */
final class VerifyEmail extends OriginalVerifyEmail implements ShouldQueue
{
    use Queueable;
    use ThrottlesOutboundEmail;

    /**
     * Build the verification email using our standard branded template.
     *
     * @param  Model&MustVerifyEmail  $notifiable
     */
    public function toMail($notifiable): NotificationMailMessage // @pest-ignore-type
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new NotificationMailMessage)
            ->subject(Lang::get('Verify Email Address'))
            ->greeting(Lang::get('Verify Email Address'))
            ->line(Lang::get('Please click the button below to verify your email address.'))
            ->action(Lang::get('Verify Email Address'), $verificationUrl)
            ->footer(Lang::get('If you did not create an account, no further action is required.'));
    }

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
     *
     * @param  Model&MustVerifyEmail  $notifiable
     */
    protected function verificationUrl($notifiable): string // @pest-ignore-type
    {
        if (self::$createUrlCallback) {
            $url = call_user_func(self::$createUrlCallback, $notifiable);

            return is_string($url) ? $url : '';
        }

        return URL::temporarySignedRoute(
            'verification.verify',
            Date::now()->addMinutes(Config::integer('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => hash('sha256', $notifiable->getEmailForVerification()),
            ]
        );
    }
}
