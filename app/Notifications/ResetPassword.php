<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Messages\NotificationMailMessage;
use App\Traits\ThrottlesOutboundEmail;
use Illuminate\Auth\Notifications\ResetPassword as OriginalResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use SensitiveParameter;

/**
 * This class exists solely to make the original notification queueable.
 */
final class ResetPassword extends OriginalResetPassword implements ShouldQueue
{
    use Queueable;
    use ThrottlesOutboundEmail;

    public function __construct(#[SensitiveParameter] string $token)
    {
        parent::__construct($token);
    }

    /**
     * Build the password reset email using our standard branded template.
     *
     * @param  CanResetPassword  $notifiable
     */
    public function toMail($notifiable): NotificationMailMessage // @pest-ignore-type
    {
        $resetUrl = $this->resetUrl($notifiable);
        $expireMinutes = Config::integer('auth.passwords.'.Config::string('auth.defaults.passwords').'.expire');

        return (new NotificationMailMessage)
            ->subject(Lang::get('Reset Password'))
            ->greeting(Lang::get('Reset Password'))
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->action(Lang::get('Reset Password'), $resetUrl)
            ->line(Lang::get('This password reset link will expire in :count minutes.', ['count' => $expireMinutes]))
            ->footer(Lang::get('If you did not request a password reset, no further action is required.'));
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
}
